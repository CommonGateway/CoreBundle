<?php

namespace CommonGateway\CoreBundle\Service\Cache;

use CommonGateway\CoreBundle\Service\ObjectEntityService;
use Doctrine\ORM\EntityManagerInterface;
use MongoDB\Database;

/**
 * Database for MongoDB data storages
 *
 * @Author Ruben van der Linde <ruben@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>, Robert Zondervan <robert@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category DataStore
 */
class MongoDbDatabase implements DatabaseInterface
{

    private Database $database;

    private string $name;

    private array $collections = [];

    public function __construct(Database $database, string $name, private readonly EntityManagerInterface $entityManager, private readonly ObjectEntityService $objectEntityService)
    {
        $this->database = $database;
        $this->name     = $name;

    }//end __construct()

    public function __get(string $collectionName): CollectionInterface
    {
        if (isset($this->collections[$collectionName]) === true) {
            return $this->collections[$collectionName];
        }

        $this->collections[$collectionName] = $collection = new MongoDbCollection($this->database->__get($collectionName), $this, $collectionName, $this->entityManager, $this->objectEntityService);

        return $collection;

    }//end __get()

    public function getName(): string
    {
        return $this->name;

    }//end getName()
}//end class
