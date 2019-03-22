<?php

namespace Wolfmatrix\LaravelCrud\Entities;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;

class BaseEntity extends EntityRepository
{
    const  DEFAULT_ALIAS = 'l';

    public $searchable;
    public $filterable;
    public $sortable;
    public $excludable;
    public $queryBuilder;
    public $pagerfanta = null;
    public $range;
    public $params     = [];
    public $autoExtend = true;

    public function __construct($em, ClassMetadata $class)
    {
        parent::__construct($em, $class);
        $this->queryBuilder = $this->createQueryBuilder(self::DEFAULT_ALIAS);
        $this->autoExtend && $this->loadExtendBuilder();
    }

    public function loadExtendBuilder()
    {
        if (method_exists($this, 'extendBuilder')) {
            $this->queryBuilder = $this->extendBuilder($this->queryBuilder);
        }
        return $this;
    }

    public function isForeignKey($column)
    {
        return strpos($column, '.');
    }

    public function getColumnName($column)
    {
        if ($this->isForeignKey($column)) {
            return $column;
        } else {
            return self::DEFAULT_ALIAS . ".$column";
        }
    }

    public function getFilterKey($filters, $column, $aliasMap)
    {
        if ($this->isForeignKey($column)) {
            list($alias, $column) = explode('.', $column);
            $filterKey = $aliasMap[$alias];
            $child     = $this->getFilterKey($filters[$filterKey], $filterKey, $aliasMap);
        } else {
            return $filters[$column];
        }
    }

    public function searchFilterSort($search = null, $filters = null, $sort = null, $excludes = null, $status = true)
    {

        if ($search) {
            $where = '';
            foreach ($this->searchable as $column) {
                $where .= $this->getColumnName($column) . " LIKE :search OR ";
            }
            $this->queryBuilder->andWhere(rtrim($where, 'OR '))
                               ->setParameter('search', strtolower("%{$search}%"))
            ;
        }

        if ($filters) {
            $aliasMap = array_flip($this->alias);
            foreach ($this->filterable as $key => $value) {
                if ($this->isForeignKey($value)) {
                    list($tblName, $column) = explode('.', $value);
                    $filterKey = $aliasMap[$tblName];
                    if (isset($filters[$filterKey][$column])) {
                        $this->queryBuilder->andWhere($value . " = :val" . $key)
                                           ->setParameter('val' . $key, $filters[$filterKey][$column])
                        ;
                    }
                } elseif (isset($filters[$value])) {
                    $this->queryBuilder->andWhere($this->getColumnName($value) . " = :val" . $key)
                                       ->setParameter('val' . $key, $filters[$value])
                    ;
                }
            }
        }

        if ($sort) {
            $sortKey   = key($sort);
            $sortOrder = array_pop($sort);


            //Used for multidimensional sort like 'sort[organization][name]'
            if (is_array($sortOrder) && isset($this->alias[$sortKey])) {
                $childKey  = key($sortOrder);
                $sortKey   = $this->alias[$sortKey] . ".$childKey";
                $sortOrder = array_pop($sortOrder);
            }

            if (in_array($sortOrder, ['asc', 'desc']) && in_array($sortKey, $this->sortable)) {
                $this->queryBuilder->addOrderBy($this->getColumnName($sortKey), strtoupper($sortOrder));
            }
        }

        if ($excludes) {
            $excludeKey    = key($excludes);
            $excludeFields = array_pop($excludes);
            if (in_array($excludeKey, $this->excludable)) {
                foreach ($excludeFields as $key => $value) {
                    $this->queryBuilder->andWhere($this->getColumnName($excludeKey) . " != :exclude" . $key)
                                       ->setParameter('exclude' . $key, $value)
                    ;
                }
            }
        }

        return $this;

    }

    public function setRange($range = false)
    {
        if ($range) {
            $aliasMap = array_flip($this->alias);

            foreach ($this->range as $rangeValue) {
                if ($this->isForeignKey($rangeValue)) {
                    list($tblName, $column) = explode('.', $rangeValue);
                    $rangeKey = $aliasMap[$tblName];
                    if (isset($range[$rangeKey][$column])) {
                        $this->queryBuilder->andWhere("$rangeValue BETWEEN :start AND :end")
                                           ->setParameter('start', $range[$rangeKey][$column]['start'])
                                           ->setParameter('end', $range[$rangeKey][$column]['end'])
                        ;
                    }
                } elseif (isset($range[$rangeValue])) {
                    $value = $this->getColumnName($rangeValue);
                    $this->queryBuilder->andWhere("$value BETWEEN :start AND :end")
                                       ->setParameter('start', $range[$rangeValue]['start'])
                                       ->setParameter('end', $range[$rangeValue]['end'])
                    ;
                }
            }
        }
//        dump($this->queryBuilder->getQuery());die;
        return $this;
    }

    public function setPage($currentPage, $pageSize)
    {
        if ($pageSize == -1) {
            return $this;
        }
        $pageSize    = (int)$pageSize ? $pageSize : 10;
        $currentPage = (int)$currentPage ? $currentPage : 1;
        //dump($pageSize,$currentPage);die;
        $adapter          = new DoctrineORMAdapter($this->queryBuilder);
        $this->pagerfanta = new Pagerfanta($adapter);
        $this->pagerfanta->setMaxPerPage($pageSize);
        $this->pagerfanta->setCurrentPage($currentPage);

        return $this;
    }

    public function getResults($param = false)
    {

        if (!$this->pagerfanta) {
            $results = $this->queryBuilder->getQuery()->getResult();
        } else {
            $results = $this->pagerfanta->getCurrentPageResults();
        }
        $collections = [];

        foreach ($results as $result) {
            if (is_array($result)) {
                foreach ($result as $key => $value) {
                    if (method_exists($value, "toArray")) {
                        $collections[] = $value->toArray($param);
                    }
                }
            } else {
                $collections[] = $result->toArray($param);
            }
        }

        $response = [
            'total'   => $this->pagerfanta ? $this->pagerfanta->getNbResults() : count($collections),
            'count'   => count($collections),
            'results' => &$collections,
        ];

        return $response;
    }

    public function setParam($key, $value)
    {
        $this->params[$key] = $value;
        return $this;
    }

    public function debugQuery($sql = false)
    {
        $sql ?
            dump($this->queryBuilder->getQuery()->getSQL())
            : dump($this->queryBuilder->getQuery());
        die;
    }
}