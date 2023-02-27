<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Action;
use App\Entity\Application;
use App\Entity\CollectionEntity;
use App\Entity\Cronjob;
use App\Entity\DashboardCard;
use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\Gateway as Source;
use App\Entity\Mapping;
use App\Entity\ObjectEntity;
use App\Entity\Organization;
use App\Entity\SecurityGroup;
//use App\Entity\User;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Exception;

/**
 * The installation service is used to install plugins (or actually symfony bundles) on the gateway.
 *
 * This class breacks complixity,methods and coupling rules. This could be solved by devidng the class into smaller classes but that would deminisch the readbilly of the code as a whole. All the code in this class is only used in an installation context and it makes more sence to keep it together. Therefore a design decicion was made to keep al this code in one class.
 *
 * @author Ruben van der Linde
 */
class InstallationService
{
    /**
     * @var ComposerService
     */
    private ComposerService $composerService;

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var ContainerInterface
     */
    private ContainerInterface $container;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var Filesystem
     */
    private Filesystem $filesystem;

    /**
     * @var SchemaService
     */
    private SchemaService $schemaService;

    /**
     * @var array The Objects aquired durring a installation
     */
    private array $objects = [];

    /**
     * @codeCoverageIgnore We do not need to test constructors
     *
     * @param ComposerService        $composerService The Composer service
     * @param EntityManagerInterface $entityManager   The entity manager
     * @param Kernel                 $kernel          The kernel
     * @param SchemaService          $schemaService   The schema service
     */
    public function __construct(
        ComposerService $composerService,
        EntityManagerInterface $entityManager,
        Kernel $kernel,
        SchemaService $schemaService,
        LoggerInterface $installationLogger
    ) {
        $this->composerService = $composerService;
        $this->entityManager = $entityManager;
        $this->container = $kernel->getContainer();
        $this->collection = null;
        $this->logger = $installationLogger;
        $this->schemaService = $schemaService;
        $this->filesystem = new Filesystem();
    }//end __construct()

    /**
     * Updates all commonground bundles on the common gateway installation.
     *
     * This functions serves as the jump of point for the `commengateway:plugins:update` command
     *
     * @param array $config The (optional) configuration
     *
     * @return int
     */
    public function update(array $config = []): int
    {
        // Let's see if we are trying to update a single plugin.
        if (isset($config['plugin']) === true) {
            $this->logger->debug('Running plugin installer for a single plugin: '.$config['plugin']);
            $this->install($config['plugin'], $config);

            return Command::SUCCESS;
        }

        // If we don't want to update a single plugin then we want to install al the plugins.
        $plugins = $this->composerService->getAll();

        $this->logger->debug('Running plugin installer for all plugins');

        foreach ($plugins as $plugin) {
            $this->install($plugin['name'], $config);
        }

        return Command::SUCCESS;
    }//end update()

