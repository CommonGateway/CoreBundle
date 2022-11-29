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


    /**
     * @param AuthenticationService $authenticationService
     * @param EntityManagerInterface $entityManager
     * @param FileService $fileService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CacheInterface $cache
    )
    {
        $this->client = new Client('mongodb://api-platform:!ChangeMe!@mongodb');
        $this->entityManager = $entityManager;
        $this->cache = $cache;
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
     * Throws all available objects into the cache
     */
    public function warmup(){

        $this->io->writeln([
            'Common Gateway Cache Warmup',
            '============',
            '',
        ]);

        // Objects
        $this->io->section('Caching Objects\'s');
        $objectEntities = $this->entityManager->getRepository('App:ObjectEntity')->findAll();
        $this->io->writeln('Found '.count($objectEntities).' objects\'s');

        foreach($objectEntities as $objectEntity){
            $this->cacheObject($objectEntity);
        }

        // Schemas
        $this->io->section('Caching Schema\'s');
        $schemas = $this->entityManager->getRepository('App:Entity')->findAll();
        $this->io->writeln('Found '.count($schemas).' Schema\'s');

        foreach($schemas as $schema){
            $this->cacheShema($schema);
        }

        // Endpoints
        $this->io->section('Caching Endpoint\'s');
        $endpoints = $this->entityManager->getRepository('App:Endpoint')->findAll();
        $this->io->writeln('Found '.count($endpoints).' Endpoint\'s');

        foreach($endpoints as $endpoint){
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
    public function cacheObject(ObjectEntity $objectEntity):ObjectEntity{
        $collection = $this->client->objects->json;

        // Lets not cash the entire schema
        $array = $objectEntity->toArray(1, ['id','self','synchronizations','schema']);
        $array['_schema'] = $array['_schema']['$id'];
        unset($array['$id']);

        if($collection->findOneAndReplace(
            ['_id'=>$objectEntity->getID()],
            $array,
            ['upsert'=>true]
        )){
            $this->io->writeln('Updated object '.$objectEntity->getId().' to cache');
        }
        else{
            $this->io->writeln('Wrote object '.$objectEntity->getId().' to cache');
        }

        return $objectEntity;
    }

    /**
     * Get a single object from the cache
     *
     * @param string $id
     * @return array|null
     */
    public function getObject(string $id){
        $collection = $this->client->objects->json;

        // Check if object is in the cache
        if($object = $collection ){
            return $object;
        }
        // Fall back tot the entity manager
        $object = $this->entityManager->getRepository('App:ObjectEntity')->find($id);
        $object = $this->cacheObject($object)->toArray(1);

        var_dump($object);
        return $object->toArray();
    }

    /**
     * Searches the object store for objects containing the search string
     *
     * @param string $search
     * @return array|null
     */
    public function searchObjects(string $search): ?array{
        $collection = $this->client->objects->json;

        $filter  = [
            '$text' => [
                '$search'=> $search,
                '$caseSensitive'=> false
            ]
        ];

        //$filter=[];

        return $collection->find($filter)->toArray();
    }

    /**
     * Put a single endpoint into the cache
     *
     * @param Endpoint $endpoint
     * @return Endpoint
     */
    public function cacheEndpoint(Endpoint $endpoint):Endpoint{
        $collection = $this->client->endpoints->json;

        return $endpoint;
    }

    /**
     * Get a single endpoint from the cache
     *
     * @param Uuid $id
     * @return array|null
     */
    public function getEndpoint(Uuid $id): ?array{
        $collection = $this->client->endpoints->json;

    }

    /**
     *
     * Put a single schema into the cache.
     * @param Entity $entity
     * @return Entity
     */
    public function cacheShema(Entity $entity): Entity{
        $collection = $this->client->schemas->json;

        // Remap the array
        $array =  $entity->toSchema(null);
        $array['reference'] = $array['$id'];
        $array['schema'] = $array['$schema'];
        unset($array['$id']);
        unset($array['$schema']);

        /*
        var_dump($array);


        if($collection->findOneAndReplace(
            ['_id'=>$entity->getID()],
            $entity->toSchema(null),
            ['upsert'=>true]
        )){
            $this->io->writeln('Updated object '.$entity->getId().' to cache');
        }
        else{
            $this->io->writeln('Wrote object '.$entity->getId().' to cache');
        }
        */

        return $entity;

    }

    /**
     * Get a single schema from the cache
     *
     * @param Uuid $id
     * @return array|null
     */
    public function getSchema(Uuid $id): ?array{
        $collection = $this->client->schemas->json;

    }
}
