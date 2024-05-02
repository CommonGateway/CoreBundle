<?php

namespace CommonGateway\CoreBundle\Service\Cache;

class MongoDbDatabase implements DatabaseInterface
{

    private Database $database;

    private array $collections = [];

    public function __construct(Database $database)
    {
        $this->database = $database;

    }//end __construct()

    public function __get(string $collectionName): CollectionInterface
    {
        if (isset($this->collections[$collectionName]) === true) {
            return $this->collections[$collectionName];
        }

        $this->collections[$collectionName] = $collection = new MongoDbCollection($this->database->__get($collectionName));

        return $collection;

    }//end __get()
}//end class
