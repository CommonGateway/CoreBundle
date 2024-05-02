<?php

namespace CommonGateway\CoreBundle\Service\Cache;

interface DatabaseInterface
{
    public function __get(string $collectionName): CollectionInterface;
}