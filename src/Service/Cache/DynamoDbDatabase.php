<?php

namespace CommonGateway\CoreBundle\Service\Cache;

class DynamoDbDatabase implements DatabaseInterface
{

    private DynamoDbClient $client;

    private string $name;

    private array $collections = [];

    public function __construct(DynamoDbClient $client, string $name)
    {
        $this->client = $client;
        $this->name = $name;

    }//end __construct()

    /**
     * @inheritDoc
     */

    public function __get(string $collectionName): CollectionInterface
    {
        if (isset($this->collections[$collectionName]) === true) {
            return $this->collections[$collectionName];
        }

        $this->collections[$collectionName] = $collection = new DynamoDbCollection($this, $collectionName);

        return $collection;

    }//end __get()
    
    public function getClient(): DynamoDbClient
    {
        return $this->client;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
}
