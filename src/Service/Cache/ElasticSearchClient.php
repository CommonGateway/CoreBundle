<?php

namespace CommonGateway\CoreBundle\Service\Cache;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;

class ElasticSearchClient implements ClientInterface
{

    private Client $connection;

    private array $databases = [];

    public function __construct(string $uri, string $apiKey)
    {
        $this->connection = ClientBuilder::create()->setHosts([$uri])->setApiKey($apiKey)->build();

    }//end __construct()

    public function __get(string $databaseName): DatabaseInterface
    {
        if (isset($this->databases[$databaseName]) === true) {
            return $this->databases[$databaseName];
        }

        $this->databases[$databaseName] = $database = new ElasticSearchDatabase($this, $databaseName);

        return $database;

    }//end __get()

    public function getConnection(): Client
    {
        return $this->connection;

    }//end getConnection()
}//end class