    /**
     * Installs the files from a bundle.
     *
     * Based on the default action handler so schould supoprt a config parrameter even if we do not use it
     *
     * @param string $bundle The bundle
     * @param array  $config Optional config (ignored on this function)
     *
     * @return bool The result of the installation
     */
    public function install(string $bundle, array $config = []): bool
    {
        $this->logger->debug('Installing plugin '.$bundle, ['plugin' => $bundle]);

        $vendorFolder = 'vendor';

        // First we want to read all the files so that we have all the content we should install.
        $this->logger->debug('Installing plugin '.$bundle);

        // Let's check the basic folders for legacy purposes. todo: remove these at some point
        $this->readDirectory($vendorFolder.'/'.$bundle.'/Action');
        $this->readDirectory($vendorFolder.'/'.$bundle.'/Schema'); // Entity
        $this->readDirectory($vendorFolder.'/'.$bundle.'/Source'); // Gateway
        $this->readDirectory($vendorFolder.'/'.$bundle.'/Mapping');
        $this->readDirectory($vendorFolder.'/'.$bundle.'/Data');
        // A function that translates old core schema references to the new ones. Only here for backwards compatibility.
        $this->translateCoreReferences();

        // Then the folder where everything should be.
        $this->readDirectory($vendorFolder.'/'.$bundle.'/Installation');
        
        // Handling al the found  files.
        $this->logger->debug('Found '.count($this->objects).' schema types for '.$bundle, ['bundle' => $bundle]);

        // There is a certain order to this, meaning that we want to handle certain schema types before other schema types.
        if (isset($this->objects['https://docs.commongateway.nl/schemas/Entity.schema.json']) === true && is_array($this->objects['https://docs.commongateway.nl/schemas/Entity.schema.json']) === true) {
            $schemas = $this->objects['https://docs.commongateway.nl/schemas/Entity.schema.json'];
            $this->logger->debug('Found '.count($schemas).' objects types for schema https://docs.commongateway.nl/schemas/Entity.schema.json', ['bundle' => $bundle, 'reference' => 'https://docs.commongateway.nl/schemas/Entity.schema.json']);
            $this->handleObjectType('https://docs.commongateway.nl/schemas/Entity.schema.json', $schemas);
            unset($this->objects['https://docs.commongateway.nl/schemas/Entity.schema.json']);
        }
    
        // Save the entities to the database.
        $this->entityManager->flush();

        // Handle all the other objects.
        foreach ($this->objects as $ref => $schemas) {
            $this->logger->debug('Found '.count($schemas).' objects types for schema '.$ref, ['bundle' => $bundle, 'reference' => $ref]);
            $this->handleObjectType($ref, $schemas);
            unset($this->objects[$ref]);
        }

        // Save the all other objects to the database.
        $this->entityManager->flush();
    
        // Find and handle the installation.json file.
        if ($this->filesystem->exists($vendorFolder.'/'.$bundle.'/Installation/installation.json') !== false) {
            $finder = new Finder();
            $files = $finder->in($vendorFolder.'/'.$bundle.'/Installation')->files()->name('installation.json');
            if (count($files) === 1) {
                $this->logger->debug('Found an installation.json file', ['bundle' => $bundle]);
                foreach ($files as $file) {
                    $this->handleInstaller($file);
                }
            } else {
                $this->logger->debug('Found '.count($files).' installation.json files', ['location' => $vendorFolder.'/'.$bundle.'/Installation']);
            }
        }

        // Save the objects created during handling installation.json to the database.
        $this->entityManager->flush();

        $this->logger->debug('All Done installing plugin '.$bundle, ['bundle' => $bundle]);

        return true;
    }//end install()
    
    /**
     * For backwards compatibility, support old core schema reference and translate them to the new ones.
     * Todo: remove this function when we no longer need it
     *
     * @return void
     */
    private function translateCoreReferences()
    {
        foreach ($this->objects as $translateFrom => $value) {
            switch ($translateFrom) {
                case 'https://json-schema.org/draft/2020-12/action':
                    $translateTo = 'https://docs.commongateway.nl/schemas/Action.schema.json';
                    break;
                case 'https://json-schema.org/draft/2020-12/schema':
                    $translateTo = 'https://docs.commongateway.nl/schemas/Entity.schema.json';
                    break;
                case 'https://json-schema.org/draft/2020-12/source':
                    $translateTo = 'https://docs.commongateway.nl/schemas/Gateway.schema.json';
                    break;
                case 'https://json-schema.org/draft/2020-12/mapping':
                    $translateTo = 'https://docs.commongateway.nl/schemas/Mapping.schema.json';
                    break;
                default:
                    continue 2;
            }
            if (isset($this->objects[$translateTo]) === false) {
                $this->objects[$translateTo] = [];
            }
            $this->objects[$translateTo] = array_merge($this->objects[$translateTo], $this->objects[$translateFrom]);
            unset($this->objects[$translateFrom]);
        }
    }//end translateCoreReferences()

