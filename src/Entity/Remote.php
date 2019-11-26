<?php

/*
 * Copyright (c) Terramar Labs
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Terramar\Packages\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Terramar\Packages\ApiSerializableEntityTrait;

/**
 * @ORM\Entity(repositoryClass="Terramar\Packages\Repository\RemoteRepository")
 * @ORM\Table(name="remotes")
 */
class Remote
{
    use ApiSerializableEntityTrait;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(name="id", type="integer")
     * @Groups({"rest"})
     */
    private $id;

    /**
     * @ORM\Column(name="name", type="string")
     * @Groups({"rest"})
     */
    private $name;

    /**
     * @ORM\Column(name="adapter", type="string", nullable=true)
     * @Groups({"rest"})
     */
    private $adapter;

    /**
     * @ORM\Column(name="enabled", type="boolean")
     * @Groups({"rest"})
     */
    private $enabled = true;

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * @param bool $enabled
     */
    public function setEnabled($enabled)
    {
        $this->enabled = (bool)$enabled;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name)
    {
        $this->name = (string)$name;
    }

    /**
     * @return string
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * @param string $adapter
     */
    public function setAdapter($adapter)
    {
        $this->adapter = (string)$adapter;
    }
}
