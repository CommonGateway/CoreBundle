<?php

namespace CommonGateway\CoreBundle\Service\Cache;

use CommonGateway\CoreBundle\Service\Cache\CollectionInterface;

class MongoDbCollection implements CollectionInterface
{
    private Collection $collection;

    public function __construct(Collection $collection) {
        $this->collection = $collection;
    }

    public function aggregate(array $pipeline, array $options = []): \Iterator
    {
        return $this->collection->aggegrate($pipeline, $options);
    }

    public function count(array $filter = [], array $options = []): int
    {
        return $this->collection->count($filter, $options);
    }

    public function createIndex(object|array $key, array $options = []): array|false
    {
        return $this->collection->createIndex($key, $options);
    }

    public function createSearchIndex(object|array $definition, array $options = []): string
    {
        return $this->collection->createSearchIndex($definition, $options);
    }

    public function find(array $filter = [], array $options = []): \Iterator
    {
        return $this->collection->find($filter, $options);
    }

    public function findOne(array $filter = [], array $options = []): \Iterator
    {
        return $this->collection->findONe($filter, $options);
    }

    public function findOneAndDelete(array $filter = [], array $options = []): array|null|object
    {
        return $this->collection->findOneAndDelete($filter, $options);
    }

    public function findOneAndReplace(object|array $filter, object|array $replacement, array $options = []): array|null|object
    {
        return $this->collection->findOneAndReplace($filter, $replacement, $options);
    }

    public function insertOne(object|array $document, array $options = []): array|object
    {
        return $this->collection->insertOne($document, $options);
    }
}