<?php

namespace CommonGateway\CoreBundle\Service\Cache;

use Doctrine\Common\Collections\ArrayCollection;
use Elasticsearch\Common\Exceptions\Missing404Exception;

class ElasticSearchCollection implements CollectionInterface
{

    public const FILTER_OPERATORS = [
        'exact',
        'case_insensitive',
        'case_sensitive',
        'like',
        '>=',
        '>',
        '<=',
        '<',
        'ne',
        'after',
        'strictly_after',
        'before',
        'strictly_before',
        'regex',
        'int_compare',
        'bool_compare',
    ];

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

        // $connection->
        // TODO: Implement aggregate() method.
    }//end aggregate()

    private function buildIn(array $values, string $field)
    {
        $matches = [];

        foreach ($values as $value) {
            $matches[] = ['match' => [$field => $value]];
        }

        return $matches;

    }//end buildIn()

    private function buildComparison(string $key, mixed $value, string|array|null $operator = null): array
    {
        if ($operator === 'like') {
            $value = ".*$value.*";
        }

        if (in_array($operator, ['like', 'regex']) && preg_match("/^\/.+\/[a-z]*$/i", $value) !== false) {
            return ['regexp' => [$key => strtolower($value)]];
        } else if ($operator === '>=' || $operator === 'after') {
            return ['range' => [$key => ['gte' => $value]]];
        } else if ($operator === '<=' || $operator === 'before') {
            return ['range' => [$key => ['lte' => $value]]];
        } else if ($operator === '>' || $operator === 'strictly_after') {
            return ['range' => [$key => ['gt' => $value]]];
        } else if ($operator === '<' || $operator === 'strictly_before') {
            return ['range' => [$key => ['lt' => $value]]];
        }

        return ['match' => [$key => $value]];

    }//end buildComparison()

    private function buildMultiComparison(string $key, mixed $values, array $operators): array
    {
        $result = [];

        foreach ($operators as $operator) {
            $result = array_merge_recursive($this->buildComparison($key, $values[$operator], $operator), $result);
        }

        return $result;

    }//end buildMultiComparison()

    private function buildQuery(array $filter, bool $directReturn = false)
    {
        $query = [];

        foreach ($filter as $key => $value) {
            if ($key === '$and') {
                $query['bool']['must'] = $this->buildQuery(filter: $value, directReturn: true);
            } else if ($key === '$or') {
                $query['bool']['should'] = $this->buildQuery(filter: $value, directReturn: true);
            } else if ($key === '_search') {
                $query['query_string']['query'] = $value;
            } else if (is_array(value: $value) === true && array_is_list(array: $value) === true) {
                $query[] = $this->buildQuery(filter: $value, directReturn: $directReturn);
            } else if (is_array(value: $value) === true && isset($value['$in'])) {
                $query['should'] = $this->buildIn(values: $value['$in'], field: $key);
            } else if (is_array(value: $value) === true
                && count(value: $value) === 1
                && in_array(needle: array_key_first(array: $value), haystack: self::FILTER_OPERATORS)
            ) {
                $query[] = $this->buildComparison(
                    key: $key,
                    value: $value[array_key_first(array: $value)],
                    operator: array_key_first(array: $value)
                );
                if ($directReturn === true) {
                    return $query[0];
                }

                // return $query;
            } else if (is_array(value: $value) === true
                && array_diff(array_keys($value), self::FILTER_OPERATORS) === []
            ) {
                $query[] = $this->buildMultiComparison(
                    key: $key,
                    values: $value,
                    operators: array_keys($value)
                );
                if ($directReturn === true) {
                    return $query[0];
                }
            } else if (is_array(value: $value) === false && $value !== null) {
                $query[] = $this->buildComparison(key: $key, value: $value);

                if ($directReturn === true) {
                    return $query[0];
                }
            } else if ($value === null) {
                $query[] = ['bool' => ['must_not' => ['exists' => ['field' => $key]]]];
                if ($directReturn === true) {
                    return $query[0];
                }
            } else {
                $query[] = $this->buildQuery(filter: $value, directReturn: $directReturn);
            }//end if
        }//end foreach

        return $query;

    }//end buildQuery()

    private function handlePagination(array &$filters): array
    {
        if (isset($filters['_limit']) === true) {
            $limit = (int) $filters['_limit'];
        } else {
            $limit = 30;
        }

        if (isset($filters['_start']) === true || isset($filters['_offset']) === true) {
            $start = isset($filters['_start']) === true ? (int) $filters['_start'] : (int) $filters['_offset'];
        } else if (isset($filters['_page']) === true) {
            $start = (((int) $filters['_page'] - 1) * $limit);
        } else {
            $start = 0;
        }

        unset($filters['_limit'], $filters['_start'], $filters['_offset'], $filters['_page']);

        return [
            'size' => $limit,
            'from' => $start,
        ];

    }//end handlePagination()

    private function generateSearchBody(array $filter): array
    {
        $body = $this->handlePagination(filters: $filter);

        $query = $this->buildQuery(filter: $filter);

        foreach ($query as $key => $value) {
            if ($key === 'match' || $key === 'query_string') {
                $query['bool']['must'][][$key] = $value;
                unset($query[$key]);
            } else if ($key === 'must' || $key === 'should') {
                $query['bool'][$key] = $value;
                unset($query[$key]);
            } else if (is_array(value: $value) === true && is_int(value: $key)) {
                $query['bool']['must'][] = $value;
                unset($query[$key]);
            }
        }

        $body['query'] = $query;

        return $body;

    }//end generateSearchBody()

    public function count(array $filter = [], array $options = []): int
    {
        $connection = $this->database->getClient()->getConnection();

        $body = $this->generateSearchBody(filter: $filter);

        unset($body['size'], $body['from']);

        $parameters = [
            'index' => $this->database->getName(),
            'body'  => $body,
        ];

        $result = $connection->count(params: $parameters);

        return $result['count'];
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

    private function formatResults(array $hit): array
    {
        $source = $hit['_source'];

        unset($hit['_source']);
        $hit = array_merge($hit, $source);

        return $hit;

    }//end formatResults()

    public function find(array $filter = [], array $options = []): \Iterator
    {
        $connection = $this->database->getClient()->getConnection();

        $body = $this->generateSearchBody(filter: $filter);

        $parameters = [
            'index' => $this->database->getName(),
            'body'  => $body,
        ];

        $result = $connection->search(params: $parameters);

        $hits = array_map(callback: [$this, 'formatResults'], array: $result['hits']['hits']);

        $iterator = new ArrayIterator(array: $hits);

        return $iterator;

    }//end find()

    public function findOne(array $filter = [], array $options = []): array|null|object
    {
        $id = $filter['_id'];

        $connection = $this->database->getClient()->getConnection();

        return $connection->get(
            params: [
                'index' => $this->database->getName(),
                'id'    => $id,
            ]
        )['_source'];

    }//end findOne()

    public function findOneAndDelete(array $filter = [], array $options = []): array|null|object
    {
        $connection = $this->database->getClient()->getConnection();

        $id = $filter['_id'];

        $parameters = [
            'index' => $this->database->getName(),
            'id'    => $id,
        ];

        return $connection->delete(params: $parameters);

    }//end findOneAndDelete()

    public function findOneAndReplace(object|array $filter, object|array $replacement, array $options = []): array|null|object
    {
        $connection = $this->database->getClient()->getConnection();

        $id = $filter['_id'];

        try {
            $document = $connection->get(
                params: [
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

                $result = $connection->update(params: $parameters);

                return $result;
            }
        } catch (Missing404Exception $exception) {
            $parameters = [
                'index' => $this->database->getName(),
                'id'    => $id,
                'body'  => (array) $replacement,
            ];

            $result = $connection->index(params: $parameters);

            return $result;
        }//end try

    }//end findOneAndReplace()

    public function insertOne(array|object $document, array $options = []): array|object
    {
        // TODO: Implement insertOne() method.
    }//end insertOne()
}//end class
