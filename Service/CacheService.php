<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\Gateway as Source;
use App\Entity\ObjectEntity;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use MongoDB\Client;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Service to call external sources
 *
 * This service provides a guzzle wrapper to work with sources in the common gateway.
 *
 * @Author Robert Zondervan <robert@conduction.nl>, Ruben van der Linde <ruben@conduction.nl>
 * @TODO add all backend developers here?
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 *
 */

class CacheService
{
    private Client $client;
    private EntityManagerInterface $entityManager;
    private CacheInterface $cache;
    private SymfonyStyle $io;
    private ParameterBagInterface $parameters;


    /**
     * @param AuthenticationService $authenticationService
     * @param EntityManagerInterface $entityManager
     * @param FileService $fileService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CacheInterface $cache,
        ParameterBagInterface $parameters
    )
    {
        $this->entityManager = $entityManager;
        $this->cache = $cache;
        $this->parameters = $parameters;
        if ($this->parameters->get('cache_url', false)) {
            $this->client = new Client($this->parameters->get('cache_url'));
        }
    }

    /**
     * Set symfony style in order to output to the console
     *
     * @param SymfonyStyle $io
     * @return self
     */
    public function setStyle(SymfonyStyle $io):self
    {
        $this->io = $io;

        return $this;
    }


    /**
     * Remov non-exisitng items from the cashe
     */
    public function cleanup()
    {
        (isset($this->io)? $this->io->writeln([
            'Common Gateway Cache Cleanup',
            '============',
            '',
        ]): '');

        (isset($this->io)?$this->io->section('Cleaning Object\'s'): '');
        $collection = $this->client->objects->json;
        $filter = [];
        $objects = $collection->find($filter)->toArray();
        (isset($this->io)?$this->io->writeln('Found '.count($objects).''): '');
    }


    /**
     * Throws all available objects into the cache
     */
    public function warmup()
    {
        (isset($this->io)? $this->io->writeln([
            'Common Gateway Cache Warmup',
            '============',
            '',
        ]): '');

        (isset($this->io)?$this->io->writeln('Connecting to'. $this->parameters->get('cache_url')): '');

        // Backwards compatablity
        if (!isset($this->client)) {
            (isset($this->io)?$this->io->writeln('No cache client found, halting warmup'): '');
            return Command::SUCCESS;
        }

        // Objects
        (isset($this->io)? $this->io->section('Caching Objects\'s'): '');
        $objectEntities = $this->entityManager->getRepository('App:ObjectEntity')->findAll();
        (isset($this->io)? $this->io->writeln('Found '.count($objectEntities).' objects\'s'): '');

        foreach ($objectEntities as $objectEntity) {
            try {
                $this->cacheObject($objectEntity);
            } catch (Exception $exception) {
                $this->ioCatchException($exception);
                continue;
            }
        }

        // Schemas
        (isset($this->io)? $this->io->section('Caching Schema\'s'): '');
        $schemas = $this->entityManager->getRepository('App:Entity')->findAll();
        (isset($this->io)? $this->io->writeln('Found '.count($schemas).' Schema\'s'): '');

        foreach ($schemas as $schema) {
            try {
                $this->cacheShema($schema);
            } catch (Exception $exception) {
                $this->ioCatchException($exception);
                continue;
            }
        }

        // Endpoints
        (isset($this->io)? $this->io->section('Caching Endpoint\'s'): '');
        $endpoints = $this->entityManager->getRepository('App:Endpoint')->findAll();
        (isset($this->io)? $this->io->writeln('Found '.count($endpoints).' Endpoint\'s'): '');

        foreach ($endpoints as $endpoint) {
            try {
                $this->cacheEndpoint($endpoint);
            } catch (Exception $exception) {
                $this->ioCatchException($exception);
                continue;
            }
        }

        // Created indexes
        $collection = $this->client->objects->json->createIndex( ["$**"=>"text" ]);
        $collection = $this->client->schemas->json->createIndex( ["$**"=>"text" ]);
        $collection = $this->client->endpoints->json->createIndex( ["$**"=>"text" ]);

        return Command::SUCCESS;
    }
    
    /**
     * Writes exception data to symfony IO
     *
     * @param Exception $exception The Exception
     * @return void
     */
    private function ioCatchException(Exception $exception)
    {
        $this->io->warning($exception->getMessage());
        $this->io->block("File: {$exception->getFile()}, Line: {$exception->getLine()}");
        $this->io->block("Trace: {$exception->getTraceAsString()}");
    }

