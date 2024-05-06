<?php

namespace CommonGateway\CoreBundle\Service\Cache;

use CommonGateway\CoreBundle\Service\Cache\CollectionInterface;

class S3Collection implements CollectionInterface
{
    public function __construct(private readonly S3Database $database, private readonly string $name)
    {

    }//end __construct()

    public function aggregate(array $pipeline, array $options = []): \Iterator
    {
        // TODO: Implement aggregate() method.

    }//end aggregate()

    public function count(array $filter = [], array $options = []): int
    {
        // TODO: Implement count() method.

    }//end count()

    public function createIndex(object|array $key, array $options = []): string
    {
        // TODO: Implement createIndex() method.

    }//end createIndex()

    public function createSearchIndex(object|array $definition, array $options = []): string
    {
        // TODO: Implement createSearchIndex() method.

    }//end createSearchIndex()

    public function find(array $filter = [], array $options = []): \Iterator
    {
        // TODO: Implement find() method.

    }//end find()

    public function findOne(array $filter = [], array $options = []): \Iterator
    {
        // TODO: Implement findOne() method.

    }//end findOne()

    public function findOneAndDelete(array $filter = [], array $options = []): array|null|object
    {
        // TODO: Implement findOneAndDelete() method.

    }//end findOneAndDelete()

    public function findOneAndReplace(object|array $filter, object|array $replacement, array $options = []): array|null|object
    {
        // TODO: Implement findOneAndReplace() method.

    }//end findOneAndReplace()

    public function insertOne(object|array $document, array $options = []): array|object
    {
        // TODO: Implement insertOne() method.

    }//end insertOne()
}//end class
