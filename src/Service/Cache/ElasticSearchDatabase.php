<?php

namespace CommonGateway\CoreBundle\Service\Cache;

class ElasticSearchDatabase implements DatabaseInterface
{

    private ElasticSearchClient $client;
    private string $name;

    public function __construct(ElasticSearchClient $client, string $name)
    {
        $this->client = $client;
        $this->name = $name;
    }

    public function __get(string $collectionName): CollectionInterface
    {
        if (isset($this->collections[$collectionName]) === true) {
            return $this->collections[$collectionName];
        }
        $this->collections[$collectionName] = $collection = new ElasticSearchCollection($this, $databaseName);
        
        return $collection;
    }
    
    public function getClient(): ElasticSearchClient
    {
        return $this->client;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
}
