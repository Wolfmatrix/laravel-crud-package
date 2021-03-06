<?php

namespace Wolfmatrix\LaravelCrud\Controllers;

use App\Http\Controllers\Controller;
use Barryvdh\Form\CreatesForms;
use Barryvdh\Form\ValidatesForms;
use Doctrine\ORM\EntityManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Wolfmatrix\LaravelCrud\Events\PatchResourceEvent;
use Wolfmatrix\LaravelCrud\Events\SaveResourceEvent;
use Wolfmatrix\LaravelCrud\Events\DeleteResourceEvent;
use Wolfmatrix\LaravelCrud\Services\FormHelper;

class BaseApiController extends Controller
{
    use ValidatesForms, CreatesForms;

    /**
     * @var EntityManager
     */
    protected $em;

    protected $formHelper;

    const CREATE = 'created';
    const UPDATE = 'updated';
    const DELETE = 'deleted';
    const PATCH = 'patched';

    public function __construct(EntityManager $em, FormHelper $formHelper)
    {
        $this->em = $em;
        $this->formHelper = $formHelper;
    }

    public function parseUrl ($pathInfo)
    {
        $urlParts = array_filter(explode("/", $pathInfo));

        if(sizeof($urlParts) > 4) {
            $flipUrlParts = array_flip($urlParts);
            $resource = array_search(5, $flipUrlParts);

        } else {
            $flipUrlParts = array_flip($urlParts);
            $resource = array_search(3, $flipUrlParts);

        }

        $folderName = ucwords(array_search(2, $flipUrlParts));
        $ucWord = ucwords($resource,'-');
        $pregReplace = preg_replace("/[^a-zA-Z]/", '', $ucWord);
        $entityName = (ucwords(rtrim($pregReplace, "s")));
        if(substr($pregReplace, -3) == 'ies') {
            $pos = strpos($pregReplace, 'ies');
            $entityName = substr_replace($pregReplace, 'y', $pos);

        } elseif (substr($pregReplace, -3) == 'ses') {
            $entityName = ucwords(substr($pregReplace, 0, -2));
        }
        $namespace = "App\\Entities\\$folderName\\$entityName";

        return [$urlParts, $entityName, $namespace, $folderName];
    }

