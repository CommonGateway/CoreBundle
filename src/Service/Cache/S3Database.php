<?php

namespace CommonGateway\CoreBundle\Service\Cache;

use CommonGateway\CoreBundle\Service\Cache\DatabaseInterface;

class S3Database implements DatabaseInterface
{
    private array $collections;
    public function __construct(private readonly S3Client $client, private readonly string $name)
    {
    }

    public function __get(string $collectionName): CollectionInterface
    {
        if(isset($this->collections[$collectionName]) === false) {
            $this->collections[$collectionName] = $collection = new S3Collection($this, $collectionName);

            return $collection;
        }

        return $this->collections[$collectionName];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getClient(): S3Client
    {
        return $this->client;
    }
}