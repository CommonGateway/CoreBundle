<?php

namespace CommonGateway\CoreBundle\Service\Cache;

use App\Entity\Entity;
use CommonGateway\CoreBundle\Service\Cache\CollectionInterface;
use CommonGateway\CoreBundle\Service\ObjectEntityService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use MongoDB\Collection;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * Collection for MongoDB data storages
 *
 * @Author Ruben van der Linde <ruben@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>, Robert Zondervan <robert@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category DataStore
 */
class MongoDbCollection implements CollectionInterface
{
    /**
     * @param Collection             $collection          The MongoDB Collection.
     * @param MongoDbDatabase        $database            The database above the collection.
     * @param string                 $name                The name of the collection.
     * @param EntityManagerInterface $entityManager       The entity manager.
     * @param ObjectEntityService    $objectEntityService The object entity service.
     * @param LoggerInterface        $cacheLogger              The logger.
     */
    public function __construct(
        private readonly Collection $collection,
        private readonly MongoDbDatabase $database,
        private readonly string $name,
        private readonly EntityManagerInterface $entityManager,
        private readonly ObjectEntityService $objectEntityService,
        private readonly LoggerInterface $cacheLogger
    ) {

    }//end __construct()

    /**
     * Uses given $search string to add a filter on all properties to the existing $filter array.
     * Will try to do a wildcard search using $regex on all attributes of the entities in $filter['_self.schema.id']['$in'].
     * Else uses the $text + $search mongoDB query in order to do a non wildcard search on all string type properties.
     *
     * @param array  $filter The filter array for mongoDB so far.
     * @param string $search The search string to search all properties with.
     *
     * @return array The updated filter array.
     */
    private function handleSearchString(array $filter, string $search): array
    {
        // Non wildcard version, just in case we do not have '_self.schema.id'
        if (isset($filter['_self.schema.id']['$in']) === false) {
            $filter['$text'] = ['$search' => $search];
            return $filter;
        }

        // Use regex in order to do wildcard search.
        $searchRegex = [
            '$regex'   => ".*$search.*",
            '$options' => 'im',
        ];

        // Add regex wildcard search for each attribute of each entity we are filtering on.
        $countEntities = 0;

        foreach ($filter['_self.schema.id']['$in'] as $entityId) {
            $entityObject = $this->entityManager->getRepository(Entity::class)->find($entityId);
            if ($entityObject === null) {
                $this->cacheLogger->error("Could not find an Entity with id = $entityId during handleSearch()");
                continue;
            }

            $countEntities = ($countEntities + 1);
            foreach ($entityObject->getAttributes() as $attribute) {
                $filter['$or'][][$attribute->getName()] = $searchRegex;
            }
        }

        // If we somehow did not find any entities we should just use non wildcard search instead of returning all objects without filtering.
        if ($countEntities === 0) {
            $filter['$text'] = ['$search' => $search];
        }

        return $filter;

    }//end handleSearchString()

    /**
     * Adds search filter to the query on MongoDB. Will use given $search string to search on entire object, unless
     * the _search query is present in $completeFilter query params, then we use that instead.
     * _search query param supports filtering on specific properties with ?_search[property1,property2]=value.
     *
     * @param array       $filter         The filter array for mongoDB so far.
     * @param array       $completeFilter All filters used with query params, will also contain properties like _order and _search.
     * @param string|null $search         A string to search with, or null.
     *
     * @return void
     */
    private function handleSearch(array &$filter, array $completeFilter)
    {
        if (isset($completeFilter['_search']) === true && empty($completeFilter['_search']) === false) {
            $search = $completeFilter['_search'];
        }

        if (empty($search) === true) {
            return;
        }

        // Normal search on every property with type text (includes strings), like this: ?_search=value.
        if (is_string($search) === true) {
            $filter = $this->handleSearchString($filter, $search);
        }
        // _search query with specific properties in the [method] like this: ?_search[property1,property2]=value.
        else if (is_array($search) === true) {
            $searchRegex = preg_replace('/([^A-Za-z0-9\s])/', '\\\\$1', $search[array_key_first($search)]);
            if (empty($searchRegex) === true) {
                return;
            }

            $searchRegex = [
                '$regex'   => ".*$searchRegex.*",
                '$options' => 'im',
            ];
            $properties  = explode(',', array_key_first($search));
            foreach ($properties as $property) {
                // todo: we might want to check if we are allowed to filter on this property? with $this->handleFilterCheck;
                $filter['$or'][][$property] = $searchRegex;
            }
        }

    }//end handleSearch()