    /**
     * This function read a folder to find other folders or json objects.
     *
     * @param string $location The location of the folder
     *
     * @return bool Whether the function was successfully executed
     */
    private function readDirectory(string $location): bool
    {

        // Let's see if the folder exists to start with.
        if ($this->filesystem->exists($location) === false) {
            $this->logger->debug('Installation folder not found', ['location' => $location]);

            return false;
        }

        // Get the folder content.
        $hits = new Finder();
        $hits = $hits->in($location);

        // Handle files.
        $this->logger->debug('Found '.count($hits->files()).' files for installer', ['location' => $location, 'files' => count($hits->files())]);
    
        if (count($hits->files()) > 34) {
            $this->logger->critical('Found more than 34 files in directory, try limiting your files to 32 per directory. Or you won\'t be able to load in these schema\'s locally on a windows machine.', ['location' => $location, 'files' => count($hits->files())]);
        } elseif (count($hits->files()) > 32) {
            $this->logger->error('Found more than 32 files in directory, try limiting your files to 32 per directory. Or you won\'t be able to load in these schema\'s locally on a windows machine.', ['location' => $location, 'files' => count($hits->files())]);
        } elseif (count($hits->files()) > 25) {
            $this->logger->warning('Found more than 25 files in directory, try limiting your files to 32 per directory. Or you won\'t be able to load in these schema\'s locally on a windows machine.', ['location' => $location, 'files' => count($hits->files())]);
        }

        foreach ($hits->files() as $file) {
            if ($file->getFilename() === 'installation.json') {
                continue;
            }
            $this->readfile($file);
        }

        return true;
    }//end readDirectory()

    /**
     * This function read a folder to find other folders or json objects.
     *
     * @param SplFileInfo $file The file location
     *
     * @return bool|array The file contents, or false if content could not be established
     */
    private function readfile(SplFileInfo $file)
    {

        // Check if it is a valid json object.
        $mappingSchema = json_decode($file->getContents(), true);
        if (empty($mappingSchema) === true) {
            $this->logger->error($file->getFilename().' is not a valid json object');

            return false;
        }

        // Todo: validateJsonMapping does not exist
//        // Check if it is a valid schema.
//        $mappingSchema = $this->validateJsonMapping($mappingSchema);
//
//        if ($this->validateJsonMapping($mappingSchema) === true) {
//            $this->logger->error($file->getFilename().' is not a valid json-mapping object');
//
//            return false;
//        }

        // Add the file to the object.
        return $this->addToObjects($mappingSchema);
    }//end readfile()

    /**
     * Adds an object to the objects stack if it is valid.
     *
     * @param array $schema The schema
     *
     * @return bool|array The file contents, or false if content could not be established
     */
    private function addToObjects(array $schema)
    {

        // It is a schema so lets save it like that.
        if (array_key_exists('$schema', $schema) === true) {
            $this->objects[$schema['$schema']][] = $schema;

            return $schema;
        }

        // If it is not a schema of itself it might be an array of objects.
        foreach ($schema as $key => $value) {
            if (is_array($value) === true) {
                $this->objects[$key][] = $value;
                continue;
            }

            // The use of gettype is discouraged, but we don't use it as a bl here and only for logging text purposes. So a design decision was made te allow it.
            $this->logger->error('Expected to find array for schema type '.$key.' but found '.gettype($value).' instead', ['value' => $value, 'schema' => $key]);
        }

        return true;
    }//end addToObjects()
    
    /**
     * Handles schemas of a certain type.
     *
     * @param string $type The type of the object
     * @param array $schemas The schemas to handle
     *
     * @return void
     */
    private function handleObjectType(string $type, array $schemas): void
    {
        foreach ($schemas as $schema) {
            $object = $this->handleObject($type, $schema);
            if ($object === null) {
                continue;
            }
            
            // Save it to the database.
            $this->entityManager->persist($object);
        }

    }//end handleObjectType();

