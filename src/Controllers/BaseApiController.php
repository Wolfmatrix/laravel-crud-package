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
    private $em;

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
        $flipUrlParts = array_flip($urlParts);
        $resource = array_search(2, $flipUrlParts);
        $entityName = (ucwords(rtrim($resource, "s")));
        $namespace = "App\\Entities\\$entityName";

        return [$urlParts, $entityName, $namespace];
    }

    public function saveResource (Request $request)
    {
        list($urlParts, $entityName, $namespace) = $this->parseUrl($request->getPathInfo());
        $requestedBody = json_decode($request->getContent(), 1);

        if (sizeof($urlParts ) > 2) {
            $updateFlag = true;
            $entityId = array_pop($urlParts);
            $entity = $this->em->getRepository($namespace)->find($entityId);
            $oldEntity  = clone $entity;

        } else {
            $entity = new $namespace;
            $updateFlag = false;
            $entityId = null;
        }

        $formNameSpace = "App\\Forms\\{$entityName}Type";
        $form = $this->createForm($formNameSpace, $entity, ['id' => $entityId]);
        $form->submit($requestedBody, $updateFlag);

        if ($form->isSubmitted()) {
            if (!$form->isValid()) {
                $error = $this->formHelper->getErrorsFromForm($form);

                return \Response::json($error, Response::HTTP_BAD_REQUEST);
            }
            $this->em->persist($entity);
            $this->em->flush();
        }

        if($updateFlag){
            event(new SaveResourceEvent(self::UPDATE, $entityName, $entity, $oldEntity));
        } else {
            event(new SaveResourceEvent(self::CREATE, $entityName, $entity));
        }


        return \Response::json([$entity->toArray()], ($updateFlag ? Response::HTTP_OK : Response::HTTP_CREATED));
    }

    public function detailResource(Request $request)
    {
        list($urlParts, $entityName, $namespace) = $this->parseUrl($request->getPathInfo());

        $entity = $this->em->getRepository($namespace)->find(array_pop($urlParts));

        return \Response::json([$entity->toArray()], Response::HTTP_OK);
    }

    public function deleteResource(Request $request)
    {
        list($urlParts, $entityName, $namespace) = $this->parseUrl($request->getPathInfo());
        $entityId = array_pop($urlParts);
        $entity = $this->em->getRepository($namespace)->find($entityId);
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

    public function patchResource (Request $request)
    {
        list($urlParts, $entityName, $namespace) = $this->parseUrl($request->getPathInfo());
        $requestedBody = json_decode($request->getContent(), 1);
        $entityId = array_pop($urlParts);
        $entity = $this->em->getRepository($namespace)->find($entityId);
        $oldEntity = clone $entity;

        if (empty($requestedBody)) {
            return ['message' => 'At Least One Field Required'];
        }

        $keys = array_keys($requestedBody);
        $name = array_pop($keys);
        $formNameSpace = "App\\Forms\\{$entityName}Type";
        $form = $this->createForm($formNameSpace, $entity, ['id' => $entityId]);


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

        event(new PatchResourceEvent(self::PATCH, $entityName, $name ,$entity, $oldEntity));

        return \Response::json([$entity->toArray()], Response::HTTP_OK);

    }
}