    /**
     * Parses the filter array and creates the filter and completeFilter arrays
     *
     * @param array $filter         The filters to parse
     * @param array $completeFilter The complete filter (can be empty, will be updated)
     *
     * @return array|null The result of the parse, contains an error on failure, contains null on success.
     *
     * @throws Exception
     */
    private function parseFilter(array &$filter, array &$completeFilter): ?array
    {
        // Backwards compatibility.
        $this->queryBackwardsCompatibility($filter);

        // Make sure we also have all filters stored in $completeFilter before unsetting.
        $completeFilter = $filter;

        unset(
            $filter['_start'],
            $filter['_offset'],
            $filter['_limit'],
            $filter['_page'],
            $filter['_extend'],
            $filter['_search'],
            $filter['_order'],
            $filter['_fields'],
            $filter['_queries'],
            $filter['_showDeleted']
        );

        // 'normal' Filters (not starting with _ ).
        foreach ($filter as $key => &$value) {
            $this->handleFilter($key, $value);
        }

        return null;

    }//end parseFilter()

    /**
     * Make sure we still support the old query params. By translating them to the new ones with _.
     *
     * @param array $filter
     *
     * @return void
     */
    private function queryBackwardsCompatibility(array &$filter)
    {
        isset($filter['_limit']) === false && isset($filter['limit']) === true && $filter['_limit']    = $filter['limit'];
        isset($filter['_start']) === false && isset($filter['start']) === true && $filter['_start']    = $filter['start'];
        isset($filter['_offset']) === false && isset($filter['offset']) === true && $filter['_offset'] = $filter['offset'];
        isset($filter['_page']) === false && isset($filter['page']) === true && $filter['_page']       = $filter['page'];
        isset($filter['_extend']) === false && isset($filter['extend']) === true && $filter['_extend'] = $filter['extend'];
        isset($filter['_search']) === false && isset($filter['search']) === true && $filter['_search'] = $filter['search'];
        isset($filter['_order']) === false && isset($filter['order']) === true && $filter['_order']    = $filter['order'];
        isset($filter['_fields']) === false && isset($filter['fields']) === true && $filter['_fields'] = $filter['fields'];

        unset(
            $filter['start'],
            $filter['offset'],
            $filter['limit'],
            $filter['page'],
            $filter['extend'],
            $filter['search'],
            $filter['order'],
            $filter['fields']
        );

    }//end queryBackwardsCompatibility()