    /**
     * Create an object bases on a type and a schema (the object as an array).
     *
     * This function breaks complexity rules, but since a switch is the most effective way of doing it a design decision was made to allow it
     *
     * @param string $type   The type of the object
     * @param array  $schema The object as an array
     *
     * @return object|null
     */
    private function handleObject(string $type, array $schema): ?object
    {
        // Only base we need it the assumption that on object isn't valid until we made is so.
        $object = null;

        // For security reasons we define allowed resources.
        $allowedCoreObjects = [
            'https://docs.commongateway.nl/schemas/Action.schema.json',
            'https://docs.commongateway.nl/schemas/Application.schema.json',
            'https://docs.commongateway.nl/schemas/CollectionEntity.schema.json',
            'https://docs.commongateway.nl/schemas/Cronjob.schema.json',
            'https://docs.commongateway.nl/schemas/DashboardCard.schema.json',
            'https://docs.commongateway.nl/schemas/Endpoint.schema.json',
            'https://docs.commongateway.nl/schemas/Entity.schema.json',
            'https://docs.commongateway.nl/schemas/Gateway.schema.json',
            'https://docs.commongateway.nl/schemas/Mapping.schema.json',
            'https://docs.commongateway.nl/schemas/Organization.schema.json',
            'https://docs.commongateway.nl/schemas/SecurityGroup.schema.json',
//                'https://docs.commongateway.nl/schemas/User.schema.json',
        ];

        // Handle core schema's.
        if (in_array($type, $allowedCoreObjects) === true) {
            $object = $this->loadCoreSchema($schema, $type);
        }//end if

        // Handle Other schema's.
        if (in_array($type, $allowedCoreObjects) === false) {
            $object = $this->loadSchema($schema, $type);
        }//end if

        // Make sure not to continue on errors.
        if ($object === null) {
            return null;
        }
        
        // Let's see if it is a new object.
        if ($this->entityManager->contains($object) === false) {
            $this->logger->info(
                'A new object has been created trough the installation service',
                [
                    'class'  => get_class($object),
                    // If you get a "::$id must not be accessed before initialization" error here, remove type UuidInterface from the class^ $id declaration. Something to do with read_secure I think.
                    'id'     => $object->getId(),
                    'object' => method_exists(get_class($object), 'toSchema') === true ? $object->toSchema() : 'toSchema function does not exists.',
                ]
            );
        }

        return $object;
    }//end handleObject()

    /**
     * This function loads a core schema.
     *
     * @param array  $schema The schema
     * @param string $type   The type of the schema
     *
     * @return mixed The loaded object
     */
    private function loadCoreSchema(array $schema, string $type): ?object
    {
        // Cleanup the type / core schema reference.
        $matchesCount = preg_match('/^https:\/\/docs\.commongateway\.nl\/schemas\/([#A-Za-z]+)\.schema\.json(|\?((|,)[^,=]+=[^,=]+)+)$/', $type, $matches);
        if ($matchesCount === 0) {
            $this->logger->error('Can\'t find schema type in this core schema reference: '.$type);
            return null;
        }
        $type = $matches[1];
        $query = explode(',', ltrim($matches[2], '?'));
    
        // Load it if we have it.
        if (array_key_exists('$id', $schema) === true) {
            $object = $this->entityManager->getRepository('App:'.$type)->findOneBy(['reference' => $schema['$id']]);
        }
    
        // Create it if we don't.
        if (isset($object) === false || $object === null) {
            $object = $this->createNewObjectType($type);
            if ($object === null) {
                $this->logger->error('Unsupported type for creating a new core object from a schema', ['type' => $type]);
                return null;
            }
        }
    
        // Make sure we have a fromSchema function for this type of object.
        if (method_exists(get_class($object), 'fromSchema') === false) {
            $this->logger->critical('fromSchema function does not exists for this core schema type: '.get_class($object));
            return null;
        }
    
        // Load the data. Todo: these version compare checks don't look right...
        if (array_key_exists('version', $schema) === true && version_compare($schema['version'], $object->getVersion()) <= 0) {
            $this->logger->debug('The new mapping has a version number equal or lower then the already present version, the object is NOT updated', ['schemaVersion' => $schema['version'], 'objectVersion' => $object->getVersion()]);
        } elseif (array_key_exists('version', $schema) === true && version_compare($schema['version'], $object->getVersion()) < 0) {
            $this->logger->debug('The new mapping has a version number higher then the already present version, the object data is updated', ['schemaVersion' => $schema['version'], 'objectVersion' => $object->getVersion()]);
            $object->fromSchema($schema);
        } elseif (array_key_exists('version', $schema) === false || $object->getVersion() === null) {
            $this->logger->debug('The new mapping doesn\'t have a version number, the object data is created', ['schemaVersion' => $schema['version'], 'objectVersion' => $object->getVersion()]);
            $object->fromSchema($schema);
        }
    
        return $object;
    }//end loadCoreSchema()
    
