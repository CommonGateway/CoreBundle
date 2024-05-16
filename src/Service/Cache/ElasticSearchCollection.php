<?php

namespace CommonGateway\CoreBundle\Service\Cache;

class ElasticSearchCollection implements CollectionInterface
{

    private string $name;

    private DatabaseInterface $database;

    public function __construct(ElasticSearchDatabase $database, string $name)
    {
        $this->database = $database;
        $this->name     = $name;

    }//end __construct()

    public function aggregate(array $pipeline, array $options = []): \Iterator
    {
        $connection = $this->database->getClient()->getConnection();

//        $connection->
        // TODO: Implement aggregate() method.
    }//end aggregate()

    private function generateSearchBody(array $filter): array
    {

    }

    public function count(array $filter = [], array $options = []): int
    {
        $connection = $this->database->getClient()->getConnection();

        $body = $this->generateSearchBody($filter);

        $parameters = [
            'index' => $this->database->getName(),
            'body'  => $body,
        ];

        $connection->count($parameters);

        // TODO: Implement count() method.
    }//end count()

    public function createIndex(array|object $key, array $options = []): string
    {
        return 'index';
    }//end createIndex()

    public function createSearchIndex(array|object $definition, array $options = []): string
    {
        return 'searchIndex';
    }//end createSearchIndex()

    public function find(array $filter = [], array $options = []): \Iterator
    {
        $connection = $this->database->getClient()->getConnection();
        // TODO: Implement find() method.
    }//end find()

    public function findOne(array $filter = [], array $options = []): array|null|object
    {
        $connection = $this->database->getClient()->getConnection();

        return $connection->get(
            [
                'index' => $this->database->getName(),
                'id'    => $id,
            ]
        );

    }//end findOne()

    public function findOneAndDelete(array $filter = [], array $options = []): array|null|object
    {
        $connection = $this->database->getClient()->getConnection();

        $id = $filter['_id'];

        $parameters = [
            'index' => $this->database->getName(),
            'id'    => $id,
        ];

        return $connection->delete($parameters);

    }//end findOneAndDelete()

    public function findOneAndReplace(object|array $filter, object|array $replacement, array $options = []): array|null|object
    {
        $connection = $this->database->getClient()->getConnection();

        $id = $filter['_id'];

        $document = $connection->get(
            [
                'index' => $this->database->getName(),
                'id'    => $id,
            ]
        );

        if ($document !== null) {
            $parameters = [
                'index' => $this->database->getName(),
                'id'    => $id,
                'body'  => [
                    'doc' => (array) $replacement,
                ],
            ];

            $result = $connection->update($parameters);

            return $result;
        }

        $parameters = [
            'index' => $this->database->getName(),
            'id'    => $id,
            'body'  => (array) $replacement,
        ];

        $result = $connection->index($parameters);

        return $result;

    }//end findOneAndReplace()

    public function insertOne(array|object $document, array $options = []): array|object
    {
        // TODO: Implement insertOne() method.
    }//end insertOne()
}//end class
