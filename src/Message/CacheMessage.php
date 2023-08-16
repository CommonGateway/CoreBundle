<?php

namespace CommonGateway\CoreBundle\Message;

use Ramsey\Uuid\UuidInterface;

class CacheMessage
{
    private UuidInterface $objectEntityId;

    public function __construct(UuidInterface $actionId)
    {
        $this->objectEntityId = $actionId;
    }

    public function getObjectEntityId(): UuidInterface
    {
        return $this->objectEntityId;
    }
}
