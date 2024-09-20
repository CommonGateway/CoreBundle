<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Database;
use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Entity\Organization;
use App\Entity\User;
use App\Service\ApplicationService;
use CommonGateway\CoreBundle\Service\Cache\ClientInterface;
use CommonGateway\CoreBundle\Service\Cache\ElasticSearchClient;
use CommonGateway\CoreBundle\Service\Cache\ElasticSearchCollection;
use CommonGateway\CoreBundle\Service\Cache\MongoDbCollection;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use CommonGateway\CoreBundle\Service\Cache\MongoDbClient as Client;
use CommonGateway\CoreBundle\Service\Cache\CollectionInterface as Collection;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Service to call external sources.
 *
 * This service provides a guzzle wrapper to work with sources in the common gateway.
 *
 * @Author Ruben van der Linde <ruben@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>, Robert Zondervan <robert@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class CacheService
{

    /**
     * @var Client
     */
    private Client $client;

    /**
     * @var Client|null
     */
    private ?ClientInterface $objectsClient;

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var CacheInterface
     */
    private CacheInterface $cache;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var SymfonyStyle
     */
    private SymfonyStyle $style;

    /**
     * @var ParameterBagInterface
     */
    private ParameterBagInterface $parameters;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var SessionInterface $session
     */
    private SessionInterface $session;

    /**
     * @var Filesystem $filesystem
     */
    private Filesystem $filesystem;

    /**
     * Object Entity Service.
     *
     * @var ObjectEntityService
     */
    private ObjectEntityService $objectEntityService;

    /**
     * @param EntityManagerInterface $entityManager       The entity manager
     * @param CacheInterface         $cache               The cache interface
     * @param LoggerInterface        $cacheLogger         The logger for the cache channel.
     * @param ParameterBagInterface  $parameters          The Parameter bag
     * @param SerializerInterface    $serializer          The serializer
     * @param ObjectEntityService    $objectEntityService The Object Entity Service.
     * @param SessionInterface       $session             The current session.
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CacheInterface $cache,
        LoggerInterface $cacheLogger,
        ParameterBagInterface $parameters,
        SerializerInterface $serializer,
        ObjectEntityService $objectEntityService,
        SessionInterface $session,
        private readonly ApplicationService $applicationService
    ) {
        $this->entityManager       = $entityManager;
        $this->cache               = $cache;
        $this->logger              = $cacheLogger;
        $this->parameters          = $parameters;
        $this->serializer          = $serializer;
        $this->objectEntityService = $objectEntityService;
        $this->session             = $session;
        if ($this->parameters->get('cache_url', false)) {
            $this->client = new Client($this->parameters->get('cache_url'), entityManager: $this->entityManager, objectEntityService: $this->objectEntityService, cacheLogger: $this->logger);
        }

        $this->filesystem = new Filesystem();

    }//end __construct()

    /**
     * Use current user and the organization of this user to get the correct objects database client.
     *
     * @return void
     */
    private function setObjectClient(): void
    {
        $this->objectsClient = null;

        $organization = null;
        $user         = $this->objectEntityService->findCurrentUser();
        if ($user !== null && $user->getOrganization() !== null) {
            $organization = $this->entityManager->getRepository(Organization::class)->find($user->getOrganization());
        }

        try {
            if ($user === null && $this->applicationService->getApplication() !== null) {
                $application  = $this->applicationService->getApplication();
                $organization = $application->getOrganization();
            }
        } catch (Exception $e) {
            $this->logger->info('Cannot determine tenant from application: '.$e->getMessage());
        }

        if ($organization !== null && $organization->getDatabase() !== null) {
            $this->objectsClient = $this->createObjectClient(database: $organization->getDatabase());
        }

    }//end setObjectClient()

    /**
     * Create a ClientInterface based on the given $database configuration.
     *
     * @param Database $database The database object containing the configuration needed to create a ClientInterface.
     *
     * @return ClientInterface|null The created ClientInterface object or null.
     */
    private function createObjectClient(Database $database): ?ClientInterface
    {
        $objectsClient = null;
        if ($database->getType() === 'mongodb') {
            $objectsClient = new Client($database->getUri(), entityManager: $this->entityManager, objectEntityService: $this->objectEntityService, cacheLogger: $this->logger);
        }

        if ($database->getType() === 'elasticsearch') {
            $objectsClient = new ElasticSearchClient($database->getUri(), $database->getAuth());
        }

        return $objectsClient;

    }//end createObjectClient()

    /**
     * Set symfony style in order to output to the console.
     *
     * @param SymfonyStyle $style
     *
     * @return self
     */
    public function setStyle(SymfonyStyle $style): self
    {
        $this->style = $style;

        return $this;

    }//end setStyle()

    /**
     * Remove non-existing items from the cache.
     *
     * @return void Nothing.
     */
    public function cleanup(): void
    {
        isset($this->style) === true && $this->style->writeln(
            [
                'Common Gateway Cache Cleanup',
                '============',
                '',
            ]
        );

        isset($this->style) === true && $this->style->section('Cleaning Object\'s');
        $objectDatabases = $this->entityManager->getRepository(Database::class)->findAll();
        foreach ($objectDatabases as $database) {
            $objectsClient = $this->createObjectClient(database: $database);

            if ($objectsClient === null) {
                continue;
            }

            $collection = $objectsClient->objects->json;

            $filter  = [];
            $objects = $collection->find($filter)->toArray();
            isset($this->style) === true && $this->style->writeln('Found '.count($objects).' in Database '.$database->getReference());
        }

    }//end cleanup()

    /**
     * Gets all schema references from a bundle/package.
     *
     * Reads all /Installation/Schema files from a bundle/package and gets the references from the schemas.
     *
     * @param string $bundleToCache
     *
     * @return array Schema references.
     */
    private function getSchemaReferencesFromBundle(string $bundleToCache): array
    {
        $hits = new Finder();
        $hits = $hits->in('vendor/'.$bundleToCache.'/Installation/Schema');

        $schemaRefs = [];
        foreach ($hits->files() as $file) {
            $schema = json_decode($file->getContents(), true);
            if (empty($schema) === true) {
                $this->logger->error($file->getFilename().' is not a valid json object');

                return [];
            }

            if (isset($schema['$id']) === true) {
                $schemaRefs[] = $schema['$id'];
            }
        }

        return $schemaRefs;

    }//end getSchemaReferencesFromBundle()

    /**
     * Gets all object entities from a bundle/package.
     *
     * Reads all /Installation/Schema files from a bundle/package and gets the references from the schemas. Then fetches all object entities from entities found with the references.
     *
     * @param array $schemaRefs
     *
     * @return mixed|bool ObjectEntities or false.
     */
    private function getObjectEntitiesFromBundle(array $schemaRefs): mixed
    {

        return $this->entityManager->getRepository(ObjectEntity::class)->findByReferences($schemaRefs);

    }//end getObjectEntitiesFromBundle()

    /**
     * Throws all available objects into the cache.
     *
     * @param array       $config        An array which can contain the keys 'objects', 'schemas' and/or 'endpoints' to skip caching these specific objects. Can also contain the key removeOnly in order to only remove from cache.
     * @param string|null $bundleToCache Bundle to cache objects from
     *
     * @return int
     */
    public function warmup(array $config = [], ?string $bundleToCache = null): int
    {
        isset($this->style) === true && $this->style->writeln(
            [
                'Common Gateway Cache Warmup',
                '============',
                '',
            ]
        );

        isset($this->style) === true && $this->style->writeln('Connecting to '.$this->parameters->get('cache_url'));

        // Backwards compatibility.
        if (((isset($config['schemas']) === false || $config['schemas'] !== true)
            || (isset($config['endpoints']) === false || $config['endpoints'] !== true))
            && isset($this->client) === false
        ) {
            isset($this->style) === true && $this->style->writeln('No cache client found, halting warmup');

            return Command::FAILURE;
        }

        $schemaRefs = [];

        // Objects.
        if ((isset($config['objects']) === false || $config['objects'] !== true)
            && (isset($config['removeOnly']) === false || $config['removeOnly'] !== true)
        ) {
            isset($this->style) === true && $this->style->section('Caching Objects');
            if ($bundleToCache !== null) {
                $schemaRefs     = $this->getSchemaReferencesFromBundle(bundleToCache: $bundleToCache);
                $objectEntities = $this->getObjectEntitiesFromBundle(schemaRefs: $schemaRefs);
                if ($objectEntities === false) {
                    return Command::FAILURE;
                }
            } else {
                $objectEntities = $this->entityManager->getRepository(ObjectEntity::class)->findAll();
            }

            isset($this->style) === true && $this->style->writeln('Found '.count($objectEntities).' objects\'s');

            foreach ($objectEntities as $objectEntity) {
                try {
                    $this->cacheObject($objectEntity);
                } catch (Exception $exception) {
                    $this->styleCatchException(exception: $exception);
                    continue;
                }
            }
        }//end if

        // Schemas.
        if ((isset($config['schemas']) === false || $config['schemas'] !== true)
            && (isset($config['removeOnly']) === false || $config['removeOnly'] !== true)
            && $bundleToCache === null
        ) {
            isset($this->style) === true && $this->style->section('Caching Schema\'s');
            $schemas = $this->entityManager->getRepository('App:Entity')->findAll();
            isset($this->style) === true && $this->style->writeln('Found '.count($schemas).' Schema\'s');

            foreach ($schemas as $schema) {
                try {
                    $this->cacheShema(entity: $schema);
                } catch (Exception $exception) {
                    $this->styleCatchException(exception: $exception);
                    continue;
                }
            }
        }

        // Endpoints.
        if ((isset($config['endpoints']) === false || $config['endpoints'] !== true)
            && (isset($config['removeOnly']) === false || $config['removeOnly'] !== true)
            && $bundleToCache === null
        ) {
            isset($this->style) === true && $this->style->section('Caching Endpoint\'s');
            $endpoints = $this->entityManager->getRepository('App:Endpoint')->findAll();
            isset($this->style) === true && $this->style->writeln('Found '.count($endpoints).' Endpoint\'s');

            foreach ($endpoints as $endpoint) {
                try {
                    $this->cacheEndpoint(endpoint: $endpoint);
                } catch (Exception $exception) {
                    $this->styleCatchException(exception: $exception);
                    continue;
                }
            }
        }

        // Created indexes and remove data from cache.
        $objectDatabases = $this->entityManager->getRepository(Database::class)->findAll();
        if (isset($config['objects']) === false || $config['objects'] !== true) {
            foreach ($objectDatabases as $database) {
                $objectsClient = $this->createObjectClient(database: $database);

                if ($objectsClient === null) {
                    continue;
                }

                $objectsClient->objects->json->createIndex(['$**' => 'text']);

                $this->removeDataFromCache(
                    collection: $objectsClient->objects->json,
                    type: 'App:ObjectEntity',
                    schemaRefs: $schemaRefs,
                    database: $database
                );
            }

            $this->client->objects->json->createIndex(['$**' => 'text']);
            $this->removeDataFromCache(
                collection: $this->client->objects->json,
                type: 'App:ObjectEntity',
                schemaRefs: $schemaRefs
            );
        }//end if

        if ((isset($config['schemas']) === false || $config['schemas'] !== true) && $bundleToCache === null) {
            $this->client->schemas->json->createIndex(['$**' => 'text']);
        }

        if ((isset($config['endpoints']) === false || $config['endpoints'] !== true) && $bundleToCache === null) {
            $this->client->endpoints->json->createIndex(['$**' => 'text']);

            $this->removeDataFromCache(collection: $this->client->endpoints->json, type:  'App:Endpoint');
        }

        return Command::SUCCESS;

    }//end warmup()

    private function removeDataFromCache(Collection $collection, string $type, array $schemaRefs = [], Database $database = null): void
    {
        if (isset($this->style) === true) {
            $databaseMsg = $database ? ' from Database: '.$database->getReference() : null;
            $this->style->section("Removing deleted $type".$databaseMsg);
        }

        if (empty($schemaRefs) === false) {
            $filter = [];
            foreach ($schemaRefs as $schemaRef) {
                $filter['_self.schema.id']['$in'][] = $schemaRef;
            }

            $objects = $collection->find($filter, [])->toArray();
        } else {
            $objects = $collection->find()->toArray();
        }

        foreach ($objects as $object) {
            if ($this->entityManager->find($type, $object['_id']) === null) {
                if (isset($this->style) === true) {
                    $this->style->writeln("removing {$object['_id']} from cache");
                }

                $collection->findOneAndDelete(['id' => $object['_id']]);
            }
        }

    }//end removeDataFromCache()

    /**
     * Writes exception data to symfony IO.
     *
     * @param Exception $exception The Exception
     *
     * @return void
     */
    private function styleCatchException(Exception $exception): void
    {
        $this->logger->error($exception->getMessage());
        if (isset($this->style) === true) {
            $this->style->warning($exception->getMessage());
            $this->style->block("File: {$exception->getFile()}, Line: {$exception->getLine()}");
            $this->style->block("Trace: {$exception->getTraceAsString()}");
        };

    }//end styleCatchException()

    /**
     * Put a single object into the cache.
     *
     * @param ObjectEntity $objectEntity
     *
     * @return ObjectEntity
     */
    public function cacheObject(ObjectEntity $objectEntity): ObjectEntity
    {
        // For when we can't generate a schema for an ObjectEntity (for example setting an id on ObjectEntity created with testData).
        if ($objectEntity->getEntity() === null) {
            return $objectEntity;
        }

        $this->setObjectClient();
        if (isset($this->objectsClient) === true) {
            $collection = $this->objectsClient->objects->json;
        } else if ($objectEntity->getOrganization() !== null && $objectEntity->getOrganization()->getDatabase() !== null) {
            $database      = $objectEntity->getOrganization()->getDatabase();
            $objectsClient = $this->createObjectClient(database: $database);
            if ($objectsClient === null) {
                $collection = $this->client->objects->json;
            } else {
                $collection = $objectsClient->objects->json;
            }
        } else if (isset($this->client) === true) {
            $collection = $this->client->objects->json;
        } else {
            return $objectEntity;
        }

        if (isset($this->style) === true) {
            $databaseRef = $this->parameters->get('cache_url');
            if (isset($database) === true) {
                $databaseRef = $database->getReference();
            }

            $this->style->writeln($databaseRef.' ===> Start caching object '.$objectEntity->getId()->toString().' of type '.$objectEntity->getEntity()->getName());
        }

        // todo: temp fix to make sure we have the latest version of this ObjectEntity before we cache it.
        $updatedObjectEntity = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $objectEntity->getId()->toString()]);
        if ($updatedObjectEntity !== null) {
            $objectEntity = $updatedObjectEntity;
        } else if (isset($this->style) === true) {
            $this->style->writeln('Could not find an ObjectEntity with id: '.$objectEntity->getId()->toString());
        }

        // Let's not cash the entire schema
        $array = $objectEntity->toArray(['embedded' => true, 'user' => $this->getObjectUser(objectEntity: $objectEntity)]);

        // (isset($array['_schema']['$id'])?$array['_schema'] = $array['_schema']['$id']:'');
        $identification = $objectEntity->getId()->toString();

        // Add an id field to main object only if the object not already has an id field.
        if (key_exists('id', $array) === false || $array['id'] === null) {
            $array['id'] = $identification;
        }

        // Add id field to level 1 subobjects for backwards compatibility reasons.
        if (key_exists('embedded', $array) === true) {
            foreach ($array['embedded'] as $key => $subObject) {
                if (key_exists('_self', $subObject) === true && key_exists('id', $subObject) === false) {
                    $array['embedded'][$key]['id'] = $subObject['_self']['id'];
                }
            }
        }

        if ($collection->findOneAndReplace(
            ['_id' => $identification],
            $array,
            ['upsert' => true]
        )
        ) {
            isset($this->style) === true && $this->style->writeln('Updated object '.$objectEntity->getId()->toString().' of type '.$objectEntity->getEntity()->getName().' to cache');

            return $objectEntity;
        }

        isset($this->style) === true && $this->style->writeln('Wrote object '.$objectEntity->getId()->toString().' of type '.$objectEntity->getEntity()->getName().' to cache');

        return $objectEntity;

    }//end cacheObject()

    /**
     * Gets the User object of an ObjectEntity.
     *
     * @param ObjectEntity $objectEntity
     *
     * @return User|null
     */
    private function getObjectUser(ObjectEntity $objectEntity): ?User
    {
        if (Uuid::isValid($objectEntity->getOwner()) === false) {
            $this->logger->info("Owner: '{$objectEntity->getOwner()}' for Object: '{$objectEntity->getId()->toString()}' is not a valid UUID.");

            return null;
        }

        $user = $this->entityManager->getRepository('App:User')->findOneBy(['id' => $objectEntity->getOwner()]);

        if ($user === null) {
            $this->logger->warning("Could not find a User with id = {$objectEntity->getOwner()} for the owner of Object: {$objectEntity->getId()->toString()}");
        }

        return $user;

    }//end getObjectUser()

    /**
     * Removes an object from the cache.
     *
     * @param ObjectEntity $objectEntity
     * @param bool         $softDelete
     *
     * @return void
     */
    public function removeObject(ObjectEntity $objectEntity, bool $softDelete = false): void
    {
        $this->setObjectClient();
        if (isset($this->objectsClient) === true) {
            $collection = $this->objectsClient->objects->json;
        } else if ($objectEntity->getOrganization() !== null && $objectEntity->getOrganization()->getDatabase() !== null) {
            $database      = $objectEntity->getOrganization()->getDatabase();
            $objectsClient = $this->createObjectClient(database: $database);
            if ($objectsClient === null) {
                $collection = $this->client->objects->json;
            } else {
                $collection = $objectsClient->objects->json;
            }
        } else if (isset($this->client) === true) {
            $collection = $this->client->objects->json;
        } else {
            return;
        }

        // Todo: cascade remove subobjects (Check Attribute->getCascadeDelete() & Attribute->getMayBeOrphaned())
        $identification = $objectEntity->getId()->toString();

        if ($softDelete === true) {
            $now                                 = new DateTime();
            $objectArray                         = $objectEntity->toArray();
            $objectArray['_self']['dateDeleted'] = $now->format('c');

            $collection->findOneAndReplace(['_id' => $identification], ['_self' => $objectArray['_self']], ['upsert' => true]);

            return;
        }

        $collection->findOneAndDelete(['_id' => $identification]);

    }//end removeObject()

    /**
     * Get a single object from the cache.
     *
     * @param string      $identification The ID of an Object.
     * @param string|null $schema         Only look for an object with this schema.
     *
     * @return array|null
     */
    public function getObject(string $identification, string $schema = null): ?array
    {
        $this->setObjectClient();
        if (isset($this->objectsClient) === true) {
            $collection = $this->objectsClient->objects->json;
        } else {
            $objectEntity = $this->entityManager->getRepository(ObjectEntity::class)->findOneBy(['id' => $identification]);
            if ($objectEntity !== null && $objectEntity->getOrganization() !== null && $objectEntity->getOrganization()->getDatabase() !== null) {
                $database      = $objectEntity->getOrganization()->getDatabase();
                $objectsClient = $this->createObjectClient(database: $database);
                if ($objectsClient === null) {
                    $collection = $this->client->objects->json;
                } else {
                    $collection = $objectsClient->objects->json;
                }
            } else if (isset($this->client) === true) {
                $collection = $this->client->objects->json;
            } else {
                return null;
            }
        }

        if ($schema !== null) {
            if (Uuid::isValid($schema) === true) {
                // $filter['_self.schema.id'] = 'b92a3a39-3639-4bf5-b2af-c404bc2cb005';
                $filter['_self.schema.id'] = $schema;
                $entityObject              = $this->entityManager->getRepository('App:Entity')->findOneBy(['id' => $schema]);
            } else {
                // $filter['_self.schema.ref'] = 'https://larping.nl/schema/example.schema.json';
                $filter['_self.schema.ref'] = $schema;
                $entityObject               = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $schema]);
            }

            if ($entityObject === null) {
                $this->logger->warning("Could not find an Entity with id or reference = $schema during getObject($identification)");
                return null;
            }
        }

        $user = $this->objectEntityService->findCurrentUser();

        $filter = ['_id' => $identification];
        if ($user !== null) {
            if ($user->getOrganization() !== null) {
                $filter['$or'][] = ['_self.owner.id' => $user->getId()->toString()];
                $filter['$or'][] = ['_self.organization.id' => $user->getOrganization()->getId()->toString()];
                $filter['$or'][] = ['_self.organization.id' => null];
            } else {
                $filter['_self.owner.id'] = $user->getId()->toString();
            }
        }

        $this->session->set('mongoDBFilter', $filter);

        // Check if object is in the cache?
        if ($object = $collection->findOne($filter)) {
            return json_decode(json_encode($object), true);
        }

        // Fall back tot the entity manager.
        if ($object = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $identification])) {
            if ($user !== null && $object->getOwner() !== $user->getId()->toString()) {
                return null;
            }

            if ($user !== null && $user->getOrganization() !== null
                && $user->getOrganization()->getId()->toString() !== $object->getOrganization()->getId()->toString()
            ) {
                return null;
            }

            return $this->cacheObject($object)->toArray(['embedded' => true]);
        }

        return null;

    }//end getObject()

    /**
     * Make sure we still support the old query params. By translating them to the new ones with _.
     *
     * @param array $filter
     *
     * @return void
     */
    private function queryBackwardsCompatibility(array &$filter): void
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
     * Will add entity filters to the filters array.
     * Will also check if we are allowed to filter & order with the given filters and order query params.
     *
     * @param array $filter   The filter array
     * @param array $entities An array with one or more entities we are searching objects for.
     *
     * @return array|null Will return an array if any query parameters are used that are not allowed.
     */
    private function handleEntities(array &$filter, array $entities): ?array
    {
        // @todo: reenable this when checking for allowed filters and ordering is reenabled.
        // $filterCheck = $filter;
        $errorData = [];
        foreach ($entities as $entity) {
            if (Uuid::isValid($entity) === true) {
                // $filter['_self.schema.id'] = 'b92a3a39-3639-4bf5-b2af-c404bc2cb005';
                $filter['_self.schema.id']['$in'][] = $entity;
                $entityObject                       = $this->entityManager->getRepository('App:Entity')->findOneBy(['id' => $entity]);
            } else {
                // $filter['_self.schema.ref'] = 'https://larping.nl/schema/example.schema.json';
                $filter['_self.schema.ref']['$in'][] = $entity;
                $entityObject                        = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $entity]);
            }

            if ($entityObject === null) {
                $this->logger->warning("Could not find an Entity with id or reference = $entity during searchObjects()");
                continue;
            }

            // @todo: for now we do not check for allowed filters and ordering, because this breaks things.
            // Only allow ordering & filtering on attributes with sortable = true & searchable = true (respectively).
            // $orderError = $this->handleOrderCheck($entityObject, $filter['_order'] ?? null);
            // $filterError = $this->handleFilterCheck($entityObject, $filterCheck ?? null);
            $orderError  = null;
            $filterError = null;
            if (empty($orderError) === true && empty($filterError) === true) {
                continue;
            }

            $errorData[$entityObject->getName()]['order']  = $orderError ?? null;
            $errorData[$entityObject->getName()]['filter'] = $filterError ?? null;
        }//end foreach

        if (empty($errorData) === false) {
            $this->logger->warning('There are some errors in your query parameters', $errorData);

            return [
                'message' => 'There are some errors in your query parameters',
                'type'    => 'error',
                'path'    => 'searchObjects',
                // todo: get path from session?
                'data'    => $errorData,
            ];
        }

        return null;

    }//end handleEntities()

    /**
     * Adds owner and organization filters (multi tenancy) for searchObjects() or countObjects(). Or other MongoDB collection queries.
     *
     * @param array $filter The filter to add owner and organization filters to.
     *
     * @return array The updated filter (unless owner and organization filter was already present).
     */
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
     * Retrieves objects from a cache collection.
     *
     * @param array      $filter  The mongoDB query to filter with.
     * @param array|null $options Options like 'limit', 'skip' & 'sort' for the mongoDB->find query.
     *
     * @return array $this->handleResultPagination() array with objects and pagination.
     */
    public function retrieveObjectsFromCache(array $filter, ?array $options): array
    {
        $filter = $this->addOwnerOrgFilter(filter: $filter);

        $this->session->set('mongoDBFilter', $filter);

        $this->setObjectClient();
        if (isset($this->objectsClient) === true) {
            $collection = $this->objectsClient->objects->json;
        } else if (isset($this->client) === true) {
            $collection = $this->client->objects->json;
        } else {
            return [];
        }

        $total = $this->countObjectsInCache(filter: $filter);

        $results = $collection->find($filter, $options)->toArray();

        return $this->handleResultPagination(filter: $filter, results: $results, total: $total);

    }//end retrieveObjectsFromCache()

    /**
     * Backwards compatibility for the searchObjects & countObjects function with 3 arguments.
     * Todo: we should remove this as soon as all other Bundles use the new versions of these functions with just 2 parameters
     *
     * @param $function_name
     * @param $arguments
     *
     * @return array|int|void
     *
     * @throws Exception
     */
    function __call($function_name, $arguments)
    {
        $count = count($arguments);

        // Check function name for searchObjects.
        if ($function_name == 'searchObjects') {
            if ($count == 3) {
                if (empty($arguments[0]) === false && isset($arguments[1]['_search']) === false) {
                    $arguments[1]['_search'] = $arguments[0];
                }

                array_shift($arguments);
            }

            if (isset($arguments['filter']) === true) {
                return $this->searchObjectsNew($arguments['filter'], $arguments['entities']);
            }

            return $this->searchObjectsNew($arguments[0], $arguments[1]);
        }

        // Check function name for countObjects.
        if ($function_name == 'countObjects') {
            if ($count == 3) {
                if (empty($arguments[0]) === false && isset($arguments[1]['_search']) === false) {
                    $arguments[1]['_search'] = $arguments[0];
                }

                array_shift($arguments);
            }

            if (isset($arguments['filter']) === true) {
                return $this->countObjectsNew($arguments['filter'], $arguments['entities']);
            }

            return $this->countObjectsNew($arguments[0], $arguments[1]);
        }

    }//end __call()

    /**
     * Searches the object store for objects containing the search string.
     * TODO: Rename this function back to searchObjects
     *
     * @param array $filter   an array of dot.notation filters for which to search with
     * @param array $entities schemas to limit te search to
     *
     * @throws Exception
     *
     * @return array The objects found
     */
    public function searchObjectsNew(array $filter = [], array $entities = []): array
    {
        // Backwards compatibility.
        if (isset($this->client) === false) {
            return [];
        }

        $this->queryBackwardsCompatibility(filter: $filter);

        // Search for the correct entity / entities.
        if (empty($entities) === false) {
            $queryError = $this->handleEntities(filter: $filter, entities: $entities);
            if ($queryError !== null) {
                return $queryError;
            }
        }

        // Limit & Start for pagination.
        $limit = 30;
        $start = 0;
        if (isset($filter['_enablePagination']) === true && $filter['_enablePagination'] === false) {
            $limit = 500;
        } else {
            $this->setPagination(limit: $limit, start: $start, filter: $filter);
        }

        // Order.
        $order = $this->setOrder(filter: $filter);

        // Find / Search.
        return $this->retrieveObjectsFromCache(filter: $filter, options: ['limit' => $limit, 'skip' => $start, 'sort' => $order]);

    }//end searchObjectsNew()

    /**
     * Counts objects in a cache collection.
     *
     * @param array $filter The mongoDB query to filter with.
     *
     * @return int The amount of objects counted.
     */
    public function countObjectsInCache(array $filter): int
    {
        $this->session->set('mongoDBFilter', $filter);

        $this->setObjectClient();
        if (isset($this->objectsClient) === true) {
            $collection = $this->objectsClient->objects->json;
        } else if (isset($this->client) === true) {
            $collection = $this->client->objects->json;
        } else {
            return 0;
        }

        return $collection->count($filter);

    }//end countObjectsInCache()

    /**
     * Counts objects found with the given search/filter parameters.
     * TODO: Rename this function back to countObjects
     *
     * @param array $filter   an array of dot.notation filters for which to search with
     * @param array $entities schemas to limit te search to
     *
     * @throws Exception
     *
     * @return int
     */
    public function countObjectsNew(array $filter = [], array $entities = []): int
    {
        // Backwards compatibility.
        if (isset($this->client) === false) {
            return 0;
        }

        $this->queryBackwardsCompatibility(filter: $filter);

        // Search for the correct entity / entities.
        if (empty($entities) === false) {
            $queryError = $this->handleEntities(filter: $filter, entities: $entities);
            if ($queryError !== null) {
                $this->logger->error($queryError);
                return 0;
            }
        }

        // Find / Search.
        return $this->countObjectsInCache(filter: $filter);

    }//end countObjectsNew()

    /**
     * Creates an aggregation of results for possible query parameters
     *
     * @param array $filter   The filter to handle.
     * @param array $entities The entities to search in.
     *
     * @return array The resulting aggregation
     *
     * @throws Exception
     */
    public function aggregateQueries(array $filter, array $entities): array
    {
        if (isset($filter['_queries']) === false) {
            return [];
        }

        $queries = $filter['_queries'];

        if (is_array($queries) === false) {
            $queries = explode(',', $queries);
        }

        $this->queryBackwardsCompatibility(filter: $filter);

        // Search for the correct entity / entities.
        if (empty($entities) === false) {
            $queryError = $this->handleEntities(filter: $filter, entities: $entities);
            if ($queryError !== null) {
                return $queryError;
            }
        }

        $result = [];
        $this->setObjectClient();
        if (isset($this->objectsClient) === true) {
            $collection = $this->objectsClient->objects->json;
        } else if (isset($this->client) === true) {
            $collection = $this->client->objects->json;
        } else {
            return $result;
        }

        if ($collection instanceof MongoDbCollection === true) {
            foreach ($queries as $query) {
                $result[$query] = $collection->aggregate([['$match' => $filter], ['$unwind' => "\${$query}"], ['$group' => ['_id' => "\${$query}", 'count' => ['$sum' => 1]]]])->toArray();
            }
        } else if ($collection instanceof ElasticSearchCollection === true) {
            unset($filter['_queries']);
            $result = $collection->aggregate([$filter, $queries])->toArray();
        }

        return $result;

    }//end aggregateQueries()

    // /**
    // * Will check if we are allowed to order with the given $order query param.
    // * Uses ObjectEntityRepository->getOrderParameters() to check if we are allowed to order, see eavService->handleSearch() $orderCheck.
    // *
    // * @param Entity           $entity The entity we are going to check for allowed attributes to order on.
    // * @param mixed|array|null $order  The order query param, should be an array or null. (but could be a string)
    // *
    // * @return string|null Returns null if given order query param is correct/allowed or when it is not present. Else an error message.
    // */
    // private function handleOrderCheck(Entity $entity, $order): ?string
    // {
    // if (empty($order)) {
    // return null;
    // }
    // This checks for each attribute of the given Entity if $attribute->getSortable() is true.
    // $orderCheck = $this->entityManager->getRepository('App:ObjectEntity')->getOrderParameters($entity, '', 1, true);
    // if (is_array($order) === false) {
    // $orderCheckStr = implode(', ', $orderCheck);
    // $message       = 'Please give an attribute to order on. Like this: ?_order[attributeName]=desc/asc. Supported order query parameters: '.$orderCheckStr;
    // }
    // if (is_array($order) === true && count($order) > 1) {
    // $message = 'Only one order query param at the time is allowed.';
    // }
    // if (is_array($order) === true && in_array(strtoupper(array_values($order)[0]), ['DESC', 'ASC']) === false) {
    // $message = 'Please use desc or asc as value for your order query param, not: '.array_values($order)[0];
    // }
    // if (is_array($order) === true && in_array(array_keys($order)[0], $orderCheck) === false) {
    // $orderCheckStr = implode(', ', $orderCheck);
    // $message       = 'Unsupported order query parameter ('.array_keys($order)[0].'). Supported order query parameters: '.$orderCheckStr;
    // }
    // if (isset($message) === true) {
    // return $message;
    // }
    // return null;
    // }//end handleOrderCheck()
    // /**
    // * @TODO reenable when this function is used again.
    // * Will check if we are allowed to filter on the given $filters in the query params.
    // * Uses ObjectEntityRepository->getFilterParameters() to check if we are allowed to filter, see eavService->handleSearch() $filterCheck.
    // *
    // * @param Entity     $entity  The entity we are going to check for allowed attributes to filter on.
    // * @param array|null $filters The filters from query params.
    // *
    // * @return string|null Returns null if all filters are allowed or if none are present. Else an error message.
    // */
    // private function handleFilterCheck(Entity $entity, ?array $filters): ?string
    // {
    // if (empty($filters) === true) {
    // return null;
    // }
    // This checks for each attribute of the given Entity if $attribute->getSearchable() is true.
    // $filterCheck = $this->entityManager->getRepository('App:ObjectEntity')->getFilterParameters($entity, '', 1, true);
    // foreach (array_keys($filters) as $param) {
    // if (in_array($param, $filterCheck) === false) {
    // $unsupportedParams = isset($unsupportedParams) === false ? $param : "$unsupportedParams, $param";
    // }
    // }
    // if (isset($unsupportedParams) === true) {
    // $filterCheckStr = implode(', ', $filterCheck);
    // return 'Unsupported queryParameters ('.$unsupportedParams.'). Supported queryParameters: '.$filterCheckStr;
    // }
    // return null;
    // }//end handleFilterCheck()

    /**
     * Decides the pagination values.
     *
     * @param int   $limit  The resulting limit
     * @param int   $start  The resulting start value
     * @param array $filter The filters
     *
     * @return array
     */
    public function setPagination(int &$limit, int &$start, array $filter): array
    {
        if (isset($filter['_limit']) === true) {
            $limit = (int) $filter['_limit'];
        }

        if (isset($filter['_start']) === true) {
            $start = (int) $filter['_start'];
        } else if (isset($filter['_offset']) === true) {
            $start = (int) $filter['_offset'];
        } else if (isset($filter['_page']) === true) {
            $start = (((int) $filter['_page'] - 1) * $limit);
        }

        return $filter;

    }//end setPagination()

    /**
     * Decides the order value.
     *
     * @param array $filter The filters
     *
     * @return array
     */
    private function setOrder(array $filter): array
    {
        $order = [];

        if (isset($filter['_order']) === true) {
            $order = str_replace(
                [
                    'asc',
                    'desc',
                ],
                [
                    1,
                    -1,
                ],
                array_map('strtolower', $filter['_order'])
            );

            $order = array_map(
                function ($value) {
                    return (int) $value;
                },
                $order
            );
        }

        return $order;

    }//end setOrder()

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
        if (isset($filter['_enablePagination']) === true && $filter['_enablePagination'] === false) {
            return $results;
        }

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

    /**
     * Put a single endpoint into the cache.
     *
     * @param Endpoint $endpoint
     *
     * @return Endpoint
     */
    public function cacheEndpoint(Endpoint $endpoint): Endpoint
    {
        // Backwards compatibility.
        if (isset($this->client) === false) {
            return $endpoint;
        }

        if (isset($this->style) === true) {
            $this->style->writeln('Start caching endpoint '.$endpoint->getId()->toString().' with name: '.$endpoint->getName());
        }

        $updatedEndpoint = $this->entityManager->getRepository('App:Endpoint')->find($endpoint->getId());
        if ($updatedEndpoint !== null) {
            $endpoint = $updatedEndpoint;
        } else if (isset($this->style) === true) {
            $this->style->writeln('Could not find an Endpoint with id: '.$endpoint->getId()->toString());
        }

        $collection = $this->client->endpoints->json;

        $endpointArray        = $this->serializer->normalize($endpoint, null, [AbstractNormalizer::IGNORED_ATTRIBUTES => ['object', 'inversedBy']]);
        $endpointArray['_id'] = $endpointArray['id'];

        if ($collection->findOneAndReplace(
            ['id' => $endpoint->getId()->toString()],
            $endpointArray,
            ['upsert' => true]
        )
        ) {
            isset($this->style) === true && $this->style->writeln('Updated endpoint '.$endpoint->getId()->toString().' to cache');
        } else {
            isset($this->style) === true && $this->style->writeln('Wrote object '.$endpoint->getId()->toString().' to cache');
        }

        return $endpoint;

    }//end cacheEndpoint()

    /**
     * Removes an endpoint from the cache.
     *
     * @param Endpoint $endpoint
     *
     * @return void
     */
    public function removeEndpoint(Endpoint $endpoint): void
    {
        // Backwards compatibility.
        if (isset($this->client) === false) {
            return;
        }

        $collection = $this->client->endpoints->json;

        $collection->findOneAndDelete(['id' => $endpoint->getId()->toString()]);

    }//end removeEndpoint()

    /**
     * Get a single endpoint from the cache.
     *
     * @param Uuid $identification
     *
     * @return array|null
     */
    public function getEndpoint(string $identification): ?array
    {
        // Backwards compatibility.
        if (isset($this->client) === false) {
            return [];
        }

        $collection = $this->client->endpoints->json;

        if ($object = $collection->findOne(['id' => $identification])) {
            return $object;
        }

        if ($object = $this->entityManager->getRepository('App:Endpoint')->find($identification)) {
            return $this->serializer->normalize($object);
        }

        return null;

    }//end getEndpoint()

    public function getEndpoints(array $filter): ?Endpoint
    {
        // Backwards compatibility.
        if (isset($this->client) === false) {
            return [];
        }

        $collection = $this->client->endpoints->json;

        if (isset($filter['path']) === true) {
            $path             = $filter['path'];
            $filter['$where'] = "\"$path\".match(this.pathRegex)";
            unset($filter['path']);
        }

        if (isset($filter['method']) === true) {
            $method        = $filter['method'];
            $filter['$or'] = [
                ['methods' => ['$in' => [$method]]],
                ['method' => $method],
            ];
            unset($filter['method']);
        }

        $endpoints = $collection->find($filter)->toArray();

        if (count($endpoints) > 1) {
            throw new NonUniqueResultException();
        } else if (count($endpoints) == 1) {
            // @TODO: We actually want to use the denormalizer, but that breaks on not setting ids.
            return $this->entityManager->find('App\Entity\Endpoint', $endpoints[0]['id']);
        } else {
            return null;
        }

    }//end getEndpoints()

    /**
     * Put a single schema into the cache.
     *
     * @param Entity $entity
     *
     * @return Entity
     */
    public function cacheShema(Entity $entity): Entity
    {
        // Backwards compatibility.
        if (isset($this->client) === false) {
            return $entity;
        }

        // Remap the array.
        $array              = $entity->toSchema(null);
        $array['reference'] = $array['$id'];
        $array['schema']    = $array['$schema'];
        unset($array['$id']);
        unset($array['$schema']);

        // var_dump($array);
        // $collection = $this->client->schemas->json;
        // if ($collection->findOneAndReplace(
        // ['_id' => $entity->getID()],
        // $entity->toSchema(null),
        // ['upsert' => true]
        // )) {
        // $this->style->writeln('Updated object '.$entity->getId().' to cache');
        // } else {
        // $this->style->writeln('Wrote object '.$entity->getId().' to cache');
        // }
        return $entity;

    }//end cacheShema()

    // /**
    // * Removes an Schema from the cache.
    // *
    // * @param Entity $entity
    // *
    // * @return void
    // */
    // public function removeSchema(Entity $entity): void
    // {
    // @TODO remove entity from cache.
    // Backwards compatibility
    // if (isset($this->client) === false) {
    // return;
    // }
    // }//end removeSchema()
    // /**
    // * Get a single schema from the cache.
    // *
    // * @param Uuid $identification
    // *
    // * @return array|null
    // */
    // public function getSchema(Uuid $identification): ?array
    // {
    // @TODO get entity from cache.
    // Backwards compatibility
    // if (isset($this->client) === false) {
    // return [];
    // }
    // }//end getSchema()
}//end class
