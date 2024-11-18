<?php

namespace CommonGateway\CoreBundle\Service\Cache;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

class PostgresqlCollection implements CollectionInterface
{
    public function __construct(
        private readonly string $name,
        private readonly PostgresqlDatabase $database
    ) {

    }//end __construct()

    private function getExpressions(QueryBuilder $queryBuilder, array $filters, ?string $filterEncoded = ''): array
    {
        $expressions = [];
        foreach ($filters as $filter => $value) {
            switch ($filter) {
            case '$or':
                $subExpressions = [];
                foreach ($value as $v) {
                    $subExpressions = array_merge($subExpressions, $this->getExpressions($queryBuilder, $v));
                }

                $expressions[] = $queryBuilder->expr()->or(...$subExpressions);
                break;
            case '$and':
                $subExpressions = [];
                foreach ($value as $v) {
                    $subExpressions = array_merge($expressions, $this->getExpressions($queryBuilder, $v));
                }

                $expressions[] = $queryBuilder->expr()->and(...$subExpressions);
                break;
            case 'before':
            case '<=':
                $expressions[] = $queryBuilder->expr()->lte("{$this->name} #>> '{".$filterEncoded."}'", "'$value'");
                break;
            case 'after':
            case '>=':
                $expressions[] = $queryBuilder->expr()->gte("{$this->name} #>> '{".$filterEncoded."}'", "'$value'");
                break;
            case 'strictly_before':
            case '<':
                $expressions[] = $queryBuilder->expr()->lt("{$this->name} #>> '{".$filterEncoded."}'", "'$value'");
                break;
            case 'strictly_after':
            case '>':
                $expressions[] = $queryBuilder->expr()->gt("{$this->name} #>> '{".$filterEncoded."}'", "'$value'");
                break;
            case 'ne':
                $expressions[] = $queryBuilder->expr()->neq("{$this->name} #>> '{".$filterEncoded."}'", "'$value'");
                break;
            case 'like':
            case 'regex':
                $expressions[] = $queryBuilder->expr()->like("{$this->name} #>> '{".$filterEncoded."}'", "'$value'");
                break;
            case '$in':
                $expressions[] = '\'["'.implode(separator: '","', array: $value)."\"]'::jsonb @> ({$this->name} #> '{".$filterEncoded."}')::jsonb";
                break;
            default:
                $filterEncoded = str_replace(search: '.', replace: ',', subject: $filter);
                if (is_array($value) && in_array('$in', array_keys($value))) {
                    $expressions[] = '\'["'.implode(separator: '","', array: $value['$in'])."\"]'::jsonb <@ ({$this->name} #>> '{".$filterEncoded."}')::jsonb";
                    break;
                }

                $expressions[] = $queryBuilder->expr()->eq("{$this->name} #>> '{".$filterEncoded."}'", "'$value'");
                break;
            }//end switch
        }//end foreach

        return $expressions;

    }//end getExpressions()

    private function addOrder(QueryBuilder $queryBuilder, array $values): QueryBuilder
    {
        foreach ($values as $key => $direction) {
            $filterEncoded = str_replace(search: '.', replace: ',', subject: $key);
            $queryBuilder->addOrderBy(sort: "{$this->name} #>> '{".$filterEncoded."}'", order: $direction);
        }

        return $queryBuilder;

    }//end addOrder()