    /**
     * Creates a new object of the given type.
     *
     * @param string $type The type to create an object of.
     * @return object|null The new Object or null if the type is not supported.
     */
    private function createNewObjectType(string $type): ?object
    {
        switch ($type) {
            case 'Action':
                return new Action();
            case 'Application':
                return new Application();
            case 'CollectionEntity':
                return new CollectionEntity();
            case 'Cronjob':
                return new Cronjob();
            case 'DashboardCard':
                return new DashboardCard();
            case 'Endpoint':
                return new Endpoint();
            case 'Entity':
                return new Entity();
            case 'Gateway':
                return new Source();
            case 'Mapping':
                return new Mapping();
            case 'Organization':
                return new Organization();
            case 'SecurityGroup':
                return new SecurityGroup();
//            case 'User':
//                return new User();
            default:
                return null;
        }
    }//end createNewObjectType()

    /**
     * This function loads an non-core schema.
     *
     * @param array  $schema The schema
     * @param string $type   The type of the schema
     *
     * @return ObjectEntity|null The loaded object or null on error.
     */
    private function loadSchema(array $schema, string $type): ?ObjectEntity
    {
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $type]);
        if ($entity === null) {
            $this->logger->error('trying to create data for non-existing entity', ['reference' => $type]);

            return null;
        }

        // If we have an id let try to grab an object.
        if (array_key_exists('id', $schema) === true) {
            $object = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $schema['$id']]);
        }

        // Create it if we don't.
        if (isset($object) === false || $object === null) {
            $object = new ObjectEntity($entity);
        }

        // Now it gets a bit specif but for EAV data we allow nested fixed id's so let dive deep.
        if ($this->entityManager->contains($object) === false && (array_key_exists('id', $schema) === true || array_key_exists('_id', $schema) === true)) {
            $object = $this->schemaService->hydrate($object, $schema);
        }

        // EAV objects arn't cast from schema but hydrated from array's.
        $object->hydrate($schema);

        return $object;
    }//end loadSchema()

    /**
     * Specifcially handles the installation file.
     *
     * @param SplFileInfo $file The installation file.
     *
     * @return bool
     */
    private function handleInstaller(SplFileInfo $file): bool
    {
        $data = json_decode($file->getContents(), true);

        if (empty($data) === true) {
            $this->logger->error($file->getFilename().' is not a valid json object');

            return false;
        }
    
        // Endpoints for schema's and/or sources.
        if (isset($data['endpoints']) === true) {
            $this->createEndpoints($data['endpoints']);
        }

        // Actions for action handlers.
        if (isset($data['actions']['handlers']) === true) {
            $this->createActions($data['actions']['handlers']);
        }

        // Cronjobs for actions for action handlers.
        if (isset($data['cronjobs']['actions']) === true) {
            $this->createCronjobs($data['cronjobs']['actions']);
        }

        // Lets see if we have things that we want to create cards for stuff (Since this might create cards for the stuff above this should always be last).
        if (isset($data['cards']) === true) {
            $this->createCards($data['cards']);
        }

        if (isset($data['installationService']) === false || empty($data['installationService']) === true) {
            $this->logger->error($file->getFilename().' Doesn\'t contain an installation service');

            return false;
        }

        $installationService = $data['installationService'];
        try {
            $installationService = $this->container->get($installationService);
        } catch (Exception $exception) {
            $error = "{$file->getFilename()} Could not be loaded from container: {$exception->getMessage()}";
        }
        if (empty($installationService) === true || isset($error) === true) {
            $this->logger->error($error ?? "{$file->getFilename()} Could not be loaded from container");
            
            return false;
        }

        try {
            $install = $installationService->install();
            return is_bool($install) ? $install : empty($install) === false;
        } catch (\Throwable $throwable) {
            $this->logger->critical("Failed to install installationService {$data['installationService']}: {$throwable->getMessage()}", ['file' => $throwable->getFile(), 'line' => $throwable->getLine()]);
        
            return false;
        }
    }//end handleInstaller()
    
    /**
     * This functions creates dashboard cars for an array of endpoints, sources, schema's or objects.
     *
     * @param array $cardsData An array of data used for creating dashboardCards.
     *
     * @return array An array of dashboardCard objects
     */
    private function createCards(array $cardsData = []): array
    {
        $cards = [];

        // Let's loop through the cardsData.
        foreach ($cardsData as $type => $references) {
            // Let's determine the proper repo to use.
            switch ($type) {
                case 'actions':
                    $repository = $this->entityManager->getRepository('App:Action');
                    break;
                case 'applications':
                    $repository = $this->entityManager->getRepository('App:Application');
                    break;
                case 'collections':
                    $repository = $this->entityManager->getRepository('App:CollectionEntity');
                    break;
                case 'cronjobs':
                    $repository = $this->entityManager->getRepository('App:Cronjob');
                    break;
                case 'endpoints':
                    $repository = $this->entityManager->getRepository('App:Endpoint');
                    break;
                case 'schemas':
                    $repository = $this->entityManager->getRepository('App:Entity');
                    break;
                case 'sources':
                    $repository = $this->entityManager->getRepository('App:Gateway');
                    break;
                case 'mappings':
                    $repository = $this->entityManager->getRepository('App:Mapping');
                    break;
                case 'objects':
                    $repository = $this->entityManager->getRepository('App:ObjectEntity');
                    break;
                case 'organizations':
                    $repository = $this->entityManager->getRepository('App:Organization');
                    break;
//                case 'securityGroups':
//                    $repository = $this->entityManager->getRepository('App:SecurityGroup');
//                    break;
//                case 'users':
//                    $repository = $this->entityManager->getRepository('App:User');
//                    break;
                default:
                    // We can't do anything so...
                    $this->logger->error('Unknown type used for the creation of a dashboard card: '.$type);
                    continue 2;
            }//end switch

            // Then we can handle some data.
            foreach ($references as $reference) {
                $object = $repository->findOneBy(['reference' => $reference]);

                if ($object === null) {
                    $this->logger->error('No object found for '.$reference.' while trying to create a DashboardCard.');
                    continue;
                }
                
                // Check if this dashboardCard already exists.
                $dashboardCard = $this->entityManager->getRepository('App:DashboardCard')->findOneBy(['entity' => get_class($object), 'entityId' => $object->getId()]);
                if ($dashboardCard !== null) {
                    $this->logger->debug('DashboardCard found for ' . get_class($object) . ' with id: ' . $object->getId());
                    continue;
                }

                $dashboardCard = new DashboardCard($object);
                $cards[] = $dashboardCard;
                $this->entityManager->persist($dashboardCard);
                $this->logger->debug('Dashboard Card created for '.$reference);
            }

        }//end foreach

        $this->logger->info(count($cards).' Cards Created');

        return $cards;
    }//end createCards()

    /**
     * This function creates endpoints for an array of schema references or source references.
     *
     * @param array $endpointsData An array of data used for creating endpoints.
     *
     * @return array An array of endpoints
     */
    private function createEndpoints(array $endpointsData = []): array
    {
        $endpoints = [];
        
        // Let's loop through the endpointsData.
        foreach ($endpointsData as $type => $endpointTypeData) {
            // Let's determine the proper repo to use.
            switch ($type) {
                case 'schemas':
                    $repository = $this->entityManager->getRepository('App:Entity');
                    break;
                case 'sources':
                    $repository = $this->entityManager->getRepository('App:Gateway');
                    break;
                default:
                    // We can't do anything so...
                    $this->logger->error('Unknown type used for endpoint creation: '.$type);
                    continue 2;
            }//end switch
        
            // Then we can handle some data.
            foreach ($endpointTypeData as $endpointData) {
                $object = $repository->findOneBy(['reference' => $endpointData['reference']]);
            
                if ($object === null) {
                    $this->logger->error('No object found for '.$endpointData['reference'].' while trying to create an Endpoint.', ['type' => $type]);
                    continue;
                }
    
                $criteria = $type === 'sources' ? ['proxy' => $object] : ['entity' => $object];
                $endpoint = $this->entityManager->getRepository('App:Endpoint')->findOneBy($criteria);
                if ($endpoint !== null) {
                    $this->logger->debug('Endpoint found for '.$endpointData['reference']);
                    continue;
                }
    
                // todo ? maybe create a second constructor?
                $endpoint = $type === 'sources' ? new Endpoint(null, $object, $endpointData) : new Endpoint($object, null, $endpointData);
                $endpoints[] = $endpoint;
                $this->entityManager->persist($endpoint);
                $this->logger->debug('Endpoint created for '.$endpointData['reference']);
            }
        
        }//end foreach

        $this->logger->info(count($endpoints).' Endpoints Created');

        return $endpoints;
    }//end createEndpoints()

    /**
     * This functions creates actions for an array of handlers.
     *
     * @param array $handlers An array of references of handlers for wih actions schould be created
     *
     * @return array An array of Action objects
     */
    private function createActions(array $handlers = []): array
    {
        $actions = [];

        foreach ($handlers as $handler) {
            $actionHandler = $this->container->get($handler);

            $action = $this->entityManager->getRepository('App:Action')->findOneBy(['class' => get_class($actionHandler)]);
            if ($action !== null) {
                $this->logger->debug('Action found for '.$handler.' with class '.get_class($actionHandler));
                continue;
            }

            $schema = $actionHandler->getConfiguration();
            if ($schema === false && empty($schema) === true) {
                $this->logger->error('Handler '.$handler.'has no configuration');
                continue;
            }

            $action = new Action($actionHandler);
            $this->entityManager->persist($action);
            $actions[] = $action;
            $this->logger->debug('Action created for '.$handler.' with class '.get_class($actionHandler));
        }

        $this->logger->info(count($actions).' Actions Created');

        return $actions;
    }//end createActions()

    /**
     * This function creates cronjobs for an array of action references.
     *
     * @param array $actions An array of references of actions for wih actions cronjobs be created
     *
     * @return array An array of cronjobs
     */
    private function createCronjobs(array $actions = []): array
    {
        $cronjobs = [];

        foreach ($actions as $reference) {
            $action = $this->entityManager->getRepository('App:Action')->findOneBy(['reference' => $reference]);

            if ($action === null) {
                $this->logger->error('No action found for reference '.$reference);
                continue;
            }

            $cronjob = new Cronjob($action);
            $this->entityManager->persist($cronjob);
            $cronjobs[] = $cronjob;
            $this->logger->debug('Cronjob created for action '.$reference);
        }

        $this->logger->info(count($cronjobs).' Cronjobs Created');

        return $cronjobs;
    }//end createCronjobs()
}//end class