    public function saveResource (Request $request)
    {
        list($urlParts, $entityName, $namespace, $folderName) = $this->parseUrl($request->getPathInfo());
        $requestedBody = json_decode($request->getContent(), 1);

        if (sizeof($urlParts ) > 4 ) {
            $parentResource = $urlParts[3];
            $ucWord = ucwords($parentResource,'-');
            $pregReplace = preg_replace("/[^a-zA-Z]/", '', $ucWord);
            $parentEntity = lcfirst(rtrim($pregReplace, 's'));
            if (substr($pregReplace, -3) == 'ies') {
                $pos = strpos($pregReplace, 'ies');
                $parentEntity = lcfirst(substr_replace($pregReplace, 'y', $pos));
            } elseif (substr($pregReplace, -3) == 'ses') {
                $parentEntity = lcfirst(substr($pregReplace, 0, -2));
            }

            if(!isset($requestedBody[$parentEntity])){
                return \Response::json(null, Response::HTTP_FORBIDDEN);
            }elseif (!isset($requestedBody[$parentEntity]['id'])){
                return \Response::json(null, Response::HTTP_FORBIDDEN);
            }
            else if(isset($requestedBody[$parentEntity]['id']) && $requestedBody[$parentEntity]['id'] != $urlParts[4]){
                return \Response::json(null, Response::HTTP_FORBIDDEN);
            }
        }

        if (sizeof($urlParts ) == 4 || sizeof($urlParts ) == 6 ) {
            $updateFlag = true;
            $entityId = array_pop($urlParts);
            $entity = $this->em->getRepository($namespace)->find($entityId);
            $oldEntity  = clone $entity;

        } elseif(sizeof($urlParts) == 3  || sizeof($urlParts ) == 5) {
            $entity = new $namespace;
            $updateFlag = false;
            $entityId = null;
        }

        $formNameSpace = "App\\Forms\\$folderName\\{$entityName}Type";
        $form = $this->createForm($formNameSpace, $entity, ['id' => $entityId, 'em' => $this->em]);

        $flattenRequestBody = [];

        array_walk(
            $requestedBody,
            function($item, $key) use (&$flattenRequestBody) {
                if(is_array($item)) {
                    if(array_key_exists('id', $item)) {
                        $flattenRequestBody[$key] = $item['id'];
                    } else {
                        array_walk($item, function ($i, $k) use (&$flattenRequestBody, $key) {
                            if (is_array($i) && array_key_exists('id', $i)) {
                                $flattenRequestBody[$key][$k] = $i['id'];
                            } elseif (!is_array($i)){
                                $flattenRequestBody[$key][$k] = $i;
                            } else {
                                array_walk($i, function ($it, $ke) use (&$flattenRequestBody, $key, $k) {
                                    if (is_array($it) && array_key_exists('id', $it)) {
                                        $flattenRequestBody[$key][$k][$ke] = $it['id'];
                                    } else {
                                        $flattenRequestBody[$key][$k][$ke] = $it;
                                    }
                                });
                            }
                        });
                    }
                } else {
                    $flattenRequestBody[$key] = $item;
                }
            }
        );

        $form->submit($flattenRequestBody, $updateFlag);

        if ($form->isSubmitted()) {
            if (!$form->isValid()) {
                $error = $this->formHelper->getErrorsFromForm($form);

                return \Response::json($error, Response::HTTP_BAD_REQUEST);
            }
            $this->em->persist($entity);
            $this->em->flush();
        }

        if($updateFlag){
            event(new SaveResourceEvent(self::UPDATE, $entityName, $entity, $requestedBody, $oldEntity));
        } else {
            event(new SaveResourceEvent(self::CREATE, $entityName, $entity, $requestedBody));
        }


        return \Response::json([$entity->toArray()], ($updateFlag ? Response::HTTP_OK : Response::HTTP_CREATED));
    }

    public function detailResource(Request $request)
    {
        list($urlParts, $entityName, $namespace) = $this->parseUrl($request->getPathInfo());

        $entity = $this->em->getRepository($namespace)->find(array_pop($urlParts));
        if (!$entity) {
            return \Response::json(null, Response::HTTP_NOT_FOUND);
        }

        if (sizeof($urlParts) > 4) {
            $parentResource = $urlParts[3];
            $ucWord = ucwords($parentResource,'-');
            $pregReplace = preg_replace("/[^a-zA-Z]/", '', $ucWord);
            $parentEntityName = (ucwords(rtrim($pregReplace, "s")));
            if(substr($pregReplace, -3) == 'ies') {
                $pos = strpos($pregReplace, 'ies');
                $parentEntityName = substr_replace($pregReplace, 'y', $pos);

            } elseif (substr($pregReplace, -3) == 'ses') {
                $parentEntityName = ucwords(substr($pregReplace, 0, -2));
            }

            if ($urlParts[4] != $entity->{"get".$parentEntityName}()->getId()) {
                return \Response::json(null, Response::HTTP_NOT_FOUND);
            }
        }

        return \Response::json([$entity->toArray()], Response::HTTP_OK);
    }

