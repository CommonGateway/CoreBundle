<?php

namespace CommonGateway\CoreBundle\Service\Cache;

use CommonGateway\CoreBundle\Service\Cache\ClientInterface;
use Aws\Credentials\Credentials;

class S3Client implements ClientInterface
{

    private \Aws\S3\S3Client $client;

    private array $databases = [];

    public function __construct(array $credentials, private readonly string $bucket)
    {
        $this->client = new \Aws\S3\S3Client(['credentials' => new Credentials($credentials['key'], $credentials['secret'], $credentials['token'])]);

    }//end __construct()

    public function __get(string $databaseName): DatabaseInterface
    {
        if (isset($this->databases[$databaseName]) === false) {
            $this->databases[$databaseName] = $database = new S3Database($this, $databaseName);

            return $database;
        }

        return $this->databases[$databaseName];

    }//end __get()

    public function getConnection(): \Aws\S3\S3Client
    {
        return $this->client;

    }//end getConnection()

    public function getBucket(): string
    {
        return $this->bucket;

    }//end getBucket()
}//end class
