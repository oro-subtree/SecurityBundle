<?php

namespace Oro\Bundle\SecurityBundle\Form\Model;

use Doctrine\Common\Collections\ArrayCollection;

class Share
{
    /** @var string */
    protected $entityClass;

    /** @var mixed */
    protected $entityId;

    /** @var ArrayCollection */
    protected $businessunits;

    /** @var ArrayCollection */
    protected $users;

    /**
     * Get class name of the target entity
     *
     * @return string
     */
    public function getEntityClass()
    {
        return $this->entityClass;
    }

    /**
     * Set class name of the target entity
     *
     * @param string $entityClass
     *
     * @return self
     */
    public function setEntityClass($entityClass)
    {
        $this->entityClass = $entityClass;

        return $this;
    }

    /**
     * Get id of the target entity
     *
     * @return string
     */
    public function getEntityId()
    {
        return $this->entityId;
    }

    /**
     * Set id of the target entity
     *
     * @param string $entityId
     *
     * @return self
     */
    public function setEntityId($entityId)
    {
        $this->entityId = $entityId;

        return $this;
    }

    /**
     * Returns collection of businessUnits
     *
     * @return ArrayCollection
     */
    public function getBusinessunits()
    {
        return $this->businessunits;
    }

    /**
     * Sets collection of businessUnits
     *
     * @param ArrayCollection $businessunits
     *
     * @return self
     */
    public function setBusinessunits(ArrayCollection $businessunits)
    {
        $this->businessunits = $businessunits;

        return $this;
    }

    /**
     * Returns collection of users
     *
     * @return ArrayCollection
     */
    public function getUsers()
    {
        return $this->users;
    }

    /**
     * Sets collection of users
     *
     * @param ArrayCollection $users
     *
     * @return self
     */
    public function setUsers(ArrayCollection $users)
    {
        $this->users = $users;

        return $this;
    }
}
