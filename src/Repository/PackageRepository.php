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

class PackageRepository extends EntityRepository
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

    public function getPackage($nameOrId)
    {
        return is_numeric($nameOrId)
            ? $this->getPackageById((int) $nameOrId)
            : $this->getPackageByFqn($nameOrId);
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

    /**
     * @param $fqn
     *
     * @return Package|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getPackageByFqn($fqn)
    {
        $this
            ->addEnabledRemoteCriteria(true)
            ->getBuilder()
            ->where('p.fqn = :fqn')
            ->setParameter('fqn', $fqn);

        return $this
            ->getQueryAndFlushBuilder()
            ->getOneOrNullResult();
    }

    /**
     * @param $id
     *
     * @return Package|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getPackageById($id)
    {
        $this
            ->addEnabledRemoteCriteria(true)
            ->getBuilder()
            ->where('p.id = :id')
            ->setParameter('id', $id);

        return $this
            ->getQueryAndFlushBuilder()
            ->getOneOrNullResult();
    }

    protected function getBuilder()
    {
        if (!$this->builder) {
            $this->builder = $this->createQueryBuilder('p');
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
                ->andWhere('p.enabled = :enabled')
                ->setParameter('enabled', (bool) $enabled);
        }

        return $this;
    }

    /**
     * @param bool $enabled
     *
     * @return $this
     */
    public function addEnabledRemoteCriteria($enabled)
    {
        if ($enabled !== null && $enabled != '') {
            $this->getBuilder()
                ->join(
                    'p.remote',
                    'r',
                    'WITH',
                    sprintf('r.enabled = %s', var_export((bool) $enabled, true))
                );
        }

        return $this;
    }

    public function addSearchCriteria($search)
    {
        if (!$search) {
            return $this;
        }

        $criteria = Criteria::create()
            ->andWhere(Criteria::expr()->contains('p.fqn', $search));

        $this->builder->addCriteria($criteria);

        return $this;
    }
}
