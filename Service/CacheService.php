<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\Gateway as Source;
use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;
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
            $this->cacheObject($objectEntity);
        }

        // Schemas
        (isset($this->io)? $this->io->section('Caching Schema\'s'): '');
        $schemas = $this->entityManager->getRepository('App:Entity')->findAll();
        (isset($this->io)? $this->io->writeln('Found '.count($schemas).' Schema\'s'): '');

        foreach ($schemas as $schema) {
            $this->cacheShema($schema);
        }

        // Endpoints
        (isset($this->io)? $this->io->section('Caching Endpoint\'s'): '');
        $endpoints = $this->entityManager->getRepository('App:Endpoint')->findAll();
        (isset($this->io)? $this->io->writeln('Found '.count($endpoints).' Endpoint\'s'): '');

        foreach ($endpoints as $endpoint) {
            $this->cacheEndpoint($endpoint);
        }

        // Created indexes
        $collection = $this->client->objects->json->createIndex( ["$**"=>"text" ]);
        $collection = $this->client->schemas->json->createIndex( ["$**"=>"text" ]);
        $collection = $this->client->endpoints->json->createIndex( ["$**"=>"text" ]);

        return Command::SUCCESS;
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

        $collection = $this->client->objects->json;

        // Lets not cash the entire schema
        $array = $objectEntity->toArray(1, ['id','self','synchronizations','schema']);

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
            (isset($this->io)? $this->io->writeln('Updated object '.$objectEntity->getId().' to cache'): '');
        } else {
            (isset($this->io)? $this->io->writeln('Wrote object '.$objectEntity->getId().' to cache'): '');
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
    
        // Search for single entity WE WOULD LIKE TO SEACH FOR MULTIPLE ENTITIES
        if (!empty($entities)) {
            foreach ($entities as $entity) {
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
    
        $paginationFilter = $filter;
    
        unset($filter['start'], $filter['offset'], $filter['limit'],
            $filter['page'], $filter['extend'], $filter['search'], $filter['order']);
    
        // Limit & Start
        $this->setPagination($limit, $start, $paginationFilter);
    
        // Order
        // todo ObjectEntityRepository->getOrderParameters() to check if we are allowed to order on the given key, see eavService->handleSearch() $orderCheck
        $order = isset($paginationFilter['order']) ? str_replace(['ASC', 'asc', 'DESC', 'desc'], [1, 1, -1, -1], $paginationFilter['order']) : [];
        !empty($order) && $order[array_keys($order)[0]] = (int) $order[array_keys($order)[0]];
    
        // Find / Search
        $results = $collection->find($filter, ['limit' => $limit, 'skip' => $start, 'sort' => $order])->toArray();
        $total = $collection->count($filter);
    
        return $this->handleResultPagination($paginationFilter, $results, $total);
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