    private function addFilters(QueryBuilder $queryBuilder, array $filters): QueryBuilder
    {
        if (key_exists(array: $filters, key: '_id')) {
            $queryBuilder->andWhere('_id = :id')
                ->setParameter(key: 'id', value: $filter['_id']);
            unset($filters['_id']);
        }

        foreach ($filters as $filter => $value) {
            switch ($filter) {
            case '$or':
                $expressions = [];
                foreach ($value as $v) {
                    $expressions = array_merge($expressions, $this->getExpressions($queryBuilder, $v));
                }

                $queryBuilder->andWhere($queryBuilder->expr()->or(...$expressions));
                break;
            case '$and':
                $expressions = [];
                foreach ($value as $v) {
                    $expressions = array_merge($expressions, $this->getExpressions($queryBuilder, $v));
                }

                $queryBuilder->andWhere($queryBuilder->expr()->and(...$expressions));
                break;
            case '$where':
                if (str_contains(needle: '.match', haystack: $value) === true) {
                    $array = explode(separator: '.match(', string: $value);

                    $check = trim(string: $array[0], characters: '"');
                    $path  = trim(string: $array[1], characters: '()');

                    $pathArray = explode(separator: '.', string: $path);
                    array_shift($pathArray);
                    $pathEncoded = implode(separator: ',', array: $pathArray);

                    $queryBuilder->andWhere("'{$check}' ~ ({$this->name} #>> '{".$pathEncoded."}')");
                }
                break;
            case '_order':
            case 'order':
                $queryBuilder = $this->addOrder($queryBuilder, $value);
                break;
            case '_limit':
            case 'limit':
                $queryBuilder->setMaxResults($value);
                break;
            case '_page':
            case 'page':
                $limit  = isset($filters['_limit']) === true ? (int) $filters['_limit'] : 30;
                $offset = ((($value - 1) * $limit) + ( isset($filters['_start']) === true ? $filters['_start'] : 0));
                $queryBuilder->setFirstResult($offset);
                break;
            case '_start':
                if (isset($filters['_page']) === false || $filters['_page'] === 1) {
                    $queryBuilder->setFirstResult($value);
                }
                break;
            default:
                $filterEncoded = str_replace(search: '.', replace: ',', subject: $filter);
                if (is_array($value) === true) {
                    $queryBuilder->andWhere(...$this->getExpressions($queryBuilder, $value, $filterEncoded));
                    break;
                }

                $queryBuilder->andWhere("{$this->name} #>> '{".$filterEncoded."}' = '$value'");
                break;
            }//end switch
        }//end foreach

        return $queryBuilder;

    }//end addFilters()

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
        unset(
            $filter['_order'],
            $filter['order'],
            $filter['_limit'],
            $filter['limit'],
            $filter['_page'],
            $filter['page'],
            $filter['_start'],
            $filter['_queries']
        );

        $queryBuilder = $this->database->getClient()->createQueryBuilder();
        $queryBuilder
            ->select("count({$this->name})")
            ->from($this->database->getName());

        $queryBuilder = $this->addFilters(queryBuilder: $queryBuilder, filters: $filter);

        $result = $queryBuilder->executeQuery();

        return $result->fetchOne();

    }//end count()

    /**
     * @inheritDoc
     */
    public function createIndex(object|array $key, array $options = []): string
    {
        return 'created';

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
        $queryBuilder = $this->database->getClient()->createQueryBuilder();
        $queryBuilder
            ->select($this->name)
            ->from($this->database->getName())
            ->setMaxResults(30);

        $queryBuilder = $this->addFilters(queryBuilder: $queryBuilder, filters: $filter);

        $result = $queryBuilder->executeQuery();

        $objects = array_map(
            callback: function ($value) {
                return json_decode(json: $value[$this->name], associative: true);
            },
            array: $result->fetchAll()
        );

        return new ArrayIterator($objects);

    }//end find()

    /**
     * @inheritDoc
     */
    public function findOne(array $filter = [], array $options = []): array|null|object
    {
        $queryBuilder = $this->database->getClient()->createQueryBuilder();
        $queryBuilder
            ->select($this->name)
            ->from($this->database->getName());

        if (key_exists(array: $filter, key: '_id')) {
            $queryBuilder->where('_id = :id')
                ->setParameter(key: 'id', value: $filter['_id']);
        }

        $result = $queryBuilder->executeQuery();
        return json_decode(json: $result->fetchOne(), associative: true);

    }//end findOne()

    /**
     * @inheritDoc
     */
    public function findOneAndDelete(array $filter = [], array $options = []): array|null|object
    {
        $object = $this->findOne($filter);

        if (is_array($object) === true) {
            $this->database->getClient()->delete($this->database->getName(), $filter);
        }

        return $object;

    }//end findOneAndDelete()

    /**
     * @inheritDoc
     */
    public function findOneAndReplace(object|array $filter, object|array $replacement, array $options = []): array|null|object
    {
        if (isset($filter['_id']) === true) {
            $replacement['_id'] = $filter['_id'];
        }

        if ($this->findOne($filter) === null) {
            $this->database->getClient()->insert(
                table: $this->database->getName(),
                data: [
                    '_id'       => $filter['_id'],
                    $this->name => json_encode($replacement),
                ]
            );
        }

        $this->database->getClient()->update(
            table: $this->database->getName(),
            data: [
                '_id'       => $filter['_id'],
                $this->name => json_encode($replacement),
            ],
            criteria: ['_id' => $filter['_id']]
        );

        return $this->findOne($filter);

    }//end findOneAndReplace()
}//end class
