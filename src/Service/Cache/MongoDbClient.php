<?php

namespace CommonGateway\CoreBundle\Service\Cache;

use CommonGateway\CoreBundle\Service\Cache\ClientInterface;
use CommonGateway\CoreBundle\Service\ObjectEntityService;
use Doctrine\ORM\EntityManagerInterface;
use MongoDB\Client;

class MongoDbClient implements ClientInterface
{

    private Client $client;

    private array $databases = [];

    public function __construct(string $uri, private readonly EntityManagerInterface $entityManager, private readonly ObjectEntityService $objectEntityService, array $uriOptions = [], array $driverOptions = [])
    {
        $this->client = new Client(uri: $uri, uriOptions: $uriOptions, driverOptions: $driverOptions);

    }//end __construct()

    public function __get(string $databaseName): DatabaseInterface
    {
        if (isset($this->databases[$databaseName]) === true) {
            return $this->databases[$databaseName];
        }

        $this->databases[$databaseName] = $database = new MongoDbDatabase(
            database: $this->client->__get($databaseName),
            name: $databaseName,
            entityManager: $this->entityManager,
            objectEntityService: $this->objectEntityService
        );

        return $database;

    }//end __get()
}//end class
