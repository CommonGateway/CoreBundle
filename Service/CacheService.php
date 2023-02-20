<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use MongoDB\Client;
use MongoDB\Collection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Service to call external sources.
 *
 * This service provides a guzzle wrapper to work with sources in the common gateway.
 *
 * @Author Wilco Louwerse, <wilco@conduction.nl>, Robert Zondervan <robert@conduction.nl>, Ruben van der Linde <ruben@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class CacheService
{
    /**
     * @var Client The MongoDB client.
     */
    private Client $client;

    /**
     * @var EntityManagerInterface The entity manager.
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var CacheInterface Symfony AdapterInterface as CacheInterface.
     */
    private CacheInterface $cache;

    /**
     * @var ParameterBagInterface The environmental values.
     */
    private ParameterBagInterface $parameters;

    /**
     * @var SerializerInterface The serializer.
     */
    private SerializerInterface $serializer;

    /**
     * @var LoggerInterface The logger interface.
     */
    private LoggerInterface $logger;

    /**
     * Setting up the base class with required services.
     *
     * @param EntityManagerInterface $entityManager The EntityManagerInterface.
     * @param CacheInterface         $cache         The CacheInterface.
     * @param ParameterBagInterface  $parameters    The ParameterBagInterface.
     * @param SerializerInterface    $serializer    The SerializerInterface.
     * @param LoggerInterface        $cacheLogger   The LoggerInterface.
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CacheInterface $cache,
        ParameterBagInterface $parameters,
        SerializerInterface $serializer,
        LoggerInterface $cacheLogger
    ) {
        $this->entityManager = $entityManager;
        $this->cache = $cache;
        $this->parameters = $parameters;
        $this->serializer = $serializer;
        $this->logger = $cacheLogger;
        if (empty($this->parameters->get('cache_url', false)) === false) {
            $this->client = new Client($this->parameters->get('cache_url'));
        }
    }//end __construct()

    /**
     * Remove non-existing items from the cache.
     *
     * @return void This function doesn't return anything.
     */
    public function cleanup()
    {
        $collection = $this->client->objects->json;
        $filter = [];
        $objects = $collection->find($filter)->toArray();

        $this->logger->info('Removed '.count($objects).' object from the cache');
    }//end cleanup()

    /**
     * Throws all available objects into the cache.
     *
     * @return void This function doesn't return anything.
     */
    public function warmup()
    {
        $this->logger->debut('Connecting to'.$this->parameters->get('cache_url'));

        // Backwards compatibility.
        if (isset($this->client) === false) {
            $this->logger->error('No cache client found, halting warmup');

            return Command::FAILURE;
        }

        $entitiesToCache = [
            'App:ObjectEntity',
            'App:Entity',
            'pp:Endpoint',
        ];

        // Stuffing the current data into the cache.
        foreach ($entitiesToCache as $type) {
            // Stuffing the current data into the cache.
            $objects = $this->entityManager->getRepository($type)->findAll();
            $this->logger->debut('Found '.count($objects).' objects\'s of type '.$type, ['type' => $type]);

            foreach ($objects as $object) {
                $this->setToCache($object);
            }

            // Create the index.
            $collection = $this->getCollection($type);
            $collection->createIndex(['$**' => 'text']);
            $this->logger->debut('Created an index for '.$type, ['type' => $type]);

            // Remove unwanted data.
            $objects = $collection->find()->toArray();

            foreach ($objects as $object) {
                $symfonyObject = $this->entityManager->find($type, $object['id']);
                if (empty($symfonyObject) === true) {
                    $collection->removeFromCache($object['id'], $type);
                }
            }
        }//end if

        $this->logger->info('Finished cache warmup');

        return Command::SUCCESS;
    }

    /**
     * Get endpoints from cache.
     *
     * @param array $filter The applied filter.
     *
     * @return Endpoint|null
     */
    public function getEndpoints(array $filter): ?Endpoint
    {
        // Backwards compatibility.
        if (isset($this->client) === false) {
            return null;
        }

        $collection = $this->client->endpoints->json;

        if (isset($filter['path']) === true) {
            $path = $filter['path'];
            $filter['$where'] = "\"$path\".match(this.pathRegex)";
            unset($filter['path']);
        }

        if (isset($filter['method']) === true) {
            $method = $filter['method'];
            $filter['$or'] = [['methods' => ['$in' => [$method]]], ['method' => $method]];
            unset($filter['method']);
        }

        $endpoints = $collection->find($filter)->toArray();

        if (count($endpoints) > 1) {
            throw new NonUniqueResultException();
        } elseif (count($endpoints) === 1) {
            return $this->entityManager->find('App\Entity\Endpoint', $endpoints[0]['id']);
        }

        return null;
    }

    /**
     * Sends an object to the cache.
     *
     * @param object $object The object to cache
     *
     * @return bool|array False is the object could not be cached true if the object could be caches
     */
    public function setToCache(object $object): mixed
    {
        $collection = $this->getCollection($object);

        // Check if the collection is found.
        if ($collection === false) {
            return false;
        }

        // Turn is to an array.
        $object = $object->toArray(['embedded' => true]);

        // Stuff it in the cache.
        if ($collection->findOneAndReplace(
            ['id' => $object['id']],
            $object,
            ['upsert' => true]
        ) === true
        ) {
            $this->logger->debug('Updated object '.$object['id'].' to cache');
        } else {
            $this->logger->debug('Wrote object '.$object['id'].' to cache');
        }

        return $collection->findOne(['id' => $object['id']]);
    }

    /**
     * Retrieves a single object from the cache.
     *
     * @param string $id   The id of the object to get
     * @param string $type The type of the object to get, defaults to App:ObjectEntity
     *
     * @return bool|array False if the object could no be retrieved or an array if the object could be retrieved
     */
    public function getItemFromCache(string $id, string $type = 'App:ObjectEntity'): mixed
    {
        $collection = $this->getCollection($type);

        // Check if the collection is found.
        if ($collection === false) {
            return false;
        }

        $object = $collection->findOne(['id' => $id]);
        if (empty($object) === false) {
            $this->logger->debug('Retrieved object from cache', ['object' => $id]);

            return $object;
        }

        $object = $this->entityManager->getRepository($type)->findOneBy(['id' => $id]);
        if ($object === null) {
            return $this->setToCache($object);
        }

        $this->logger->error('Object does not seem to exist', ['id' => $id, 'type' => $type]);

        return false;
    }

    /**
     * Retrieves a collection of objects from the.
     *
     * @param string|null $search   a string to search for within the given context.
     * @param array       $filter   an array of dot notation filters for which to search with.
     * @param array       $entities schemas to limit te search to.
     * @param string      $type     The type of the object, defautls to 'App:ObjectEntity'
     *
     * @throws Exception A basic Exception.
     *
     * @return array|null The objects found.
     */
    public function getCollectionFromCache(
        string $search = null,
        array $filter = [],
        array $entities = [],
        string $type = 'App:ObjectEntity'
    ): mixed {
        $collection = $this->getCollection($type);

        // Check if the collection is found.
        if ($collection === false) {
            return false;
        }

        // Backwards compatibility.
        $this->queryBackwardsCompatibility($filter);

        // Make sure we also have all filters stored in $completeFilter before unsetting.
        $completeFilter = $filter;
        unset(
            $filter['_start'], $filter['_offset'], $filter['_limit'], $filter['_page'],
            $filter['_extend'], $filter['_search'], $filter['_order'], $filter['_fields']);

        // 'normal' Filters (not starting with _ )
        foreach ($filter as $key => &$value) {
            $this->handleFilter($key, $value);
        }

        // Search for the correct entity / entities.
        // Todo: make this if into a function?
        if (empty($entities) === false) {
            foreach ($entities as $entity) {
                // Todo $filter['_self.schema.ref']='https://larping.nl/character.schema.json';.
                $filter['_self.schema.id']['$in'][] = $entity;
            }
        }

        // Lets see if we need a search.
        $this->handleSearch($filter, $completeFilter, $search);

        // Limit & Start for pagination.
        $this->setPagination($limit, $start, $completeFilter);

        // Order.
        $order = [];
        if (isset($completeFilter['_order']) === true) {
            $order = str_replace(['ASC', 'asc', 'DESC', 'desc'], [1, 1, -1, -1], $completeFilter['_order']);
        }

        if (empty($order) === false) {
            $order[array_keys($order)[0]] = (int) $order[array_keys($order)[0]];
        }

        // Find / Search.
        $results = $collection->find($filter, ['limit' => $limit, 'skip' => $start, 'sort' => $order])->toArray();
        $total = $collection->count($filter);

        // Make sure to add the pagination properties in response.
        return $this->handleResultPagination($completeFilter, $results, $total);
    }

    /**
     * Deletes a single object from the cache.
     *
     * @param string|object $target The target defined by the id as string or object to remove
     * @param string        $type   The type of the object, defautls to 'App:ObjectEntity'
     *
     * @return bool Wheter or not the target was deleted
     */
    public function removeFromCache($target, string $type = 'App:ObjectEntity'): bool
    {

        // What if the target is not a string.
        if (is_object($target) === true) {
            $target = $target->getId()->toString();
            $type = get_class($target);
        }

        $collection = $this->getCollection($type);

        // Check if the collection is found.
        if ($collection === false) {
            return false;
        }

        $this->logger->info("removing {$target} from cache", ['type' => $type, 'id' => $target]);
        $collection->findOneAndDelete(['id' => $target]);

        return true;
    }

    /**
     * Get the appropriate collection for an object.
     *
     * @param string|object $object The object or string reprecentation thereof
     *
     * @return Collection|bool The appropriate collection if found or false if otherwise
     */
    private function getCollection($object): mixed
    {
        // Backwards compatibility.
        if (isset($this->client) === false) {
            return false;
        }

        // What if the object is not a string.
        if (is_object($object) === true) {
            $object = get_class($object);
        }

        switch ($object) {
            case 'App:Entity':
                return $this->client->schemas->json;
            case 'App:ObjectEntity':
                return $this->client->objects->json;
            case 'App:Endpoint':
                return $this->client->endpoints->json;
            default:
                $this->logger->error('No cache collection found for '.$object);

                return false;
        }
    }

    /**
     * Make sure we still support the old query params. By translating them to the new ones starting with _.
     *
     * @param array $filter The applied filter.
     *
     * @return void This function doesn't return anything.
     */
    private function queryBackwardsCompatibility(array &$filter)
    {
        $oldParameters = ['start', 'offset', 'limit', 'page', 'extend', 'search', 'order', 'fields'];

        foreach ($oldParameters as $oldParameter) {
            // We don't need to do anything if the old parameters wasn't used or the new one is used.
            if (isset($filter[$oldParameter]) === false || isset($filter['_'.$oldParameter]) === true) {
                continue;
            }

            // But if we end up here we need to come into action.
            $filter['_'.$oldParameter] = $filter[$oldParameter];
            unset($filter[$oldParameter]);
        }
    }

    /**
     * Handles a single filter used on a get collection api call. This function makes sure special filters work correctly.
     *
     * @param string $key   The key.
     * @param mixed  $value The value.
     *
     * @throws Exception A basic Exception.
     *
     * @return void This function doesn't return anything.
     */
    private function handleFilter(string $key, &$value)
    {
        if (substr($key, 0, 1) === '_') {
            // Todo: deal with filters starting with _ like: _dateCreated.
        }

        // Handle filters that expect $value to be an array.
        if ($this->handleFilterArray($key, $value) === true) {
            return;
        }

        // Todo: this works, we should go to php 8.0 later.
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

        // Todo: exact match is default, make case insensitive optional.
        $value = preg_replace('/([^A-Za-z0-9\s])/', '\\\\$1', $value);
        $value = ['$regex' => "^$value$", '$options' => 'im'];
    }

    /**
     * Handles a single filter used on a get collection api call. Specifically an filter where the value is an array.
     *
     * This code breaks complexity guidelines, we have made a descign deciion to accept that for now on the bases of readabliity
     *
     * @param string       $key   The key.
     * @param string|array $value The value.
     *
     * @throws Exception A basic Exception.
     *
     * @return bool Returns true if $value is an array with the correct structure, and we can deal with the specific filter.
     *              Returns false if $value is not an array, or if $value is structured incorrectly for the filters we handle here.
     */
    private function handleFilterArray($key, &$value): bool
    {
        // Lets check for the methods like in.
        if (is_array($value) === true) {
            // Do int_compare.
            if (array_key_exists('int_compare', $value) === true && is_array($value['int_compare']) === true) {
                $value = array_map('intval', $value['int_compare']);
            } elseif (array_key_exists('int_compare', $value) === true) {
                $value = (int) $value['int_compare'];

                return true;
            }

            // Do bool_compare.
            if (array_key_exists('bool_compare', $value) === true && is_array($value['bool_compare']) === true) {
                $value = array_map('boolval', $value['bool_compare']);
            } elseif (array_key_exists('bool_compare', $value) === true) {
                $value = (bool) $value['bool_compare'];

                return true;
            }

            // After, before, strictly_after,strictly_before (after, before, strictly_after,strictly_before).
            if (empty(array_intersect_key($value, array_flip(['after', 'before', 'strictly_after', 'strictly_before']))) === false) {
                // Compare datetime.
                if (empty(array_intersect_key($value, array_flip(['after', 'strictly_after']))) === false) {
                    $after = array_key_exists('strictly_after', $value) ? 'strictly_after' : 'after';
                    $compareDate = new DateTime($value[$after]);
                    $compareKey = $after === 'strictly_after' ? '$gt' : '$gte';
                } else {
                    $before = array_key_exists('strictly_before', $value) ? 'strictly_before' : 'before';
                    $compareDate = new DateTime($value[$before]);
                    $compareKey = $before === 'strictly_before' ? '$lt' : '$lte';
                }

                $value = ["$compareKey" => "{$compareDate->format('c')}"];

                return true;
            }

            // Like (like).
            if (array_key_exists('like', $value) === true && is_array($value['like']) === true) {
                // Todo: $value = array_map('like', $value['like']);.
            } elseif (array_key_exists('like', $value) === true) {
                $value = preg_replace('/([^A-Za-z0-9\s])/', '\\\\$1', $value['like']);
                $value = ['$regex' => ".*$value.*", '$options' => 'im'];

                return true;
            }

            // Regex (regex).
            if (array_key_exists('regex', $value) === true && is_array($value['regex']) === true) {
                // Todo $value = array_map('like', $value['like']);.
            } elseif (array_key_exists('regex', $value) === true) {
                $value = ['$regex' => $value['regex']];

                return true;
            }

            // Greater then or equel (>=).
            if (array_key_exists('>=', $value) === true && is_array($value['>=']) === true) {
                // Todo $value = array_map('like', $value['like']);.
            } elseif (array_key_exists('>=', $value) === true) {
                $value = ['$gte' => (int) $value['>=']];

                return true;
            }

            // Greather then (>).
            if (array_key_exists('>', $value) === true && is_array($value['>']) === true) {
                // ToDo $value = array_map('like', $value['like']);.
            } elseif (array_key_exists('>', $value) === true) {
                $value = ['$gt' => (int) $value['>']];

                return true;
            }

            // Smaller than or equal  (<=).
            if (array_key_exists('<=', $value) === true && is_array($value['<=']) === true) {
                // Todo  $value = array_map('like', $value['like']);.
            } elseif (array_key_exists('<=', $value) === true) {
                $value = ['$lte ' => (int) $value['<=']];

                return true;
            }

            // Smaller then (<).
            if (array_key_exists('<', $value) === true && is_array($value['<']) === true) {
                // Todo $value = array_map('like', $value['like']);.
            } elseif (array_key_exists('<', $value) === true) {
                $value = ['$lt' => (int) $value['<']];

                return true;
            }

            // Exact (exact).
            if (array_key_exists('exact', $value) === true && is_array($value['exact']) === true) {
                // Todo $value = array_map('like', $value['like']);.
            } elseif (array_key_exists('exact', $value) === true) {
                $value = $value;

                return true;
            }

            // Case insensitive (case_insensitive).
            if (array_key_exists('case_insensitive', $value) === true && is_array($value['case_insensitive']) === true) {
                // Todo  $value = array_map('like', $value['like']);.
            } elseif (array_key_exists('case_insensitive', $value) === true) {
                $value = ['$regex' => $value['case_insensitive'], '$options' => 'i'];

                return true;
            }

            // Case sensitive (case_sensitive).
            if (array_key_exists('case_sensitive', $value) === true && is_array($value['case_sensitive']) === true) {
                // Todo $value = array_map('like', $value['like']);.
            } elseif (array_key_exists('case_sensitive', $value) === true) {
                $value = ['$regex' => $value['case_sensitive']];

                return true;
            }

            // Handle filter value = array (example: ?property=a,b,c) also works if the property we are filtering on is an array.
            $value = ['$in' => $value];

            return true;
        }//end if

        return false;
    }

    /**
     * Adds search filter to the query on MongoDB. Will use given $search string to search on entire object, unless
     * the _search query is present in $completeFilter query params, then we use that instead.
     * _search query param supports filtering on specific properties with ?_search[property1,property2]=value.
     *
     * @param array       $filter         The filter.
     * @param array       $completeFilter The complete filer.
     * @param string|null $search         The thing you are searching for.
     *
     * @return void This function doesn't return anything.
     */
    private function handleSearch(array &$filter, array $completeFilter, ?string $search)
    {
        if (isset($completeFilter['_search']) === true && empty($completeFilter['_search']) === false) {
            $search = $completeFilter['_search'];
        }

        if (empty($search) === true) {
            return;
        }

        // Normal search on every property with type text (includes strings).
        if (is_string($search) === true) {
            $filter['$text']
                = [
                    '$search'        => $search,
                    '$caseSensitive' => false,
                ];
            // For _search query with specific properties in the [method] like this: ?_search[property1,property2]=value.
        } elseif (is_array($search) === true) {
            $searchRegex = preg_replace('/([^A-Za-z0-9\s])/', '\\\\$1', $search[array_key_first($search)]);
            if (empty($searchRegex) === true) {
                return;
            }

            $searchRegex = ['$regex' => $searchRegex, '$options' => 'i'];
            $properties = explode(',', array_key_first($search));
            foreach ($properties as $property) {
                // Todo: we might want to check if we are allowed to filter on this property? with $this->handleFilterCheck.
                $filter['$or'][][$property] = $searchRegex;
            }
        }
    }

    /**
     * Decides the pagination values.
     *
     * @param int|null $limit   The resulting limit.
     * @param int|null $start   The resulting start value.
     * @param array    $filters The filters.
     *
     * @return array The filters array (unchanged).
     */
    public function setPagination(?int &$limit, ?int &$start, array $filters): array
    {
        $limit = 30;
        if (isset($filters['_limit']) === true) {
            $limit = (int) $filters['_limit'];
        }

        $start = 0;
        if (isset($filters['_start']) === true) {
            $start = (int) $filters['_start'];
        } elseif (isset($filters['_offset']) === true) {
            $start = (int) $filters['_offset'];
        } elseif (isset($filters['_page']) === true) {
            $start = (((int) $filters['_page'] - 1) * $limit);
        }

        return $filters;
    }

    /**
     * Adds pagination variables to an array with the results we found with searchObjects().
     *
     * @param array $filter  The filters.
     * @param array $results The results.
     * @param int   $total   The total.
     *
     * @return array the result with pagination.
     */
    private function handleResultPagination(array $filter, array $results, int $total = 0): array
    {
        // Default values.
        $start = 0;
        $limit = 30;
        $page = 1;

        // Pulling the other values form the filter.
        if (isset($filter['_start']) === true && is_numeric($filter['_start']) === true ) {
            $start = (int) $filter['_start'];
        }

        if (isset($filter['_limit']) === true  && is_numeric($filter['_limit']) === true ) {
            $limit = (int) $filter['_limit'];
        }

        if (isset($filter['_page']) === true  && is_numeric($filter['_page']) === true ) {
            $page = (int) $filter['_page'];
        }

        // Lets build the page & pagination.
        if ($start > 1) {
            $offset = ($start - 1);
        } else {
            $offset = (($page - 1) * $limit);
        }

        $pages = ceil($total / $limit);

        if ($pages === 0) {
            $pages = 1;
        }

        return [
            'results' => $results,
            'count'   => count($results),
            'limit'   => $limit,
            'total'   => $total,
            'offset'  => $offset,
            'page'    => (floor($offset / $limit) + 1),
            'pages'   => $pages,
        ];
    }
}