    public function deleteResource(Request $request)
    {
        list($urlParts, $entityName, $namespace) = $this->parseUrl($request->getPathInfo());
        $entityId = array_pop($urlParts);
        $entity = $this->em->getRepository($namespace)->find($entityId);
        if (!$entity) {
            return \Response::json(null, Response::HTTP_NOT_FOUND);
        }

        if (sizeof($urlParts) > 4) {
            $parentResource = $urlParts[3];
            $ucWord = ucwords($parentResource,'-');
            $pregReplace = preg_replace("/[^a-zA-Z]/", '', $ucWord);
            $parentEntityName = (ucwords(rtrim($pregReplace, "s")));
            if(substr($pregReplace, -3) == 'ies') {
                $pos = strpos($pregReplace, 'ies');
                $parentEntityName = substr_replace($pregReplace, 'y', $pos);

            } elseif (substr($pregReplace, -3) == 'ses') {
                $parentEntityName = ucwords(substr($pregReplace, 0, -2));
            }

            if ($urlParts[4] != $entity->{"get".$parentEntityName}()->getId()) {
                return \Response::json(null, Response::HTTP_NOT_FOUND);
            }
        }

        $oldEntity = clone $entity;

        $this->em->remove($entity);
        $this->em->flush();

        event(new DeleteResourceEvent(self::DELETE, $entityName, $oldEntity));

        return \Response::json([null], Response::HTTP_NO_CONTENT);

    }

    public function listResource (Request $request)
    {
        list($urlParts, $entityName, $namespace) = $this->parseUrl($request->getPathInfo());
        $data = $this->em->getRepository($namespace)
            ->searchFilterSort(
                $request->get('search'),
                $request->get('filter'),
                $request->get('sort')
            )
            ->setPage($request->get('page', 1), $request->get('pageSize'))
            ->getResults()
        ;
        return \Response::json([$data], Response::HTTP_OK);
    }

    public function listSubResource (Request $request)
    {
        list($urlParts, $entityName, $namespace, $folderName) = $this->parseUrl($request->getPathInfo());

        $extendedResource = $urlParts[3];
        $ucWord = ucwords($extendedResource,'-');
        $pregReplace = preg_replace("/[^a-zA-Z]/", '', $ucWord);
        $extendedEntityName = (ucwords(rtrim($pregReplace, "s")));

        if(substr($pregReplace, -3) == 'ies') {
            $pos = strpos($pregReplace, 'ies');
            $extendedEntityName = substr_replace($pregReplace, 'y', $pos);

        } elseif (substr($pregReplace, -3) == 'ses') {
            $extendedEntityName = ucwords(substr($pregReplace, 0, -2));
        }

        $extendedNamespace = "App\\Entities\\$folderName\\$extendedEntityName";
        $extendedEntity = $this->em->getRepository($extendedNamespace)->find($urlParts[4]);

        if (!$extendedEntity) {
            return \Response::json(null, Response::HTTP_NOT_FOUND);
        }

        $data = $this->em->getRepository($namespace)
            ->setParam('entityId', $extendedEntity->getId())
            ->loadExtendBuilder()
            ->searchFilterSort(
                $request->get('search'),
                $request->get('filter'),
                $request->get('sort')
            )
            ->setPage($request->get('page', 1), $request->get('pageSize'))
            ->getResults()
        ;

        return \Response::json([$data], Response::HTTP_OK);
    }

    public function patchResource (Request $request)
    {
        list($urlParts, $entityName, $namespace, $folderName) = $this->parseUrl($request->getPathInfo());
        $requestedBody = json_decode($request->getContent(), 1);
        $entityId = array_pop($urlParts);
        $entity = $this->em->getRepository($namespace)->find($entityId);
        $oldEntity = clone $entity;

        if (empty($requestedBody)) {
            return ['message' => 'At Least One Field Required'];
        }

        $keys = array_keys($requestedBody);
        $name = array_pop($keys);
        $formNameSpace = "App\\Forms\\$folderName\\{$entityName}Type";
        $form = $this->createForm($formNameSpace, $entity, ['id' => $entityId, 'em' => $this->em]);


        foreach ($form->all() as $fields) {
            if ($fields->getName() != $name) {
                $form->remove($fields->getName());
            }
        }

        $form->submit($requestedBody);
        if ($form->isSubmitted()) {
            if (!$form->isValid()) {
                $error = $this->formHelper->getErrorsFromForm($form);

                return $error;
            }
            $this->em->persist($entity);
            $this->em->flush();
        }

        event(new PatchResourceEvent(self::PATCH, $entityName, $requestedBody, $name ,$entity, $oldEntity));

        return \Response::json([$entity->toArray()], Response::HTTP_OK);

    }
}
