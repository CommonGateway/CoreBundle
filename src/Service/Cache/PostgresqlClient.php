<?php

namespace CommonGateway\CoreBundle\Service\Cache;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractPostgreSQLDriver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;

class PostgresqlClient implements ClientInterface
{

    private array $databases = [];

    public function __construct(
        string $uri,
    ) {
        $dsnParser  = new DsnParser();
        $parameters = $dsnParser->parse(dsn: $uri);
        $connection = DriverManager::getConnection($parameters);

        $this->client = $connection;

    }//end __construct()

    /**
     * @inheritDoc
     */
    public function __get(string $databaseName): DatabaseInterface
    {
        if (isset($this->databases[$databaseName]) === true) {
            return $this->databases[$databaseName];
        }

        if (in_array(
            needle: strtolower($databaseName),
            haystack: $this->client->executeQuery(sql: 'SELECT tablename FROM pg_catalog.pg_tables;')->fetchFirstColumn()
        ) === false
        ) {
            $this->client->executeQuery("CREATE TABLE ".strtolower($databaseName)." (_id uuid);");
        }


        $this->databases[$databaseName] = $database = new PostgresqlDatabase(name: $databaseName, client: $this->client);

        return $database;

    }//end __get()
}//end class
