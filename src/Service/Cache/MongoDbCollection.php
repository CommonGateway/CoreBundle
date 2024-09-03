<?php

namespace CommonGateway\CoreBundle\Service\Cache;

use App\Entity\Entity;
use CommonGateway\CoreBundle\Service\Cache\CollectionInterface;
use CommonGateway\CoreBundle\Service\ObjectEntityService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
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
     * @param LoggerInterface        $cacheLogger         The logger.
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
     * Adds search filter to the query on MongoDB. Will use _search query if present in $filter query params.
     * _search query param supports filtering on specific properties with ?_search[property1,property2]=value.
     *
     * @param array $filter The filter array for mongoDB so far.
     *
     * @return void
     */
    private function handleSearch(array &$filter): void
    {
        if (isset($filter['_search']) === true && empty($filter['_search']) === false) {
            $search = $filter['_search'];
        }

        if (empty($search) === true) {
            return;
        }

        // Normal search on every property with type text (includes strings), like this: ?_search=value.
        if (is_string($search) === true) {
            $filter = $this->handleSearchString(filter: $filter, search:  $search);
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
     * Parses the filter array and creates the filter array
     *
     * @param array $filter The filters to parse
     *
     * @return void The result of the parse, contains an error on failure, contains null on success.
     *
     * @throws Exception
     */
    private function parseFilter(array &$filter): void
    {
        if (key_exists('_showDeleted', $filter) === false || $filter['_showDeleted'] === 'false') {
            $filter['_self.dateDeleted'] = 'IS NULL';
        }

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
            $filter['_showDeleted'],
            $filter['_enablePagination']
        );

        // 'normal' Filters (not starting with _ ).
        foreach ($filter as $key => &$value) {
            $this->handleFilter(key: $key, value: $value);
        }

    }//end parseFilter()

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
                if (strtolower($value['bool_compare']) === 'false') {
                    $value = false;
                } else {
                    $value = true;
                }

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
     * @param mixed $key
     * @param mixed $value
     *
     * @throws Exception
     *
     * @return void
     */
    private function handleFilter(mixed $key, mixed &$value): void
    {
        // Skip $and & $or (in case a _search query is used and already added to filter for example).
        if ($key === '$and' || $key === '$or') {
            return;
        }

        if (substr($key, 0, 1) == '_') {
            // @Todo: deal with filters starting with _ like: _dateCreated.
        }

        // Handle filters that expect $value to be an array.
        if ($this->handleFilterArray(value: $value) === true) {
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
     * @inheritDoc
     */
    public function aggregate(array $pipeline, array $options = []): \Iterator
    {
        if ($this->database->getName() !== 'objects' || isset($pipeline[0]['$match']) === false) {
            return $this->collection->aggregate($pipeline, $options);
        }

        $filter = $pipeline[0]['$match'];

        // Let's see if we need a search.
        $this->handleSearch(filter: $filter);

        $this->parseFilter(filter: $filter);

        $pipeline[0]['$match'] = $filter;
        return $this->collection->aggregate($pipeline, $options);

    }//end aggregate()

    /**
     * @inheritDoc
     */
    public function count(array $filter = [], array $options = []): int
    {
        if ($this->database->getName() !== 'objects') {
            return $this->collection->count($filter, $options);
        }

        // Let's see if we need a search.
        $this->handleSearch(filter: $filter);

        $this->parseFilter(filter: $filter);

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

        // Let's see if we need a search.
        $this->handleSearch(filter: $filter);

        $this->parseFilter(filter: $filter);

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
