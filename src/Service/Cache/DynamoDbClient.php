<?php

namespace CommonGateway\CoreBundle\Service\Cache;

use App\Entity\Database;
use Elasticsearch\Client;

class DynamoDbClient implements ClientInterface
{

    private \Aws\DynamoDb\DynamoDbClient $connection;
    private array $databases = [];

    public function __construct(Database $database)
    {
        $explodedUri = explode(':', $database->getUri());
        $key         = $explodedUri[0];
        $region      = $explodedUri[1];

        $dynamoDbConnection = new \Aws\DynamoDb\DynamoDbClient([[
            'key'    => $key,
            'secret' => $database->getAuth(),
            'region' => $region,
        ]]);

        $this->connection = $dynamoDbConnection;
    }


    public function __get(string $databaseName): DatabaseInterface
    {
        if (isset($this->databases[$databaseName]) === true) {
            return $this->databases[$databaseName];
        }

        $tables = $this->connection->listTables()['TableNames'];

        if(in_array(needle: $databaseName, array: $tables) === false) {
            $this->connection->createTable([
                'TableName'            => $databaseName,
                'AttributeDefinitions' => [
                    [
                        'AttributeName' => '_id',
                        'AttributeType' => 'S'
                    ]
                ],
                'KeySchema'             => [
                    [
                        'AttributeName' => '_id',
                        'KeyType'       => 'HASH'
                    ]
                ]
            ]);
        }

        $this->databases[$databaseName] = $database = new DynamoDbDatabase($this, $databaseName);

        return $database;

    }//end __get()

    public function getConnection(): \Aws\DynamoDb\DynamoDbClient
    {
        return $this->connection;

    }//end getConnection()

}