    /**
     * Handles a single filter used on a get collection api call. Specifically an filter where the value is an array.
     *
     * @param $value
     *
     * @throws Exception
     *
     * @return bool
     */
    private function handleFilterArray(&$value): bool
    {
        // Let's check for the methods like in
        if (is_array($value) === true) {
            // Type: int_compare.
            if (array_key_exists('int_compare', $value) === true && is_array($value['int_compare']) === true) {
                $value = array_map('intval', $value['int_compare']);
            } else if (array_key_exists('int_compare', $value) === true) {
                $value = (int) $value['int_compare'];

                return true;
            }

            // Type: bool_compare.
            if (array_key_exists('bool_compare', $value) === true && is_array($value['bool_compare']) === true) {
                $value = array_map('boolval', $value['bool_compare']);
            } else if (array_key_exists('bool_compare', $value) === true) {
                $value = (bool) $value['bool_compare'];

                return true;
            }

            // After, before, strictly_after,strictly_before.
            if (empty(array_intersect_key($value, array_flip(['after', 'before', 'strictly_after', 'strictly_before']))) === false) {
                $newValue = null;
                // Compare datetime.
                if (empty(array_intersect_key($value, array_flip(['after', 'strictly_after']))) === false) {
                    $after       = array_key_exists('strictly_after', $value) ? 'strictly_after' : 'after';
                    $compareDate = new DateTime($value[$after]);
                    $compareKey  = $after === 'strictly_after' ? '$gt' : '$gte';

                    // Todo: add in someway an option for comparing string datetime or mongoDB datetime.
                    // $newValue["$compareKey"] = new UTCDateTime($compareDate);
                    $newValue["$compareKey"] = "{$compareDate->format('c')}";
                }

                if (empty(array_intersect_key($value, array_flip(['before', 'strictly_before']))) === false) {
                    $before      = array_key_exists('strictly_before', $value) ? 'strictly_before' : 'before';
                    $compareDate = new DateTime($value[$before]);
                    $compareKey  = $before === 'strictly_before' ? '$lt' : '$lte';

                    // Todo: add in someway an option for comparing string datetime or mongoDB datetime.
                    // $newValue["$compareKey"] = new UTCDateTime($compareDate);
                    $newValue["$compareKey"] = "{$compareDate->format('c')}";
                }

                $value = $newValue;

                return true;
            }//end if

            // Type: like.
            if (array_key_exists('like', $value) === true && is_array($value['like']) === true) {
                // $value = array_map('like', $value['like']);
            } else if (array_key_exists('like', $value) === true) {
                $value = preg_replace('/([^A-Za-z0-9\s])/', '\\\\$1', $value['like']);
                $value = [
                    '$regex'   => ".*$value.*",
                    '$options' => 'im',
                ];

                return true;
            }

            // Type: regex.
            if (array_key_exists('regex', $value) === true && is_array($value['regex']) === true) {
                // $value = array_map('like', $value['like']); @todo.
            } else if (array_key_exists('regex', $value) === true) {
                $value = ['$regex' => $value['regex']];

                return true;
            }

            // Type: >= .
            if (array_key_exists('>=', $value) === true && is_array($value['>=']) === true) {
                // $value = array_map('like', $value['like']); @todo.
            } else if (array_key_exists('>=', $value) === true) {
                $value = ['$gte' => (int) $value['>=']];

                return true;
            }

            // Type: > .
            if (array_key_exists('>', $value) === true && is_array($value['>']) === true) {
                // $value = array_map('like', $value['like']); @todo.
            } else if (array_key_exists('>', $value) === true) {
                $value = ['$gt' => (int) $value['>']];

                return true;
            }

            // Type: <= .
            if (array_key_exists('<=', $value) === true && is_array($value['<=']) === true) {
                // $value = array_map('like', $value['like']); @todo.
            } else if (array_key_exists('<=', $value) === true) {
                $value = ['$lte' => (int) $value['<=']];

                return true;
            }

            // Type: < .
            if (array_key_exists('<', $value) === true && is_array($value['<']) === true) {
                // $value = array_map('like', $value['like']); @todo.
            } else if (array_key_exists('<', $value) === true) {
                $value = ['$lt' => (int) $value['<']];

                return true;
            }

            // Type: Exact .
            if (array_key_exists('exact', $value) === true && is_array($value['exact']) === true) {
                // $value = array_map('like', $value['like']); @todo.
            } else if (array_key_exists('exact', $value) === true) {
                $value = $value;

                return true;
            }

            // Type: case_insensitive.
            if (array_key_exists('case_insensitive', $value) === true && is_array($value['case_insensitive']) === true) {
                // $value = array_map('like', $value['like']); @todo.
            } else if (array_key_exists('case_insensitive', $value) === true) {
                $value = [
                    '$regex'   => $value['case_insensitive'],
                    '$options' => 'i',
                ];

                return true;
            }

            // case_sensitive.
            if (array_key_exists('case_sensitive', $value) === true && is_array($value['case_sensitive']) === true) {
                // $value = array_map('like', $value['like']); @todo.
            } else if (array_key_exists('case_sensitive', $value)) {
                $value = ['$regex' => $value['case_sensitive']];

                return true;
            }

            // not equals
            if (array_key_exists('ne', $value) === true) {
                $value = ['$ne' => $value['ne']];

                return true;
            }

            if (array_key_first($value) === '$elemMatch') {
                return true;
            }

            if (array_key_first($value) === '$in') {
                return true;
            }

            // Handle filter value = array (example: ?property=a,b,c) also works if the property we are filtering on is an array.
            $value = ['$in' => $value];

            return true;
        }//end if

        return false;

    }//end handleFilterArray()

    /**
     * Handles a single filter used on a get collection api call. This function makes sure special filters work correctly.
     *
     * @param $key
     * @param $value
     *
     * @throws Exception
     *
     * @return void
     */
    private function handleFilter($key, &$value)
    {
        if ($key === '$and') {
            return;
        }

        if (substr($key, 0, 1) == '_') {
            // @Todo: deal with filters starting with _ like: _dateCreated.
        }

        // Handle filters that expect $value to be an array.
        if ($this->handleFilterArray($value) === true) {
            return;
        }

        // If the value is a boolean we need a other format.
        if (is_bool($value) === true || is_int($value) === true) {
            // Set as key '$eq' with the value.
            $value = ['$eq' => $value];

            return;
        }

        if (str_contains($value, '%') === true) {
            $regex = str_replace('%', '', $value);
            $regex = preg_replace('/([^A-Za-z0-9\s])/', '\\\\$1', $regex);
            $value = ['$regex' => $regex];

            return;
        }

        if ($value === 'IS NOT NULL') {
            $value = ['$ne' => null];

            return;
        }

        if ($value === 'IS NULL' || $value === 'null') {
            $value = null;

            return;
        }

        // @Todo: exact match is default, make case insensitive optional:
        $value = preg_replace('/([^A-Za-z0-9\s])/', '\\\\$1', $value);
        $value = [
            '$regex'   => "^$value$",
            '$options' => 'im',
        ];

    }//end handleFilter()

