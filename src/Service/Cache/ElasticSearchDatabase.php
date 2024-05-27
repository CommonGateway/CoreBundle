<?php

namespace CommonGateway\CoreBundle\Service\Cache;

/**
 * Database for ElasticSearch data storages
 *
 * @Author Ruben van der Linde <ruben@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>, Robert Zondervan <robert@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category DataStore
 */
class ElasticSearchDatabase implements DatabaseInterface
{

    private ElasticSearchClient $client;

    private string $name;

    private array $collections = [];

    public function __construct(ElasticSearchClient $client, string $name)
    {
        $this->client = $client;
        $this->name   = $name;

    }//end __construct()

    public function __get(string $collectionName): CollectionInterface
    {
        if (isset($this->collections[$collectionName]) === true) {
            return $this->collections[$collectionName];
        }

        $this->collections[$collectionName] = $collection = new ElasticSearchCollection($this, $collectionName);

        return $collection;

    }//end __get()

    public function getClient(): ElasticSearchClient
    {
        return $this->client;

    }//end getClient()

    public function getName(): string
    {
        return $this->name;

    }//end getName()
}//end class
