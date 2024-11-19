<?php

namespace CommonGateway\CoreBundle\Service\Cache;

use Doctrine\DBAL\Connection;

class PostgresqlDatabase implements DatabaseInterface
{

    private array $collections = [];

    public function __construct(
        private readonly string $name,
        private readonly Connection $client,
    ) {

    }//end __construct()

    /**
     * @inheritDoc
     */
    public function __get(string $collectionName): CollectionInterface
    {
        if (in_array(needle: $collectionName, haystack: $this->collections) === true) {
            return $this->collections[$collectionName];
        }

        if (in_array(
            needle: strtolower($collectionName),
            haystack: $this->client->executeQuery("select column_name from information_schema.columns where table_name = '{$this->name}'")->fetchFirstColumn()
        ) === false
        ) {
            $this->client->executeQuery("ALTER TABLE {$this->name} ADD {$collectionName} json");
        }

        $this->collections[$collectionName] = $collection = new PostgresqlCollection(name: $collectionName, database: $this);

        return $collection;

    }//end __get()

    public function getClient(): Connection
    {
        return $this->client;

    }//end getClient()

    public function getName():string
    {
        return $this->name;

    }//end getName()
}//end class
