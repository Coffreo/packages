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
 * @ORM\Entity(repositoryClass="Terramar\Packages\Repository\PackageRepository")
 * @ORM\Table(name="packages")
 */
class Package
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
     * @ORM\Column(name="external_id", type="string")
     * @Groups({"rest"})
     */
    private $externalId;

    /**
     * @ORM\Column(name="hook_external_id", type="string")
     * @Groups({"rest"})
     */
    private $hookExternalId = '';

    /**
     * @ORM\Column(name="description", type="string")
     * @Groups({"rest"})
     */
    private $description;

    /**
     * @ORM\Column(name="enabled", type="boolean")
     * @Groups({"rest"})
     */
    private $enabled = false;

    /**
     * @ORM\Column(name="ssh_url", type="string")
     * @Groups({"rest"})
     */
    private $sshUrl;

    /**
     * @ORM\Column(name="web_url", type="string")
     * @Groups({"rest"})
     */
    private $webUrl;

    /**
     * @ORM\Column(name="fqn", type="string")
     * @Groups({"rest"})
     */
    private $fqn;

    /**
     * @ORM\ManyToOne(targetEntity="Terramar\Packages\Entity\Remote")
     * @ORM\JoinColumn(name="configuration_id", referencedColumnName="id")
     * @Groups({"rest"})
     */
    private $remote;

    /**
     * @param mixed $enabled
     */
    public function setEnabled($enabled)
    {
        $this->enabled = (bool)$enabled;
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
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
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param mixed $description
     */
    public function setDescription($description)
    {
        $this->description = (string)$description;
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
     * @return Remote
     */
    public function getRemote()
    {
        return $this->remote;
    }

    /**
     * @param Remote $remote
     */
    public function setRemote(Remote $remote)
    {
        $this->remote = $remote;
    }

    /**
     * @return mixed
     */
    public function getExternalId()
    {
        return $this->externalId;
    }

    /**
     * @param mixed $externalId
     */
    public function setExternalId($externalId)
    {
        $this->externalId = (string)$externalId;
    }

    /**
     * @return mixed
     */
    public function getHookExternalId()
    {
        return $this->hookExternalId;
    }

    /**
     * @param mixed $hookExternalId
     */
    public function setHookExternalId($hookExternalId)
    {
        $this->hookExternalId = (string)$hookExternalId;
    }

    /**
     * @return mixed
     */
    public function getFqn()
    {
        return $this->fqn;
    }

    /**
     * @param mixed $fqn
     */
    public function setFqn($fqn)
    {
        $this->fqn = (string)$fqn;
    }

    /**
     * @return mixed
     */
    public function getSshUrl()
    {
        return $this->sshUrl;
    }

    /**
     * @param mixed $sshUrl
     */
    public function setSshUrl($sshUrl)
    {
        $this->sshUrl = (string)$sshUrl;
    }

    /**
     * @return mixed
     */
    public function getWebUrl()
    {
        return $this->webUrl;
    }

    /**
     * @param mixed $webUrl
     */
    public function setWebUrl($webUrl)
    {
        $this->webUrl = (string)$webUrl;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return sprintf("%d:%d", $this->remote->getId(), $this->externalId);
    }
}