    /**
     * Put a single object into the cache
     *
     * @param ObjectEntity $objectEntity
     * @return ObjectEntity
     */
    public function cacheObject(ObjectEntity $objectEntity):ObjectEntity
    {
        // Backwards compatablity
        if (!isset($this->client)) {
            return $objectEntity;
        }
        
        if (isset($this->io)) {
            $this->io->writeln('Start caching object '.$objectEntity->getId().' of type '.$objectEntity->getEntity()->getName());
        }

        $collection = $this->client->objects->json;

        // Lets not cash the entire schema
        $array = $objectEntity->toArray(1, ['id','self','synchronizations','schema'], false, true);

        unset($array['_schema']['required']);
        unset($array['_schema']['properties']);

        //(isset($array['_schema']['$id'])?$array['_schema'] = $array['_schema']['$id']:'');

        $id = (string) $objectEntity->getId();

        $array['id'] = $id;

        if ($collection->findOneAndReplace(
            ['_id'=>$id],
            $array,
            ['upsert'=>true]
        )) {
            (isset($this->io)? $this->io->writeln('Updated object '.$objectEntity->getId().' of type '.$objectEntity->getEntity()->getName().' to cache'): '');
        } else {
            (isset($this->io)? $this->io->writeln('Wrote object '.$objectEntity->getId().' of type '.$objectEntity->getEntity()->getName().' to cache'): '');
        }

        return $objectEntity;
    }

    /**
     * Removes an object from the cache
     *
     * @param ObjectEntity $objectEntity
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
     * Get a single object from the cache
     *
     * @param string $id
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
        $object = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id'=>$id]);
        $object = $this->cacheObject($object)->toArray(1,['id']);

        return $object;
    }

    /**
     * Decides the pagination values
     *
     * @param int $limit        The resulting limit
     * @param int $start        The resulting start value
     * @param array $filters    The filters
     * @return array
     */
    public function setPagination (&$limit, &$start, array $filters): array
    {
        if (isset($filters['limit'])) {
            $limit = intval($filters['limit']);
        } else {
            $limit = 30;
        }
        if (isset($filters['start']) || isset($filters['offset'])) {
            $start = isset($filters['start']) ? intval($filters['start']) : intval($filters['offset']);
        } elseif (isset($filters['page'])) {
            $start = (intval($filters['page']) - 1) * $limit;
        } else {
            $start = 0;
        }

        return $filters;
    }

