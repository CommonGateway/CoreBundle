<?php

namespace CommonGateway\CoreBundle\Service\Cache;

use CommonGateway\CoreBundle\Service\Cache\CollectionInterface;
use Dompdf\Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

class S3Collection implements CollectionInterface
{

    private LoggerInterface $logger;

    public function __construct(private readonly S3Database $database, private readonly string $name, LoggerInterface $cacheLogger)
    {
        $this->logger = $cacheLogger;

    }//end __construct()

    public function aggregate(array $pipeline, array $options = []): \Iterator
    {
        $this->logger->error(message: 'S3 does not support aggegations, this request is not implemented in S3 setups');
        throw new HttpException(statusCode: 501, message: 'S3 does not support aggegations, this request is not implemented in S3 setups');

    }//end aggregate()

    public function count(array $filter = [], array $options = []): int
    {
        // TODO: Implement count() method.

    }//end count()

    public function createIndex(object|array $key, array $options = []): string
    {
        $this->logger->warning(message: 'S3 does not support indices, no index will be created');
        return '';

    }//end createIndex()

    public function createSearchIndex(object|array $definition, array $options = []): string
    {
        $this->logger->warning(message: 'S3 does not support indices, no index will be created');
        return '';

    }//end createSearchIndex()

    public function find(array $filter = [], array $options = []): \Iterator
    {
        // TODO: Implement find() method.

    }//end find()

    public function findOne(array $filter = [], array $options = []): array|null|object
    {
        return \Safe\json_decode(
            json: $this->database->getClient()->getConnection()->getObject(
                [
                    'Bucket' => $this->database->getClient()->getBucket(),
                    'Key'    => $filter['_id'],
                ]
            )->get('Body'),
            assoc: true
        );

    }//end findOne()

    public function findOneAndDelete(array $filter = [], array $options = []): array|null|object
    {
        try {
            return \Safe\json_decode(
                json: $this->database->getClient()->getConnection()->deleteObject(
                    [
                        'Bucket' => $this->database->getClient()->getBucket(),
                        'Delete' => [
                            'Objects' => [
                                [
                                    'Key' => $filter['_id'],
                                ],
                            ],
                        ],
                    ]
                )->get('Body'),
                assoc: true
            );
        } catch (Exception $exception) {
            return null;
        }

        // TODO: Implement findOneAndDelete() method.

    }//end findOneAndDelete()

    public function findOneAndReplace(object|array $filter, object|array $replacement, array $options = []): array|null|object
    {

        try {
            return \Safe\json_decode(
                json: $this->database->getClient()->getConnection()->putObject(
                    args: [
                        'Bucket'   => $this->database->getClient()->getBucket(),
                        'Key'      => $filter['_id'],
                        'Body'     => \Safe\json_encode(value: $replacement),
                        'Metadata' => [
                            'database'   => $this->database->getName(),
                            'collection' => $this->name,
                        ],
                    ]
                ),
                assoc: true
            );
        } catch (Exception $exception) {
            return null;
        }

    }//end findOneAndReplace()

    public function insertOne(object|array $document, array $options = []): array|object
    {

    }//end insertOne()
}//end class
