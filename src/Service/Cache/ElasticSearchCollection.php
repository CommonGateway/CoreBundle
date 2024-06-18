<?php

namespace CommonGateway\CoreBundle\Service\Cache;

/*
 * Collection for ElasticSearch data storages
 *
 * @Author Ruben van der Linde <ruben@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>, Robert Zondervan <robert@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category DataStore
 */
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

    /**
     * @param ElasticSearchDatabase $database The elasticSearch Database used.
     * @param string                $name     The name of the collection.
     */
    public function __construct(private readonly ElasticSearchDatabase $database, private readonly string $name)
    {

    }//end __construct()

    /**
     * Rename the items in an aggregation bucket according to the response standard for aggregations.
     *
     * @param array $bucketItem The item to rewrite
     *
     * @return array The rewritten array.
     */
    private function renameBucketItems(array $bucketItem): array
    {
        return [
            '_id'   => $bucketItem['key'],
            'count' => $bucketItem['doc_count'],
        ];

    }//end renameBucketItems()

    /**
     * Map aggregation results to comply to the existing standard for aggregation results.
     *
     * @param array $result The result to map.
     *
     * @return array The mapped result.
     */
    private function mapAggregationResults(array $result): array
    {
        $buckets = $result['buckets'];

        $result = array_map([$this, 'renameBucketItems'], $buckets);

        return $result;

    }//end mapAggregationResults()

    /**
     * @inheritDoc
     */
    public function aggregate(array $pipeline, array $options = []): \Iterator
    {
        $connection = $this->database->getClient()->getConnection();

        $body = $this->generateSearchBody($pipeline[0]);

        foreach ($pipeline[1] as $query) {
            $body['runtime_mappings'][$query] = ['type' => 'keyword'];
            $body['aggs'][$query]             = ['terms' => ['field' => $query]];
        }

        $body['size'] = 0;

        $parameters = [
            'index' => $this->database->getName(),
            'body'  => $body,
        ];

        $result = $connection->search(params: $parameters);

        $result = array_map([$this, 'mapAggregationResults'], $result['aggregations']);

        return new ArrayIterator($result);

    }//end aggregate()

    /**
     * Build filters that are analogue to the MongoDB $in filters.
     *
     * @param array  $values The values to put in the comparison.
     * @param string $field  The field that should have one of the values.
     *
     * @return array The resulting filter.
     */
    private function buildIn(array $values, string $field)
    {
        $matches = [];

        foreach ($values as $value) {
            $matches[] = ['match' => [$field => $value]];
        }

        return $matches;

    }//end buildIn()

    /**
     * Build the actual comparison (match, regex, range) for a filter.
     *
     * @param string            $key      The field for which the filter holds.
     * @param mixed             $value    The value that should be tested.
     * @param string|array|null $operator The operator that should be used to test the value.
     *
     * @return array[]|\array[][]|mixed[][]|\string[][]
     */
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

    /**
     * Build comparisons for an array.
     *
     * @param  string $key       The field of which the values should be tested
     * @param  mixed  $values    The values to test against.
     * @param  array  $operators The operators that should be used to test the values.
     * @return array
     */
    private function buildMultiComparison(string $key, mixed $values, array $operators): array
    {
        $result = [];

        foreach ($operators as $operator) {
            $result = array_merge_recursive($this->buildComparison($key, $values[$operator], $operator), $result);
        }

        return $result;

    }//end buildMultiComparison()

    /**
     * Build query for given filter.
     *
     * @param array $filter       The filter given.
     * @param bool  $directReturn Whether the function should return immediately after building the filter (instead of adding it to an array).
     *
     * @return array|array[]|\array[][]|mixed|\mixed[][]|\string[][] The resulting query.
     */
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

    /**
     * Sets the correct sort-parameters by reading _order filters.
     *
     * @param  array $filters The given filter array for the request
     * @param  array $body    The request body before adding sorts.
     * @return array The request body after adding sorts.
     */
    private function handleOrder(array &$filters, array $body): array
    {
        if (isset($filters['_order']) === false) {
            return $body;
        }

        $order = $filters['_order'];
        unset($filters['_order']);

        foreach ($order as $key => $value) {
            $body['sort'][$key] = ['order' => $value];
        }

        return $body;

    }//end handleOrder()

    /**
     * Handle pagination for search results.
     *
     * @param array $filters The raw filter array.
     *
     * @return array The processed pagination parameters.
     */
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

    /**
     * Generates a search body for given filter.
     *
     * @param  array $filter The filter to generate the search body with.
     * @return array The resulting search body.
     */
    private function generateSearchBody(array $filter): array
    {
        $body = $this->handlePagination(filters: $filter);
        $body = $this->handleOrder(filters: $filter, body: $body);

        $query = $this->buildQuery(filter: $filter);

        foreach ($query as $key => $value) {
            if ($key === 'match' || $key === 'query_string') {
                $query['bool']['must'][][$key] = $value;
                unset($query[$key]);
            } else if ($key === 'must' || $key === 'should') {
                $query['bool']['must'][0]['bool']['must'][0]['bool'][$key] = $value;
                unset($query[$key]);
            } else if (is_array(value: $value) === true && is_int(value: $key)) {
                $query['bool']['must'][] = $value;
                unset($query[$key]);
            }
        }

        $body['query'] = $query;

        return $body;

    }//end generateSearchBody()

    /**
     * @inheritDoc
     */
    public function count(array $filter = [], array $options = []): int
    {
        $connection = $this->database->getClient()->getConnection();

        $body = $this->generateSearchBody(filter: $filter);

        unset($body['size'], $body['from'], $body['sort']);

        $parameters = [
            'index' => $this->database->getName(),
            'body'  => $body,
        ];

        $result = $connection->count(params: $parameters);

        return $result['count'];

    }//end count()

    /**
     * @inheritDoc
     */
    public function createIndex(array|object $key, array $options = []): string
    {
        return 'index';

    }//end createIndex()

    /**
     * @inheritDoc
     */
    public function createSearchIndex(array|object $definition, array $options = []): string
    {
        return 'searchIndex';

    }//end createSearchIndex()

    /**
     * Formats results to existing response standard.
     *
     * @param array $hit The hit to format.
     *
     * @return array The reformatted hit.
     */
    private function formatResults(array $hit): array
    {
        $source = $hit['_source'];

        unset($hit['_source']);
        $hit = array_merge($hit, $source);

        return $hit;

    }//end formatResults()

    /**
     * @inheritDoc
     */
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

    /**
     * @inheritDoc
     */
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

    /**
     * @inheritDoc
     */
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

    /**
     * @inheritDoc
     */
    public function findOneAndReplace(object|array $filter, object|array $replacement, array $options = []): array|null|object
    {
        $connection = $this->database->getClient()->getConnection();

        $id = $filter['_id'];

        $replacement = array_merge(
            (array) $replacement,
            [
                'doctype'          => 'OpenWOO',
                'title'            => $replacement['titel'] ?? null,
                'excerpt'          => $replacement['samenvatting'] ?? null,
                'date'             => $replacement['publicatiedatum'] ?? null,
                'link'             => $replacement['portalUrl'] ?? null,
                'content_filtered' => $replacement['beschrijving'] ?? null,
            ]
        );

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
                    'body'  => ['doc' => $replacement],
                ];

                $result = $connection->update(params: $parameters);
            } else {
                $result = [];
            }
        } catch (Missing404Exception $exception) {
            $parameters = [
                'index' => $this->database->getName(),
                'id'    => $id,
                'body'  => $replacement,
            ];

            $result = $connection->index(params: $parameters);
        }//end try

        return $result;

    }//end findOneAndReplace()
}//end class