    /**
     * Searches the object store for objects containing the search string
     *
     * @param string $search a string to search for within the given context
     * @param array $filter an array of dot.notation filters for wich to search with
     * @param array $entities schemas to limit te search to
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
        
        // Make sure we also have all filters stored in $completeFilter before unsetting
        $completeFilter = $filter;
        unset($filter['start'], $filter['offset'], $filter['limit'], $filter['page'],
            $filter['extend'], $filter['search'], $filter['order'], $filter['fields']);
    
        // Filters
        // todo: make this foreach into a function?
        foreach ($filter as $key => &$value) {
            if (substr($key, 0, 1)) {
                // todo: deal with filters starting with _ like: _dateCreated
            }
            // todo: make this if into a function?
            if (is_array($value)) {
                // todo: handle filter value = array (example: ?key=a,b)
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
                    $value = [ "$compareKey" => "{$compareDate->format('c')}" ];
                }
                continue;
            }
            // todo: this works, we should go to php 8.0 later
            if (str_contains($value, '%')) {
                $regex = str_replace('%', '', $value);
                $regex = preg_replace('/([^A-Za-z0-9\s])/', '\\\\$1', $regex);
                $value = [ '$regex' => $regex ];
                continue;
            }
            if ($value === 'IS NOT NULL') {
                $value = [ '$ne' => null ];
                continue;
            }
            if ($value === 'IS NULL' || $value === 'null') {
                $value = null;
                continue;
            }
            // todo: if integer in mongodb we can not search with a string.
            if (is_numeric($value)) {
                $value = (int) $value;
                continue;
            }
            // todo: if boolean in mongodb we can not search with a string.
            if (in_array(strtolower($value), ['true', 'false'])) {
                $value = (bool) $value;
                continue;
            }
            // todo: make exact match default and case insensitive optional:
            $value = [ '$regex' => "^$value$", '$options' => 'im' ];
        }
        
        // Search for single entity WE WOULD LIKE TO SEACH FOR MULTIPLE ENTITIES
        // todo: make this if into a function?
        if (!empty($entities)) {
            foreach ($entities as $entity) {
                $orderError = $this->handleOrderCheck($entity, $completeFilter['order'] ?? null);
                $filterError = $this->handleFilterCheck($entity, $filter ?? null);
                if (!empty($orderError) || !empty($filterError)) {
                    !empty($orderError) && $data['order'] = $orderError;
                    !empty($filterError) && $data['filter'] = $filterError;
                    return [
                        'message' => 'There are some errors in your query parameters',
                        'type'    => 'error',
                        'path'    => $entity->getName(),
                        'data'    => $data,
                    ];
                }
                
                //$filter['_schema.$id']='https://larping.nl/character.schema.json';
                $filter['_schema.$id'] =  $entity->getReference();
            }
        }
    
        // Let see if we need a search
        if (isset($search) and !empty($search)) {
            $filter['$text']
                = [
                '$search'=> $search,
                '$caseSensitive'=> false
            ];
        }
        
        // Limit & Start
        $this->setPagination($limit, $start, $completeFilter);
        
        // Order
        $order = isset($completeFilter['order']) ? str_replace(['ASC', 'asc', 'DESC', 'desc'], [1, 1, -1, -1], $completeFilter['order']) : [];
        !empty($order) && $order[array_keys($order)[0]] = (int) $order[array_keys($order)[0]];
        
        // Find / Search
        $results = $collection->find($filter, ['limit' => $limit, 'skip' => $start, 'sort' => $order])->toArray();
        $total = $collection->count($filter);
        
        return $this->handleResultPagination($completeFilter, $results, $total);
    }
    
    /**
     * Will check if we are allowed to order with the given $order query param.
     * Uses ObjectEntityRepository->getOrderParameters() to check if we are allowed to order, see eavService->handleSearch() $orderCheck
     *
     * @param Entity $entity The entity we are going to check for allowed attributes to order on.
     * @param mixed|array|null $order The order query param, should be an array or null. (but could be a string)
     *
     * @return string|null Returns null if given order query param is correct/allowed or when it is not present. Else an error message.
     */
    private function handleOrderCheck(Entity $entity, $order): ?string
    {
        if (empty($order)) {
            return null;
        }
    
        $orderCheck = $this->entityManager->getRepository('App:ObjectEntity')->getOrderParameters($entity);
    
        if (!is_array($order)) {
            $orderCheckStr = implode(', ', $orderCheck);
            $message = 'Please give an attribute to order on. Like this: ?order[attributeName]=desc/asc. Supported order query parameters: '.$orderCheckStr;
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
     * Uses ObjectEntityRepository->getFilterParameters() to check if we are allowed to filter, see eavService->handleSearch() $filterCheck
     *
     * @param Entity $entity The entity we are going to check for allowed attributes to filter on.
     * @param array|null $filters The filters from query params.
     *
     * @return string|null Returns null if all filters are allowed or if none are present. Else an error message.
     */
    private function handleFilterCheck(Entity $entity, ?array $filters): ?string
    {
        if (empty($filters)) {
            return null;
        }
    
        $filterCheck = $this->entityManager->getRepository('App:ObjectEntity')->getFilterParameters($entity);
    
        foreach ($filters as $param => $value) {
            if (!in_array($param, $filterCheck)) {
                $unsupportedParams = !isset($unsupportedParams) ? $param : "$unsupportedParams, $param";
            }
        }
        if (isset($unsupportedParams)) {
            $filterCheckStr = implode(', ', $filterCheck);
            return 'Unsupported queryParameters ('.$unsupportedParams.'). Supported queryParameters: '.$filterCheckStr;;
        }
        
        return null;
    }
    
    /**
     * Adds pagination variables to an array with the results we found with searchObjects()
     *
     * @param array $filter
     * @param array $results
     * @param int $total
     *
     * @return array the result with pagination.
     */
    private function handleResultPagination(array $filter, array $results, int $total = 0): array
    {
        $start = isset($filter['start']) && is_numeric($filter['start']) ? (int) $filter['start'] : 0;
        $limit = isset($filter['limit']) && is_numeric($filter['limit']) ? (int) $filter['limit'] : 30;
        $page = isset($filter['page']) && is_numeric($filter['page']) ? (int) $filter['page'] : 1;
    
        // Lets build the page & pagination
        if ($start > 1) {
            $offset = $start - 1;
        } else {
            $offset = ($page - 1) * $limit;
        }
        $pages = ceil($total / $limit);
    
        return [
            'results' => $results,
            'count' => count($results),
            'limit' => $limit,
            'total' => $total,
            'offset' => $offset,
            'page' => floor($offset / $limit) + 1,
            'pages' => $pages == 0 ? 1 : $pages
        ];
    }

    /**
     * Put a single endpoint into the cache
     *
     * @param Endpoint $endpoint
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
     * Removes an endpoint from the cache
     *
     * @param Endpoint $endpoint
     * @return void
     */
    public function removeEndpoint(Endpoint $endpoint): void
    {
        // Backwards compatablity
        if (!isset($this->client)) {
            return;
        }

        $collection = $this->client->endpoints->json;

        return;
    }

    /**
     * Get a single endpoint from the cache
     *
     * @param Uuid $id
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
     *
     * Put a single schema into the cache.
     * @param Entity $entity
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
        $array =  $entity->toSchema(null);
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
     * Removes an Schema from the cache
     *
     * @param Entity $entity
     * @return void
     */
    public function removeSchema(Entity $entity): void
    {
        // Backwards compatablity
        if (!isset($this->client)) {
            return;
        }

        $collection = $this->client->schemas->json;

        return;
    }

    /**
     * Get a single schema from the cache
     *
     * @param Uuid $id
     * @return array|null
     */
    public function getSchema(Uuid $id): ?array
    {
        // Backwards compatablity
        if (!isset($this->client)) {
            return [] ;
        }

        $collection = $this->client->schemas->json;

    }
}
