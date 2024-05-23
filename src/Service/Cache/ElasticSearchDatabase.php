<?php

namespace CommonGateway\CoreBundle\Service\Cache;

class ElasticSearchDatabase implements DatabaseInterface
{

    private ElasticSearchClient $client;

    private string $name;
    private array $collections = [];

    public function __construct(ElasticSearchClient $client, string $name)
    {
        $this->client = $client;
        $this->name   = $name;

    }//end __construct()

    public function __get(string $collectionName): CollectionInterface
    {
        if (isset($this->collections[$collectionName]) === true) {
            return $this->collections[$collectionName];
        }

        $this->collections[$collectionName] = $collection = new ElasticSearchCollection($this, $collectionName);

        return $collection;

    }//end __get()

    public function getClient(): ElasticSearchClient
    {
        return $this->client;

    }//end getClient()

    public function getName(): string
    {
        return $this->name;

    }//end getName()
}//end class
