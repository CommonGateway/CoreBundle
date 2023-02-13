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
use Ramsey\Uuid\Uuid;
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
    }// end cleanup()

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

        // Objects.
        $objectEntities = $this->entityManager->getRepository('App:ObjectEntity')->findAll();
        $this->logger->debut('Found '.count($objectEntities).' objects\'s');

        foreach ($objectEntities as $objectEntity) {
            // Todo: Set to session.
            $this->cacheObject($objectEntity);
            // Todo: remove from session.
        }

        // Schemas.
        $schemas = $this->entityManager->getRepository('App:Entity')->findAll();
        $this->logger->debut('Found '.count($schemas).' schema\'s');

        foreach ($schemas as $schema) {
            // Todo: Set to session.
            $this->cacheShema($schema);
            // Todo: remove from session.
        }

        // Endpoints.
        $endpoints = $this->entityManager->getRepository('App:Endpoint')->findAll();
        $this->logger->debut('Found '.count($endpoints).' endpoints\'s');

        foreach ($endpoints as $endpoint) {
            // Todo: Set to session.
            $this->cacheEndpoint($endpoint);
            // Todo: remove from session.
        }

        // Created indexes.
        $this->client->objects->json->createIndex(['$**' => 'text']);
        $this->client->schemas->json->createIndex(['$**' => 'text']);
        $this->client->endpoints->json->createIndex(['$**' => 'text']);

        $this->logger->debug('Removing deleted endpoints');
        $this->removeDataFromCache($this->client->endpoints->json, 'App:Endpoint');

        $this->logger->debug('Removing deleted objects');
        $this->removeDataFromCache($this->client->objects->json, 'App:ObjectEntity');

        $this->logger->info('Finished cache warmup');

        return Command::SUCCESS;
    }

    /**
     * Loop trough an collection and remove any vallues that no longer exists.
     *
     * @param Collection $collection The collection to use.
     * @param string     $type       The (symfony) entity entity type.
     *
     * @return void This function doesn't return anything.
     */
    private function removeDataFromCache(Collection $collection, string $type): void
    {
        $endpoints = $collection->find()->toArray();
        foreach ($endpoints as $endpoint) {
            $symfonyObject = $this->entityManager->find($type, $endpoint['id']);
            if (empty($symfonyObject) === true) {
                $this->logger->info("removing {$endpoint['id']} from cache", ['type' => $type, 'id' => $endpoint['id']]);
                $collection->findOneAndDelete(['id' => $endpoint['id']]);
            }
        }
    }

    /**
     * Put a single object into the cache.
     *
     * @param ObjectEntity $objectEntity The ObjectEntity to cache.
     *
     * @return ObjectEntity The cached ObjectEntity.
     */
    public function cacheObject(ObjectEntity $objectEntity): ObjectEntity
    {
        // For when we can't generate a schema for an ObjectEntity (for example setting an id on ObjectEntity created with testData).
        if (empty($objectEntity->getEntity()) === true) {
            return $objectEntity;
        }

        // Backwards compatibility.
        if (isset($this->client) === false) {
            return $objectEntity;
        }

        $this->logger->debug('Start caching object '.$objectEntity->getId()->toString().' of type '.$objectEntity->getEntity()->getName());

        // Todo: temp fix to make sure we have the latest version of this ObjectEntity before we cache it.
        $updatedObjectEntity = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $objectEntity->getId()->toString()]);

        if ($updatedObjectEntity instanceof ObjectEntity) {
            $objectEntity = $updatedObjectEntity;
        } else {
            $this->logger->error('Could not find an ObjectEntity with id: '.$objectEntity->getId()->toString());
        }

        $collection = $this->client->objects->json;

        // Lets not cash the entire schema.
        $array = $objectEntity->toArray(['embedded' => true]);

        $id = $objectEntity->getId()->toString();

        $array['id'] = $id;

        if ($collection->findOneAndReplace(
            ['_id' => $id],
            $array,
            ['upsert' => true]
        ) === true) {
            $this->logger->debug('Updated object '.$objectEntity->getId()->toString().' of type '.$objectEntity->getEntity()->getName().' to cache');
        } else {
            $this->logger->debug('Wrote object '.$objectEntity->getId()->toString().' of type '.$objectEntity->getEntity()->getName().' to cache');
        }

        return $objectEntity;
    }

    /**
     * Removes an object from the cache.
     *
     * @param ObjectEntity $object The ObjectEntity to remove.
     *
     * @return void This function doesn't return anything.
     */
    public function removeObject(ObjectEntity $object): void
    {
        // Backwards compatibility.
        if (isset($this->client) === false) {
            return;
        }

        $id = $object->getId()->toString();
        $collection = $this->client->objects->json;

        $collection->findOneAndDelete(['_id' => $id]);

        $this->logger->info('Removed object from cache', ['object' => $id]);
    }

    /**
     * Get a single object from the cache.
     *
     * @param string $id The id of the object.
     *
     * @return mixed The ObjectEntity as an array or false.
     */
    public function getObject(string $id)
    {
        // Backwards compatibility.
        if (isset($this->client) === false) {
            return false;
        }

        $collection = $this->client->objects->json;

        // Check if object is in the cache ????
        $object = $collection->findOne(['_id' => $id]);
        if (empty($object) === false) {
            $this->logger->debug('Retrieved object from cache', ['object' => $id]);

            return $object;
        }

        // Fall back to the entity manager.
        $object = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $id]);
        if ($object instanceof ObjectEntity === true) {
            $this->logger->debug('Could not retrieve object from cache', ['object' => $id]);

            return $this->cacheObject($object)->toArray(['embedded' => true]);
        }

        $this->logger->error('Object does not seem to exist', ['object' => $id]);

        return false;
    }

    /**
     * Searches the cache for objects containing the search string.
     *
     * @param string|null $search   a string to search for within the given context.
     * @param array       $filter   an array of dot notation filters for which to search with.
     * @param array       $entities schemas to limit te search to.
     *
     * @throws Exception A basic Exception.
     *
     * @return array|null The objects found.
     */
    public function searchObjects(string $search = null, array $filter = [], array $entities = []): ?array
    {
        // Backwards compatibility.
        if (isset($this->client) === false) {
            return [];
        }

        $collection = $this->client->objects->json;

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
        // todo: make this if into a function?
        if (empty($entities) === false) {
            foreach ($entities as $entity) {
                // todo: disable this for now, put back later!
//                $orderError = $this->handleOrderCheck($entity, $completeFilter['_order'] ?? null);
//                $filterError = $this->handleFilterCheck($entity, $filter ?? null);
//                if (!empty($orderError) || !empty($filterError)) {
//                    !empty($orderError) && $errorData['order'] = $orderError;
//                    !empty($filterError) && $errorData['filter'] = $filterError;
//                    return [
//                        'message' => 'There are some errors in your query parameters',
//                        'type'    => 'error',
//                        'path'    => $entity->getName(),
//                        'data'    => $errorData,
//                    ];
//                }

                //$filter['_self.schema.ref']='https://larping.nl/character.schema.json';
                $filter['_self.schema.id']['$in'][] = $entity;
            }
        }

        // Lets see if we need a search.
        $this->handleSearch($filter, $completeFilter, $search);

        // Limit & Start for pagination.
        $this->setPagination($limit, $start, $completeFilter);

        // Order.
        $order = isset($completeFilter['_order']) ? str_replace(['ASC', 'asc', 'DESC', 'desc'], [1, 1, -1, -1], $completeFilter['_order']) : [];
        !empty($order) && $order[array_keys($order)[0]] = (int) $order[array_keys($order)[0]];

        // Find / Search.
        $results = $collection->find($filter, ['limit' => $limit, 'skip' => $start, 'sort' => $order])->toArray();
        $total = $collection->count($filter);

        // Make sure to add the pagination properties in response.
        return $this->handleResultPagination($completeFilter, $results, $total);
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

        // Handle filters that expect $value to be an array
        if ($this->handleFilterArray($key, $value) === true) {
            return;
        }

        // Todo: this works, we should go to php 8.0 later.
        if (str_contains($value, '%')) {
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
     * Todo: make code in this function abstract or split it into multiple functions.
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
            // int_compare
            if (array_key_exists('int_compare', $value) && is_array($value['int_compare'])) {
                $value = array_map('intval', $value['int_compare']);
            } elseif (array_key_exists('int_compare', $value)) {
                $value = (int) $value['int_compare'];

                return true;
            }
            // bool_compare
            if (array_key_exists('bool_compare', $value) && is_array($value['bool_compare'])) {
                $value = array_map('boolval', $value['bool_compare']);
            } elseif (array_key_exists('bool_compare', $value)) {
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
                //$value = array_map('like', $value['like']); @todo
            } elseif (array_key_exists('like', $value) === true) {
                $value = preg_replace('/([^A-Za-z0-9\s])/', '\\\\$1', $value['like']);
                $value = ['$regex' => ".*$value.*", '$options' => 'im'];

                return true;
            }
            // Regex (regex).
            if (array_key_exists('regex', $value) === true && is_array($value['regex']) === true) {
                //$value = array_map('like', $value['like']); @todo
            } elseif (array_key_exists('regex', $value) === true) {
                $value = ['$regex' => $value['regex']];

                return true;
            }
            // Greater then or equel (>=).
            if (array_key_exists('>=', $value) === true && is_array($value['>=']) === true) {
                //$value = array_map('like', $value['like']); @todo
            } elseif (array_key_exists('>=', $value) === true) {
                $value = ['$gte' => (int) $value['>=']];

                return true;
            }
            // Greather then (>).
            if (array_key_exists('>', $value) === true && is_array($value['>']) === true) {
                //$value = array_map('like', $value['like']); @todo
            } elseif (array_key_exists('>', $value) === true) {
                $value = ['$gt' => (int) $value['>']];

                return true;
            }
            // Smaller than or equal  (<=).
            if (array_key_exists('<=', $value) === true && is_array($value['<=']) === true) {
                //$value = array_map('like', $value['like']); @todo
            } elseif (array_key_exists('<=', $value) === true) {
                $value = ['$lte '=> (int) $value['<=']];

                return true;
            }
            // Smaller then (<).
            if (array_key_exists('<', $value) === true && is_array($value['<']) === true) {
                //$value = array_map('like', $value['like']); @todo
            } elseif (array_key_exists('<', $value) === true) {
                $value = ['$lt' => (int) $value['<']];

                return true;
            }
            // Exact (exact).
            if (array_key_exists('exact', $value) === true && is_array($value['exact']) === true) {
                //$value = array_map('like', $value['like']); @todo
            } elseif (array_key_exists('exact', $value) === true) {
                $value = $value;

                return true;
            }
            // Case insensitive (case_insensitive).
            if (array_key_exists('case_insensitive', $value) === true && is_array($value['case_insensitive']) === true) {
                //$value = array_map('like', $value['like']); @todo
            } elseif (array_key_exists('case_insensitive', $value) === true) {
                $value = ['$regex' => $value['case_insensitive'], '$options' => 'i'];

                return true;
            }
            // Case sensitive (case_sensitive).
            if (array_key_exists('case_sensitive', $value) === true && is_array($value['case_sensitive']) === true) {
                //$value = array_map('like', $value['like']); @todo
            } elseif (array_key_exists('case_sensitive', $value) === true) {
                $value = ['$regex' => $value['case_sensitive']];

                return true;
            }

            // Handle filter value = array (example: ?property=a,b,c) also works if the property we are filtering on is an array.
            $value = ['$in' => $value];

            return true;
        }

        return false;
    }

    /**
     * Will check if we are allowed to order with the given $order query param.
     * Uses ObjectEntityRepository->getOrderParameters() to check if we are allowed to order, see eavService->handleSearch() $orderCheck.
     *
     * @param Entity           $entity The entity we are going to check for allowed attributes to order on.
     * @param mixed|array|null $order  The order query param, should be an array or null. (but could be a string).
     *
     * @return string|null Returns null if given order query param is correct/allowed or when it is not present. Else an error message.
     */
    private function handleOrderCheck(Entity $entity, $order): ?string
    {
        if (empty($order) === true) {
            return null;
        }

        $orderCheck = $this->entityManager->getRepository('App:ObjectEntity')->getOrderParameters($entity, '', 1, true);

        if (is_array($order) === false) {
            $orderCheckStr = implode(', ', $orderCheck);
            $message = 'Please give an attribute to order on. Like this: ?_order[attributeName]=desc/asc. Supported order query parameters: '.$orderCheckStr;
        }

        if (is_array($order) === true && count($order) > 1) {
            $message = 'Only one order query param at the time is allowed.';
        }

        if (is_array($order) === true && in_array(strtoupper(array_values($order)[0]), ['DESC', 'ASC']) === false) {
            $message = 'Please use desc or asc as value for your order query param, not: '.array_values($order)[0];
        }

        if (is_array($order) === true && in_array(array_keys($order)[0], $orderCheck) === false) {
            $orderCheckStr = implode(', ', $orderCheck);
            $message = 'Unsupported order query parameter ('.array_keys($order)[0].'). Supported order query parameters: '.$orderCheckStr;
        }

        if (isset($message) === true) {
            return $message;
        }

        return null;
    }

    /**
     * Will check if we are allowed to filter on the given $filters in the query params.
     * Uses ObjectEntityRepository->getFilterParameters() to check if we are allowed to filter, see eavService->handleSearch() $filterCheck.
     *
     * @param Entity     $entity  The entity we are going to check for allowed attributes to filter on.
     * @param array|null $filters The filters from query params.
     *
     * @return string|null Returns null if all filters are allowed or if none are present. Else an error message.
     */
    private function handleFilterCheck(Entity $entity, ?array $filters): ?string
    {
        if (empty($filters) === true) {
            return null;
        }

        $filterCheck = $this->entityManager->getRepository('App:ObjectEntity')->getFilterParameters($entity, '', 1, true);

        foreach ($filters as $param => $value) {
            if (in_array($param, $filterCheck) === false) {
                $unsupportedParams = !isset($unsupportedParams) ? $param : "$unsupportedParams, $param";
            }
        }
        if (isset($unsupportedParams) === true) {
            $filterCheckStr = implode(', ', $filterCheck);

            return 'Unsupported queryParameters ('.$unsupportedParams.'). Supported queryParameters: '.$filterCheckStr;
        }

        return null;
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
                    '$search'       => $search,
                    '$caseSensitive'=> false,
                ];
        }
        // _search query with specific properties in the [method] like this: ?_search[property1,property2]=value.
        elseif (is_array($search) === true) {
            $searchRegex = preg_replace('/([^A-Za-z0-9\s])/', '\\\\$1', $search[array_key_first($search)]);
            if (empty($searchRegex)) {
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
            $limit = intval($filters['_limit']);
        }

        $start = 0;
        if (isset($filters['_start']) === true) {
            $start = intval($filters['_start']);
        } elseif (isset($filters['_offset']) === true) {
            $start = intval($filters['_offset']);
        } elseif (isset($filters['_page']) === true) {
            $start = (intval($filters['_page']) - 1) * $limit;
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
        $start = isset($filter['_start']) && is_numeric($filter['_start']) ? (int) $filter['_start'] : 0;
        $limit = isset($filter['_limit']) && is_numeric($filter['_limit']) ? (int) $filter['_limit'] : 30;
        $page = isset($filter['_page']) && is_numeric($filter['_page']) ? (int) $filter['_page'] : 1;

        // Lets build the page & pagination.
        if ($start > 1) {
            $offset = $start - 1;
        } else {
            $offset = ($page - 1) * $limit;
        }
        $pages = ceil($total / $limit);

        return [
            'results' => $results,
            'count'   => count($results),
            'limit'   => $limit,
            'total'   => $total,
            'offset'  => $offset,
            'page'    => floor($offset / $limit) + 1,
            'pages'   => $pages == 0 ? 1 : $pages,
        ];
    }

    /**
     * Put a single endpoint into the cache.
     *
     * @param Endpoint $endpoint The endpoint to cache.
     *
     * @return Endpoint The cached endpoint.
     */
    public function cacheEndpoint(Endpoint $endpoint): Endpoint
    {
        // Backwards compatibility.
        if (isset($this->client) === false) {
            return $endpoint;
        }

        $this->logger->debug('Start caching endpoint '.$endpoint->getId()->toString().' with name: '.$endpoint->getName());

        $updatedEndpoint = $this->entityManager->getRepository('App:Endpoint')->find($endpoint->getId());
        if ($updatedEndpoint instanceof Endpoint === true) {
            $endpoint = $updatedEndpoint;
        } else {
            $this->logger->debug('Could not find an Endpoint with id: '.$endpoint->getId()->toString());
        }

        $collection = $this->client->endpoints->json;

        $endpointArray = $this->serializer->normalize($endpoint);

        if ($collection->findOneAndReplace(
            ['id' => $endpoint->getId()->toString()],
            $endpointArray,
            ['upsert'=>true]
        ) === true
        ) {
            $this->logger->debug('Updated endpoint '.$endpoint->getId()->toString().' to cache');
        } else {
            $this->logger->debug('Wrote object '.$endpoint->getId()->toString().' to cache');
        }

        return $endpoint;
    }

    /**
     * Removes an endpoint from the cache.
     *
     * @param Endpoint $endpoint The endpoint to remove.
     *
     * @return void This function doesn't return anything.
     */
    public function removeEndpoint(Endpoint $endpoint): void
    {
        // Backwards compatibility.
        if (!isset($this->client)) {
            return;
        }

        $collection = $this->client->endpoints->json;

        $collection->findOneAndDelete(['id' => $endpoint->getId()->toString()]);
    }

    /**
     * Get a single endpoint from the cache.
     *
     * @param string $id The uuid of the endpoint.
     *
     * @return array|null The Endpoint as an array, empty array or null.
     */
    public function getEndpoint(string $id): ?array
    {
        // Backwards compatibility.
        if (!isset($this->client)) {
            return [];
        }

        $collection = $this->client->endpoints->json;

        $object = $collection->findOne(['id' => $id]);
        if (empty($object) === false) {
            return $object;
        }

        $object = $this->entityManager->getRepository('App:Endpoint')->find($id);
        if ($object instanceof Endpoint === true) {
            return $this->serializer->normalize($object);
        }

        $this->logger->error('Endpoint does not seem to exist', ['endpoint' => $id]);

        return null;
    }

    /**
     * Get endpoints from cache.
     *
     * @param array $filter The applied filter.
     *
     * @return Endpoint|null Todo this should probably be array or something?
     */
    public function getEndpoints(array $filter): ?Endpoint
    {
        // Backwards compatibility.
        if (isset($this->client) === false) {
            return null;
        }

        $collection = $this->client->endpoints->json;

        if (isset($filter['path'])) {
            $path = $filter['path'];
            $filter['$where'] = "\"$path\".match(this.pathRegex)";
            unset($filter['path']);
        }
        if (isset($filter['method'])) {
            $method = $filter['method'];
            $filter['$or'] = [['methods' => ['$in' => [$method]]], ['method' => $method]];
            unset($filter['method']);
        }

        $endpoints = $collection->find($filter)->toArray();

        if (count($endpoints) > 1) {
            throw new NonUniqueResultException();
        } elseif (count($endpoints) == 1) {
            //@TODO: We actually want to use the denormalizer, but that breaks on not setting ids
            return $this->entityManager->find('App\Entity\Endpoint', $endpoints[0]['id']);
        } else {
            return null;
        }
    }

    /**
     * Put a single schema into the cache.
     *
     * @param Entity $entity The Entity to cache.
     *
     * @return Entity The cached Entity.
     */
    public function cacheShema(Entity $entity): Entity
    {
        // Backwards compatibility.
        if (isset($this->client) === false) {
            return $entity;
        }
        $collection = $this->client->schemas->json;

        // Remap the array
        $array = $entity->toSchema(null);
        $array['reference'] = $array['$id'];
        $array['schema'] = $array['$schema'];
        unset($array['$id']);
        unset($array['$schema']);

        /*
        var_dump($array);


        if ($collection->findOneAndReplace(
            ['_id'=>$entity->getID()],
            $entity->toSchema(null),
            ['upsert'=>true]
        )) {
        } else {
        }
        */

        return $entity;
    }

    /**
     * Removes a Schema from the cache.
     *
     * @param Entity $entity The entity to remove from cache.
     *
     * @return void This function doesn't return anything.
     */
    public function removeSchema(Entity $entity): void
    {
        // Backwards compatibility.
        if (isset($this->client) === false) {
            return;
        }

        $collection = $this->client->schemas->json;
    }

    /**
     * Get a single schema from the cache.
     *
     * @param string $id The uuid of the schema
     *
     * @return array|null An Entity as array or null.
     */
    public function getSchema(string $id): ?array
    {
        // Backwards compatibility.
        if (isset($this->client) === false) {
            return null;
        }

        $collection = $this->client->schemas->json;

        return null;
    }
}
