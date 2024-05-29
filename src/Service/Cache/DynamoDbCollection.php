<?php

namespace CommonGateway\CoreBundle\Service\Cache;

class DynamoDbCollection implements CollectionInterface
{
    public function __construct(private readonly DynamoDbDatabase $database, private readonly string $name)
    {

    }//end __construct()

    private function createInFilter(array $values, string $key, array &$expressionAttributeNames = [], array &$expressionAttributeValues = []): string
    {
        $expressionAttributeNames["#$key"] = $key;
        $value                             = $this->toDynamoDbArray($values);

        $expressionString = "#$key IN (";

        $i = 0;
        foreach ($values as $k => $value) {
            $expressionAttributeValues[":$key$i"] = $value;
            $expressionString                    .= ":$key$i";
            if (array_key_last($values) !== $k) {
                $expressionString .= ',';
                $i++;
            } else {
                $expressionString .= ')';
            }
        }

        return $expressionString;

    }//end createInFilter()

    private function convertFilters(array $filter, string $append = 'AND', array &$expressionAttributeNames = [], array &$expressionAttributeValues = []): string
    {
        $expressionString = '';

        foreach ($filter as $key => $value) {
            if ($key === '$or') {
                $expressionString .= '('.$this->convertFilters(
                    filter: $value,
                    append: 'OR',
                    expressionAttributeNames: $expressionAttributeNames,
                    expressionAttributeValues: $expressionAttributeValues
                ).')';
            } else if ($key === '$and') {
                $expressionString .= '('.$this->convertFilters(
                    filter: $value,
                    expressionAttributeNames: $expressionAttributeNames,
                    expressionAttributeValues: $expressionAttributeValues
                ).')';
            } else if (is_array($value) && isset($value['$in'])) {
                $expressionString .= $this->createInFilter(
                    values: $value['$in'],
                    key: $key,
                    expressionAttributeNames: $expressionAttributeNames,
                    expressionAttributeValues: $expressionAttributeValues
                );
            } else if (is_array($value)) {
                $expressionString .= '('.$this->convertFilters(
                    filter: $value,
                    append: $append,
                    expressionAttributeNames: $expressionAttributeNames,
                    expressionAttributeValues: $expressionAttributeValues
                ).')';
            } else {
            }//end if
        }//end foreach

    }//end convertFilters()

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
    public function createSearchIndex(object|array $definition, array $options = []): string
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

    /**
     * @param  array $array
     * @return array
     */
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

        return $object;

    }//end toDynamoDbArray()

    /**
     * @inheritDoc
     */
    public function findOneAndReplace(object|array $filter, object|array $replacement, array $options = []): array|null|object
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
