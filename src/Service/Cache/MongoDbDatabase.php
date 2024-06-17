<?php

namespace CommonGateway\CoreBundle\Service\Cache;

use CommonGateway\CoreBundle\Service\ObjectEntityService;
use Doctrine\ORM\EntityManagerInterface;
use MongoDB\Database;
use Psr\Log\LoggerInterface;

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

    /**
     * @var array collections known by the database.
     */
    private array $collections = [];

    /**
     * @param Database               $database            The MongoDB Database.
     * @param string                 $name                The name of the database.
     * @param EntityManagerInterface $entityManager       The entity manager.
     * @param ObjectEntityService    $objectEntityService The object entity service.
     * @param LoggerInterface        $cacheLogger              The logger.
     */
    public function __construct(
        private readonly Database $database,
        private readonly string $name,
        private readonly EntityManagerInterface $entityManager,
        private readonly ObjectEntityService $objectEntityService,
        private readonly LoggerInterface $cacheLogger
    ) {

    }//end __construct()

    /**
     * @inheritDoc
     */
    public function __get(string $collectionName): CollectionInterface
    {
        if (isset($this->collections[$collectionName]) === true) {
            return $this->collections[$collectionName];
        }

        $this->collections[$collectionName] = $collection = new MongoDbCollection(
            collection: $this->database->__get($collectionName),
            database: $this,
            name: $collectionName,
            entityManager: $this->entityManager,
            objectEntityService: $this->objectEntityService,
            cacheLogger: $this->cacheLogger
        );

        return $collection;

    }//end __get()

    /**
     * Gets the name of the database.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;

    }//end getName()
}//end class
