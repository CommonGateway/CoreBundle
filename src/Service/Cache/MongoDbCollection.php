<?php

namespace CommonGateway\CoreBundle\Service\Cache;

use CommonGateway\CoreBundle\Service\Cache\CollectionInterface;

class MongoDbCollection implements CollectionInterface
{

    private Collection $collection;

    public function __construct(Collection $collection)
    {
        $this->collection = $collection;

    }//end __construct()

    public function aggregate(array $pipeline, array $options = []): \Iterator
    {
        return $this->collection->aggegrate($pipeline, $options);

    }//end aggregate()

    public function count(array $filter = [], array $options = []): int
    {
        return $this->collection->count($filter, $options);

    }//end count()

    public function createIndex(object|array $key, array $options = []): string
    {
        return $this->collection->createIndex($key, $options);

    }//end createIndex()

    public function createSearchIndex(object|array $definition, array $options = []): string
    {
        return $this->collection->createSearchIndex($definition, $options);

    }//end createSearchIndex()

    public function find(array $filter = [], array $options = []): \Iterator
    {
        return $this->collection->find($filter, $options);

    }//end find()

    public function findOne(array $filter = [], array $options = []): \Iterator
    {
        return $this->collection->findONe($filter, $options);

    }//end findOne()

    public function findOneAndDelete(array $filter = [], array $options = []): array|null|object
    {
        return $this->collection->findOneAndDelete($filter, $options);

    }//end findOneAndDelete()

    public function findOneAndReplace(object|array $filter, object|array $replacement, array $options = []): array|null|object
    {
        return $this->collection->findOneAndReplace($filter, $replacement, $options);

    }//end findOneAndReplace()

    public function insertOne(object|array $document, array $options = []): array|object
    {
        return $this->collection->insertOne($document, $options);

    }//end insertOne()
}//end class
