<?php

/*
 * Copyright (c) Terramar Labs
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Terramar\Packages\Repository;

use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Terramar\Packages\Entity\Package;
use Terramar\Packages\Entity\Remote;

class RemoteRepository extends EntityRepository
{
    /**
     * @var QueryBuilder
     */
    protected $builder;

    public function get($hydrationMode = Query::HYDRATE_OBJECT)
    {
        return $this
            ->getQuery()
            ->getResult($hydrationMode);
    }

    public function getQueryAndFlushBuilder()
    {
        $query = $this->getBuilder()->getQuery();
        $this->builder = null;

        return $query;
    }

    public function getPaginator()
    {
        return new Paginator($this->getQueryAndFlushBuilder());
    }

    public function getRemote($nameOrId, $enabled = null)
    {
        return is_numeric($nameOrId)
            ? $this->getRemoteById((int) $nameOrId, $enabled)
            : $this->getRemoteByName($nameOrId, $enabled);
    }

    /**
     * @param $id
     * @param $enabled
     *
     * @return Remote|null
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getRemoteById($id, $enabled = null)
    {
        $this
            ->addEnabledCriteria($enabled)
            ->getBuilder()
            ->where('r.id = :id')
            ->setParameter('id', $id);

        return $this
            ->getQueryAndFlushBuilder()
            ->getOneOrNullResult();
    }

    /**
     * @param $name
     * @param $enabled
     *
     * @return Package|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getRemoteByName($name, $enabled = null)
    {
        $this
            ->addEnabledCriteria($enabled)
            ->getBuilder()
            ->where('r.name = :name')
            ->setParameter('name', $name);

        return $this
            ->getQueryAndFlushBuilder()
            ->getOneOrNullResult();
    }

    public function setLimit($limit)
    {
        if ($limit !== null && $limit !== '') {
            $this->getBuilder()->setMaxResults($limit);
        }

        return $this;
    }

    public function setOffset($offset)
    {
        if ($offset !== null && $offset !== '') {
            $this->getBuilder()->setFirstResult($offset);
        }

        return $this;
    }

    protected function getBuilder()
    {
        if (!$this->builder) {
            $this->builder = $this->createQueryBuilder('r');
        }

        return $this->builder;
    }

    /**
     * @param bool $enabled
     *
     * @return $this
     */
    public function addEnabledCriteria($enabled)
    {
        if ($enabled !== null && $enabled != '') {
            $this->getBuilder()
                ->andWhere('r.enabled = :enabled')
                ->setParameter('enabled', $enabled);
        }

        return $this;
    }

    public function addSearchCriteria($search)
    {
        if (!$search) {
            return $this;
        }

        $criteria = Criteria::create()
            ->andWhere(Criteria::expr()->contains('r.name', $search));

        $this->builder->addCriteria($criteria);

        return $this;
    }
}
