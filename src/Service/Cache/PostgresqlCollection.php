<?php

namespace CommonGateway\CoreBundle\Service\Cache;

class PostgresqlCollection implements CollectionInterface
{

    /**
     * @inheritDoc
     */
    public function aggregate(array $pipeline, array $options = []): \Iterator
    {
        // TODO: Implement aggregate() method.
    }

    /**
     * @inheritDoc
     */
    public function count(array $filter = [], array $options = []): int
    {
        // TODO: Implement count() method.
    }

    /**
     * @inheritDoc
     */
    public function createIndex(object|array $key, array $options = []): string
    {
        // TODO: Implement createIndex() method.
    }

    /**
     * @inheritDoc
     */
    public function createSearchIndex(object|array $definition, array $options = []): string
    {
        // TODO: Implement createSearchIndex() method.
    }

    /**
     * @inheritDoc
     */
    public function find(array $filter = [], array $options = []): \Iterator
    {
        // TODO: Implement find() method.
    }

    /**
     * @inheritDoc
     */
    public function findOne(array $filter = [], array $options = []): array|null|object
    {
        // TODO: Implement findOne() method.
    }

    /**
     * @inheritDoc
     */
    public function findOneAndDelete(array $filter = [], array $options = []): array|null|object
    {
        // TODO: Implement findOneAndDelete() method.
    }

    /**
     * @inheritDoc
     */
    public function findOneAndReplace(object|array $filter, object|array $replacement, array $options = []): array|null|object
    {
        // TODO: Implement findOneAndReplace() method.
    }
}