<?php

namespace CommonGateway\CoreBundle\Service\Cache;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;

/**
 * Client for ElasticSearch data storages
 *
 * @Author Ruben van der Linde <ruben@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>, Robert Zondervan <robert@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category DataStore
 */
class ElasticSearchClient implements ClientInterface
{

    private Client $connection;

    private array $databases = [];

    /**
     * @param string $uri    The uri of the elastic database.
     * @param string $apiKey The apikey of the elastic database.
     */
    public function __construct(string $uri, string $apiKey)
    {
        $parsedUri = parse_url(url: $uri);

        $uri = "{$parsedUri['scheme']}://{$parsedUri['host']}". (isset($parsedUri['port']) ? ":{$parsedUri['port']}" : "");


        $explodedApiKey = explode(':', \Safe\base64_decode($apiKey));

        $this->connection = ClientBuilder::create()->setHosts([$uri])->setApiKey($explodedApiKey[0], $explodedApiKey[1])->build();

        if(isset($parsedUri['path']) === true) {
            $databaseName = ltrim(string: $parsedUri['path'], characters: '/');
            $this->databases['objects'] = new ElasticSearchDatabase($this, $databaseName);
        }

    }//end __construct()

    /**
     * @inheritDoc
     */
    public function __get(string $databaseName): DatabaseInterface
    {
        if (isset($this->databases[$databaseName]) === true) {
            return $this->databases[$databaseName];
        }

        $this->databases[$databaseName] = $database = new ElasticSearchDatabase($this, $databaseName);

        return $database;

    }//end __get()

    /**
     * Gets the elastic client.
     *
     * @return Client
     */
    public function getConnection(): Client
    {
        return $this->connection;

    }//end getConnection()
}//end class
