<?php

namespace CommonGateway\CoreBundle\Service\Cache;

use CommonGateway\CoreBundle\Service\Cache\ClientInterface;
use MongoDB\Client;

class MongoDbClient implements ClientInterface
{
    private Client $client;

    private array $databases = [];

    public function __construct(?string $uri = null, array $uriOptions = [], array $driverOptions =[]) {
        $this->client = new Client(uri: $uri, uriOptions: $uriOptions, driverOptions: $driverOptions);
    }

    public function __get(string $databaseName): DatabaseInterface
    {
        if (isset($this->databases[$databaseName]) === true) {
            return $this->databases[$databaseName];
        }

        $this->databases[$databaseName] = $database = new MongoDbDatabase($this->client->__get($databaseName));

        return $database;
    }
}