<?php

namespace MongoExtensions\Tests\SoftDeletable\Fixture\Document;

use MongoExtensions\Mapping\Annotation as MongoExtensions;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * @ODM\Document(collection="users")
 * @MongoExtensions\SoftDeletable(fieldName="deletedAt")
 */
class User
{
    /** @ODM\Id */
    private $id;

    /** @ODM\Field(type="string") */
    private $username;

    /** @ODM\Field(type="date") */
    protected $deletedAt;

    /**
     * Sets deletedAt.
     *
     * @param \Datetime $deletedAt
     *
     * @return $this
     */
    public function setDeletedAt(?\DateTime $deletedAt)
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    /**
     * Returns deletedAt.
     *
     * @return \DateTime
     */
    public function getDeletedAt(): ?\DateTime
    {
        return $this->deletedAt;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setUsername($username)
    {
        $this->username = $username;
    }

    public function getUsername()
    {
        return $this->username;
    }
}
