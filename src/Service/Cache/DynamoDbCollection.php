<?php

namespace CommonGateway\CoreBundle\Service\Cache;

class DynamoDbCollection implements CollectionInterface
{
    public function __construct(private readonly DynamoDbDatabase $database, private readonly string $name)
    {

    }//end __construct()

    /**
     * @inheritDoc
     */
    public function aggregate(array $pipeline, array $options = []): \Iterator
    {
        // TODO: Implement aggregate() method.
    }//end aggregate()

    /**
     * @inheritDoc
     */
    public function count(array $filter = [], array $options = []): int
    {
        // TODO: Implement count() method.
    }//end count()

    /**
     * @inheritDoc
     */
    public function createIndex(object $key, array $options = []): string
    {
        // TODO: Implement createIndex() method.
    }//end createIndex()

    /**
     * @inheritDoc
     */
    public function createSearchIndex(object $definition, array $options = []): string
    {
        // TODO: Implement createSearchIndex() method.
    }//end createSearchIndex()

    /**
     * @inheritDoc
     */
    public function find(array $filter = [], array $options = []): \Iterator
    {
        // TODO: Implement find() method.
    }//end find()

    /**
     * @inheritDoc
     */
    public function findOne(array $filter = [], array $options = []): array|null|object
    {
        $result = $this->database->getClient()->getConnection()->getItem(
            [
                'TableName'      => $this->database->getName(),
                'ConsistentRead' => true,
                'Key'            => ['_id' => ['S' => $filter['_id']]],
            ]
        );

        return $result->toArray();

    }//end findOne()

    /**
     * @inheritDoc
     */
    public function findOneAndDelete(array $filter = [], array $options = []): array|null|object
    {
        $result = $this->database->getClient()->getConnection()->deleteItem(
            [
                'TableName' => $this->database->getName(),
                'Key'       => ['_id' => ['S' => $filter['_id']]],
            ]
        );

        return $result->toArray();

    }//end findOneAndDelete()

    private function toDynamoDbArray(array $array): array
    {
        $object = [];
        foreach ($array as $key => $value) {
            if (is_int(value: $value) === true || is_float(value: $value) === true) {
                $object[$key] = ['N' => $value];
            } else if (is_string(value: $value) === true) {
                $object[$key] = ['S' => $value];
            } else if (is_bool(value: $value) === true) {
                $object[$key] = ['BOOL' => $value];
            } else if (is_array(value: $value) === true && array_is_list(array: $value) === false) {
                $object[$key] = ['M' => $this->toDynamoDbArray($value)];
            } else if (is_array(value: $value) === true) {
                $object[$key] = ['L' => $this->toDynamoDbArray($value)];
            } else {
                $object[$key] = ['NULL' => true];
            }
        }

    }//end toDynamoDbArray()

    /**
     * @inheritDoc
     */
    public function findOneAndReplace(object $filter, object $replacement, array $options = []): array|null|object
    {
        $result = $this->database->getClient()->getConnection()->putItem(
            [
                'TableName' => $this->database->getName(),
                'Item'      => $replacement,
            ]
        );

        return $result->toArray();

    }//end findOneAndReplace()
}//end class
