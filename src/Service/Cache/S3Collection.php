<?php

namespace CommonGateway\CoreBundle\Service\Cache;

use CommonGateway\CoreBundle\Service\Cache\CollectionInterface;

class S3Collection implements CollectionInterface
{

    public function __construct(private readonly S3Database $database, private readonly string $name)
    {

    }

    public function aggregate(array $pipeline, array $options = []): \Iterator
    {
        // TODO: Implement aggregate() method.
    }

    public function count(array $filter = [], array $options = []): int
    {
        // TODO: Implement count() method.
    }

    public function createIndex(object|array $key, array $options = []): array|false
    {
        // TODO: Implement createIndex() method.
    }

    public function createSearchIndex(object|array $definition, array $options = []): string
    {
        // TODO: Implement createSearchIndex() method.
    }

    public function find(array $filter = [], array $options = []): \Iterator
    {
        // TODO: Implement find() method.
    }

    public function findOne(array $filter = [], array $options = []): \Iterator
    {
        // TODO: Implement findOne() method.
    }

    public function findOneAndDelete(array $filter = [], array $options = []): array|null|object
    {
        // TODO: Implement findOneAndDelete() method.
    }

    public function findOneAndReplace(object|array $filter, object|array $replacement, array $options = []): array|null|object
    {
        // TODO: Implement findOneAndReplace() method.
    }

    public function insertOne(object|array $document, array $options = []): array|object
    {
        // TODO: Implement insertOne() method.
    }
}