<?php

namespace CommonGateway\CoreBundle\Service\Cache;

interface CollectionInterface
{
    public function aggregate(array $pipeline, array $options = []): \Iterator;

    public function count(array $filter = [], array $options = []): int;

    public function createIndex(array|object $key, array $options = []): array|false;

    public function createSearchIndex(array|object $definition, array $options = []): string;

    public function find(array $filter = [], array $options =[]): \Iterator;

    public function findOne(array $filter = [], array $options = []): \Iterator;

    public function findOneAndDelete(array $filter = [], array $options = []): array|null|object;
    
    public function findOneAndReplace(array|object $filter, array|object $replacement, array $options = []): array|null|object;
    
    public function insertOne(array|object $document, array $options = []): array|object;

}
