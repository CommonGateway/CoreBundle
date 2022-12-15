<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use MongoDB\Client;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Service to call external sources.
 *
 * This service provides a guzzle wrapper to work with sources in the common gateway.
 *
 * @Author Robert Zondervan <robert@conduction.nl>, Ruben van der Linde <ruben@conduction.nl>
 * @TODO add all backend developers here?
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class CacheService
{
    private Client $client;
    private EntityManagerInterface $entityManager;
    private CacheInterface $cache;
    private SymfonyStyle $io;
    private ParameterBagInterface $parameters;

    /**
     * @param AuthenticationService  $authenticationService
     * @param EntityManagerInterface $entityManager
     * @param FileService            $fileService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CacheInterface $cache,
        ParameterBagInterface $parameters
    ) {
        $this->entityManager = $entityManager;
        $this->cache = $cache;
        $this->parameters = $parameters;
        if ($this->parameters->get('cache_url', false)) {
            $this->client = new Client($this->parameters->get('cache_url'));
        }
    }

    /**
     * Set symfony style in order to output to the console.
     *
     * @param SymfonyStyle $io
     *
     * @return self
     */
    public function setStyle(SymfonyStyle $io): self
    {
        $this->io = $io;

        return $this;
    }

    /**
     * Remov non-exisitng items from the cashe.
     */
    public function cleanup()
    {
        (isset($this->io) ? $this->io->writeln([
            'Common Gateway Cache Cleanup',
            '============',
            '',
        ]) : '');

        (isset($this->io) ? $this->io->section('Cleaning Object\'s') : '');
        $collection = $this->client->objects->json;
        $filter = [];
        $objects = $collection->find($filter)->toArray();
        (isset($this->io) ? $this->io->writeln('Found '.count($objects).'') : '');
    }

    /**
     * Throws all available objects into the cache.
     */
    public function warmup()
    {
        (isset($this->io) ? $this->io->writeln([
            'Common Gateway Cache Warmup',
            '============',
            '',
        ]) : '');

        (isset($this->io) ? $this->io->writeln('Connecting to'.$this->parameters->get('cache_url')) : '');

        // Backwards compatablity
        if (!isset($this->client)) {
            (isset($this->io) ? $this->io->writeln('No cache client found, halting warmup') : '');

            return Command::SUCCESS;
        }

        // Objects
        (isset($this->io) ? $this->io->section('Caching Objects\'s') : '');
        $objectEntities = $this->entityManager->getRepository('App:ObjectEntity')->findAll();
        (isset($this->io) ? $this->io->writeln('Found '.count($objectEntities).' objects\'s') : '');

        foreach ($objectEntities as $objectEntity) {
            try {
                $this->cacheObject($objectEntity);
            } catch (Exception $exception) {
                $this->ioCatchException($exception);
                continue;
            }
        }

        // Schemas
        (isset($this->io) ? $this->io->section('Caching Schema\'s') : '');
        $schemas = $this->entityManager->getRepository('App:Entity')->findAll();
        (isset($this->io) ? $this->io->writeln('Found '.count($schemas).' Schema\'s') : '');

        foreach ($schemas as $schema) {
            try {
                $this->cacheShema($schema);
            } catch (Exception $exception) {
                $this->ioCatchException($exception);
                continue;
            }
        }

        // Endpoints
        (isset($this->io) ? $this->io->section('Caching Endpoint\'s') : '');
        $endpoints = $this->entityManager->getRepository('App:Endpoint')->findAll();
        (isset($this->io) ? $this->io->writeln('Found '.count($endpoints).' Endpoint\'s') : '');

        foreach ($endpoints as $endpoint) {
            try {
                $this->cacheEndpoint($endpoint);
            } catch (Exception $exception) {
                $this->ioCatchException($exception);
                continue;
            }
        }

        // Created indexes
        $collection = $this->client->objects->json->createIndex(['$**'=>'text']);
        $collection = $this->client->schemas->json->createIndex(['$**'=>'text']);
        $collection = $this->client->endpoints->json->createIndex(['$**'=>'text']);

        return Command::SUCCESS;
    }

    /**
     * Writes exception data to symfony IO.
     *
     * @param Exception $exception The Exception
     *
     * @return void
     */
    private function ioCatchException(Exception $exception)
    {
        $this->io->warning($exception->getMessage());
        $this->io->block("File: {$exception->getFile()}, Line: {$exception->getLine()}");
        $this->io->block("Trace: {$exception->getTraceAsString()}");
    }

    /**
     * Put a single object into the cache.
     *
     * @param ObjectEntity $objectEntity
     *
     * @return ObjectEntity
     */
    public function cacheObject(ObjectEntity $objectEntity): ObjectEntity
    {
        // Backwards compatablity
        if (!isset($this->client)) {
            return $objectEntity;
        }
    
        if (isset($this->io)) {
            $this->io->writeln('Start caching object '.$objectEntity->getId()->toString().' of type '.$objectEntity->getEntity()->getName());
        }

        // todo: temp fix to make sure we have the latest version of this ObjectEntity before we cache it.
        $updatedObjectEntity = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $objectEntity->getId()->toString()]);
        if ($updatedObjectEntity instanceof ObjectEntity) {
            $objectEntity = $updatedObjectEntity;
        } elseif (isset($this->io)) {
            $this->io->writeln('Could not find an ObjectEntity with id: '.$objectEntity->getId()->toString());
        }

        $collection = $this->client->objects->json;

        // Lets not cash the entire schema
        $array = $objectEntity->toArray(['embedded' => true]);

        //(isset($array['_schema']['$id'])?$array['_schema'] = $array['_schema']['$id']:'');

        $id = $objectEntity->getId()->toString();

        $array['id'] = $id;

        if ($collection->findOneAndReplace(
            ['_id'=>$id],
            $array,
            ['upsert'=>true]
        )) {
            (isset($this->io) ? $this->io->writeln('Updated object '.$objectEntity->getId()->toString().' of type '.$objectEntity->getEntity()->getName().' to cache') : '');
        } else {
            (isset($this->io) ? $this->io->writeln('Wrote object '.$objectEntity->getId()->toString().' of type '.$objectEntity->getEntity()->getName().' to cache') : '');
        }

        return $objectEntity;
    }

    /**
     * Removes an object from the cache.
     *
     * @param ObjectEntity $objectEntity
     *
     * @return void
     */
    public function removeObject($id): void
    {
        // Backwards compatablity
        if (!isset($this->client)) {
            return;
        }

        $collection = $this->client->objects->json;

        $collection->findOneAndDelete(['_id'=>$id]);
    }

    /**
     * Get a single object from the cache.
     *
     * @param string $id
     *
     * @return array|null
     */
    public function getObject(string $id)
    {
        // Backwards compatablity
        if (!isset($this->client)) {
            return false;
        }

        $collection = $this->client->objects->json;

        // Check if object is in the cache ????
        if ($object = $collection->findOne(['_id'=>$id])) {
            return $object;
        }

        // Fall back tot the entity manager
        if ($object = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id'=>$id])) {
            return $this->cacheObject($object)->toArray(['embedded' => true]);
        }

        return false;
    }

    /**
     * Searches the object store for objects containing the search string.
     *
     * @param string $search   a string to search for within the given context
     * @param array  $filter   an array of dot.notation filters for wich to search with
     * @param array  $entities schemas to limit te search to
     *
     * @throws Exception
     *
     * @return array|null
     */
    public function searchObjects(string $search = null, array $filter = [], array $entities = []): ?array
    {
        // Backwards compatablity
        if (!isset($this->client)) {
            return [];
        }

        $collection = $this->client->objects->json;

        // Backwards compatibility
        $this->queryBackwardsCompatibility($filter);

        // Make sure we also have all filters stored in $completeFilter before unsetting
        $completeFilter = $filter;
        unset($filter['_start'], $filter['_offset'], $filter['_limit'], $filter['_page'],
            $filter['_extend'], $filter['_search'], $filter['_order'], $filter['_fields']);

        // 'normal' Filters (not starting with _ )
        foreach ($filter as $key => &$value) {
            $this->handleFilter($key, $value);
        }

        // Search for the correct entity / entities
        // todo: make this if into a function?
        if (!empty($entities)) {
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
                $filter['_self.schema.ref']['$in'][] = $entity->getReference();
            }
        }

        // Lets see if we need a search
        $this->handleSearch($filter, $completeFilter, $search);

        // Limit & Start for pagination
        $this->setPagination($limit, $start, $completeFilter);

        // Order
        $order = isset($completeFilter['_order']) ? str_replace(['ASC', 'asc', 'DESC', 'desc'], [1, 1, -1, -1], $completeFilter['_order']) : [];
        !empty($order) && $order[array_keys($order)[0]] = (int) $order[array_keys($order)[0]];

        // Find / Search
        $results = $collection->find($filter, ['limit' => $limit, 'skip' => $start, 'sort' => $order])->toArray();
        $total = $collection->count($filter);

        // Make sure to add the pagination properties in response
        return $this->handleResultPagination($completeFilter, $results, $total);
    }

    /**
     * Make sure we still support the old query params. By translating them to the new ones with _.
     *
     * @param array $filter
     *
     * @return void
     */
    private function queryBackwardsCompatibility(array &$filter)
    {
        !isset($filter['_limit']) && isset($filter['limit']) && $filter['_limit'] = $filter['limit'];
        !isset($filter['_start']) && isset($filter['start']) && $filter['_start'] = $filter['start'];
        !isset($filter['_offset']) && isset($filter['offset']) && $filter['_offset'] = $filter['offset'];
        !isset($filter['_page']) && isset($filter['page']) && $filter['_page'] = $filter['page'];
        !isset($filter['_extend']) && isset($filter['extend']) && $filter['_extend'] = $filter['extend'];
        !isset($filter['_search']) && isset($filter['search']) && $filter['_search'] = $filter['search'];
        !isset($filter['_order']) && isset($filter['order']) && $filter['_order'] = $filter['order'];
        !isset($filter['_fields']) && isset($filter['fields']) && $filter['_fields'] = $filter['fields'];

        unset($filter['start'], $filter['offset'], $filter['limit'], $filter['page'],
            $filter['extend'], $filter['search'], $filter['order'], $filter['fields']);
    }

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
        if (substr($key, 0, 1) == '_') {
            // todo: deal with filters starting with _ like: _dateCreated
        }
        // Handle filters that expect $value to be an array
        if ($this->handleFilterArray($key, $value)) {
            return;
        }
        // todo: this works, we should go to php 8.0 later
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
        // todo: exact match is default, make case insensitive optional:
        $value = preg_replace('/([^A-Za-z0-9\s])/', '\\\\$1', $value);
        $value = ['$regex' => "^$value$", '$options' => 'im'];
    }

    /**
     * Handles a single filter used on a get collection api call. Specifically an filter where the value is an array.
     *
     * @param $key
     * @param $value
     *
     * @throws Exception
     *
     * @return bool
     */
    private function handleFilterArray($key, &$value): bool
    {
        if (is_array($value)) {
            if (array_key_exists('int_compare', $value) && is_array($value['int_compare'])) {
                $value = array_map('intval', $value['int_compare']);
            } elseif (array_key_exists('int_compare', $value)) {
                $value = (int) $value['int_compare'];

                return true;
            }
            if (array_key_exists('bool_compare', $value) && is_array($value['bool_compare'])) {
                $value = array_map('boolval', $value['bool_compare']);
            } elseif (array_key_exists('bool_compare', $value)) {
                $value = (bool) $value['bool_compare'];

                return true;
            }
            if (!empty(array_intersect_key($value, array_flip(['after', 'before', 'strictly_after', 'strictly_before'])))) {
                // Compare datetime
                if (!empty(array_intersect_key($value, array_flip(['after', 'strictly_after'])))) {
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
            // Handle filter value = array (example: ?property=a,b,c) also works if the property we are filtering on is an array
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
     * @param mixed|array|null $order  The order query param, should be an array or null. (but could be a string)
     *
     * @return string|null Returns null if given order query param is correct/allowed or when it is not present. Else an error message.
     */
    private function handleOrderCheck(Entity $entity, $order): ?string
    {
        if (empty($order)) {
            return null;
        }

        $orderCheck = $this->entityManager->getRepository('App:ObjectEntity')->getOrderParameters($entity, '', 1, true);

        if (!is_array($order)) {
            $orderCheckStr = implode(', ', $orderCheck);
            $message = 'Please give an attribute to order on. Like this: ?_order[attributeName]=desc/asc. Supported order query parameters: '.$orderCheckStr;
        }
        if (is_array($order) && count($order) > 1) {
            $message = 'Only one order query param at the time is allowed.';
        }
        if (is_array($order) && !in_array(strtoupper(array_values($order)[0]), ['DESC', 'ASC'])) {
            $message = 'Please use desc or asc as value for your order query param, not: '.array_values($order)[0];
        }
        if (is_array($order) && !in_array(array_keys($order)[0], $orderCheck)) {
            $orderCheckStr = implode(', ', $orderCheck);
            $message = 'Unsupported order query parameter ('.array_keys($order)[0].'). Supported order query parameters: '.$orderCheckStr;
        }
        if (isset($message)) {
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
        if (empty($filters)) {
            return null;
        }

        $filterCheck = $this->entityManager->getRepository('App:ObjectEntity')->getFilterParameters($entity, '', 1, true);

        foreach ($filters as $param => $value) {
            if (!in_array($param, $filterCheck)) {
                $unsupportedParams = !isset($unsupportedParams) ? $param : "$unsupportedParams, $param";
            }
        }
        if (isset($unsupportedParams)) {
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
     * @param array       $filter
     * @param array       $completeFilter
     * @param string|null $search
     *
     * @return void
     */
    private function handleSearch(array &$filter, array $completeFilter, ?string $search)
    {
        if (isset($completeFilter['_search']) && !empty($completeFilter['_search'])) {
            $search = $completeFilter['_search'];
        }
        if (empty($search)) {
            return;
        }

        // Normal search on every property with type text (includes strings)
        if (is_string($search)) {
            $filter['$text']
                = [
                    '$search'       => $search,
                    '$caseSensitive'=> false,
                ];
        }
        // _search query with specific properties in the [method] like this: ?_search[property1,property2]=value
        elseif (is_array($search)) {
            $searchRegex = preg_replace('/([^A-Za-z0-9\s])/', '\\\\$1', $search[array_key_first($search)]);
            if (empty($searchRegex)) {
                return;
            }
            $searchRegex = ['$regex' => $searchRegex, '$options' => 'i'];
            $properties = explode(',', array_key_first($search));
            foreach ($properties as $property) {
                // todo: we might want to check if we are allowed to filter on this property? with $this->handleFilterCheck;
                $filter[$property] = isset($filter[$property]) ? array_merge($filter[$property], $searchRegex) : $searchRegex;
            }
        }
    }

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
        if (isset($filters['_limit'])) {
            $limit = intval($filters['_limit']);
        } else {
            $limit = 30;
        }
        if (isset($filters['_start']) || isset($filters['_offset'])) {
            $start = isset($filters['_start']) ? intval($filters['_start']) : intval($filters['_offset']);
        } elseif (isset($filters['_page'])) {
            $start = (intval($filters['_page']) - 1) * $limit;
        } else {
            $start = 0;
        }

        return $filters;
    }

    /**
     * Adds pagination variables to an array with the results we found with searchObjects().
     *
     * @param array $filter
     * @param array $results
     * @param int   $total
     *
     * @return array the result with pagination.
     */
    private function handleResultPagination(array $filter, array $results, int $total = 0): array
    {
        $start = isset($filter['_start']) && is_numeric($filter['_start']) ? (int) $filter['_start'] : 0;
        $limit = isset($filter['_limit']) && is_numeric($filter['_limit']) ? (int) $filter['_limit'] : 30;
        $page = isset($filter['_page']) && is_numeric($filter['_page']) ? (int) $filter['_page'] : 1;

        // Lets build the page & pagination
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
     * @param Endpoint $endpoint
     *
     * @return Endpoint
     */
    public function cacheEndpoint(Endpoint $endpoint): Endpoint
    {
        // Backwards compatablity
        if (!isset($this->client)) {
            return $endpoint;
        }

        $collection = $this->client->endpoints->json;

        return $endpoint;
    }

    /**
     * Removes an endpoint from the cache.
     *
     * @param Endpoint $endpoint
     *
     * @return void
     */
    public function removeEndpoint(Endpoint $endpoint): void
    {
        // Backwards compatablity
        if (!isset($this->client)) {
            return;
        }

        $collection = $this->client->endpoints->json;
    }

    /**
     * Get a single endpoint from the cache.
     *
     * @param Uuid $id
     *
     * @return array|null
     */
    public function getEndpoint(Uuid $id): ?array
    {
        // Backwards compatablity
        if (!isset($this->client)) {
            return [];
        }

        $collection = $this->client->endpoints->json;
    }

    /**
     * Put a single schema into the cache.
     *
     * @param Entity $entity
     *
     * @return Entity
     */
    public function cacheShema(Entity $entity): Entity
    {
        // Backwards compatablity
        if (!isset($this->client)) {
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
            $this->io->writeln('Updated object '.$entity->getId().' to cache');
        } else {
            $this->io->writeln('Wrote object '.$entity->getId().' to cache');
        }
        */

        return $entity;
    }

    /**
     * Removes an Schema from the cache.
     *
     * @param Entity $entity
     *
     * @return void
     */
    public function removeSchema(Entity $entity): void
    {
        // Backwards compatablity
        if (!isset($this->client)) {
            return;
        }

        $collection = $this->client->schemas->json;
    }

    /**
     * Get a single schema from the cache.
     *
     * @param Uuid $id
     *
     * @return array|null
     */
    public function getSchema(Uuid $id): ?array
    {
        // Backwards compatablity
        if (!isset($this->client)) {
            return [];
        }

        $collection = $this->client->schemas->json;
    }
}