    /**
     * Decides the pagination values.
     *
     * @param int   $limit   The resulting limit
     * @param int   $start   The resulting start value
     * @param array $filters The filters
     *
     * @return array
     */
    public function setPagination(&$limit, &$start, array $filters): array
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

        return $filters;

    }//end setPagination()

    /**
     * Adds pagination variables to an array with the results we found with searchObjects().
     *
     * @param array $filter
     * @param array $results
     * @param int   $total
     *
     * @return array the result with pagination.
     */
    public function handleResultPagination(array $filter, array $results, int $total = 0): array
    {
        $start = isset($filter['_start']) === true && is_numeric($filter['_start']) === true ? (int) $filter['_start'] : 0;
        $limit = isset($filter['_limit']) === true && is_numeric($filter['_limit']) === true ? (int) $filter['_limit'] : 30;
        $page  = isset($filter['_page']) === true && is_numeric($filter['_page']) === true ? (int) $filter['_page'] : 1;

        // Let's build the page & pagination
        if ($start > 1) {
            $offset = ($start - 1);
        } else {
            $offset = (($page - 1) * $limit);
        }

        $pages = ceil($total / $limit);

        return [
            'results' => $results,
            'count'   => count($results),
            'limit'   => $limit,
            'total'   => $total,
            'offset'  => $offset,
            'page'    => (floor($offset / $limit) + 1),
            'pages'   => $pages == 0 ? 1 : $pages,
        ];

    }//end handleResultPagination()

    private function addOwnerOrgFilter(array $filter): array
    {
        if (isset($filter['$and']) === true) {
            $andCount = (count($filter['$and']) - 1);
            if (isset($filter['$and'][$andCount]['$or'][0]['_self.owner.id']) === true) {
                return $filter;
            }
        }

        if (isset($filter['_self.owner.id']) === true) {
            return $filter;
        }

        $user = $this->objectEntityService->findCurrentUser();

        if ($user !== null && $user->getOrganization() !== null) {
            if (isset($filter['$or']) === true) {
                $filter['$and'][] = ['$or' => $filter['$or']];
                unset($filter['$or']);
            }

            $orFilter          = [];
            $orFilter['$or'][] = ['_self.owner.id' => $user->getId()->toString()];
            $orFilter['$or'][] = ['_self.organization.id' => $user->getOrganization()->getId()->toString()];
            $orFilter['$or'][] = ['_self.organization.id' => null];
            $filter['$and'][]  = ['$or' => $orFilter['$or']];
        } else if ($user !== null) {
            $filter['_self.owner.id'] = $user->getId()->toString();
        }

        return $filter;

    }//end addOwnerOrgFilter()

    /**
     * @inheritDoc
     */
    public function aggregate(array $pipeline, array $options = []): \Iterator
    {
        return $this->collection->aggregate($pipeline, $options);

    }//end aggregate()

    /**
     * @inheritDoc
     */
    public function count(array $filter = [], array $options = []): int
    {
        return $this->collection->count($filter, $options);

    }//end count()

    /**
     * @inheritDoc
     */
    public function createIndex(object|array $key, array $options = []): string
    {
        return $this->collection->createIndex($key, $options);

    }//end createIndex()

    /**
     * @inheritDoc
     */
    public function createSearchIndex(object|array $definition, array $options = []): string
    {
        return $this->collection->createSearchIndex($definition, $options);

    }//end createSearchIndex()

    /**
     * @inheritDoc
     */
    public function find(array $filter = [], array $options = []): \Iterator
    {
        if ($this->database->getName() !== 'objects') {
            return $this->collection->find($filter, $options);
        }

//        $completeFilter = [];
//        $filterParse    = $this->parseFilter($filter, $completeFilter);
//        if ($filterParse !== null) {
//            return $filterParse;
//        }

        // Let's see if we need a search
//        $this->handleSearch($filter, $completeFilter);

        // var_dump($filter);
        return $this->collection->find($filter, $options);

    }//end find()

    /**
     * @inheritDoc
     */
    public function findOne(array $filter = [], array $options = []): array|null|object
    {
        return $this->collection->findOne($filter, $options);

    }//end findOne()

    /**
     * @inheritDoc
     */
    public function findOneAndDelete(array $filter = [], array $options = []): array|null|object
    {
        return $this->collection->findOneAndDelete($filter, $options);

    }//end findOneAndDelete()

    /**
     * @inheritDoc
     */
    public function findOneAndReplace(object|array $filter, object|array $replacement, array $options = []): array|null|object
    {
        return $this->collection->findOneAndReplace($filter, $replacement, $options);

    }//end findOneAndReplace()
}//end class
