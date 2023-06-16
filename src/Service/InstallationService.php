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
use App\Entity\User;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * The installation service is used to install plugins (or actually symfony bundles) on the gateway.
 *
 * This class breaks complexity, methods and coupling rules. This could be solved by deviding the class into smaller classes but that would deminisch the readability of the code as a whole. All the code in this class is only used in an installation context, and it makes more sense to keep it together. Therefore, a design decision was made to keep al this code in one class.
 *
 * @Author Ruben van der Linde <ruben@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>, Barry Brands <barry@conduction.nl>, Robert Zondervan <robert@conduction.nl>, Sarai Misidjan <sarai@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
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
     * @var GatewayResourceService
     */
    private GatewayResourceService $resourceService;

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
     * @var CacheService
     */
    private CacheService $cacheService;

    /**
     * @var string The location of the vendor folder.
     */
    private string $vendorFolder = 'vendor';

    /**
     * @var array The Objects acquired during an installation
     */
    private array $objects = [];

    /**
     * Some values used for creating test data.
     * Note that owner => reference is replaces with an uuid of that User object.
     *
     * @var array|string[]
     */
    private array $testDataDefault = ['owner' => 'https://docs.commongateway.nl/user/default.user.json'];

    private const ALLOWED_CORE_SCHEMAS = [
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
        'https://docs.commongateway.nl/schemas/User.schema.json',
    ];


    /**
     * The constructor sets al needed variables.
     *
     * @codeCoverageIgnore We do not need to test constructors
     *
     * @param ComposerService        $composerService    The Composer service
     * @param EntityManagerInterface $entityManager      The entity manager
     * @param GatewayResourceService $resourceService    The resource service
     * @param Kernel                 $kernel             The kernel
     * @param LoggerInterface        $installationLogger The logger for the installation channel.
     * @param SchemaService          $schemaService      The schema service
     * @param CacheService           $cacheService       The cache service
     */
    public function __construct(
        ComposerService $composerService,
        EntityManagerInterface $entityManager,
        GatewayResourceService $resourceService,
        Kernel $kernel,
        LoggerInterface $installationLogger,
        SchemaService $schemaService,
        CacheService $cacheService
    ) {
        $this->composerService = $composerService;
        $this->entityManager   = $entityManager;
        $this->resourceService = $resourceService;
        $this->container       = $kernel->getContainer();
        $this->logger          = $installationLogger;
        $this->schemaService   = $schemaService;
        $this->cacheService    = $cacheService;
        $this->filesystem      = new Filesystem();

    }//end __construct()


    /**
     * Updates all commonground bundles on the common gateway installation.
     *
     * This functions serves as the jump of point for the `commengateway:plugins:update` command
     *
     * @param array             $config The (optional) configuration
     * @param SymfonyStyle|null $style  In case we run update from the :initialize command and want cache:warmup to show IO messages.
     *
     * @throws Exception
     *
     * @return int
     */
    public function update(array $config=[], SymfonyStyle $style=null): int
    {
        // Let's see if we are trying to update a single plugin.
        if (isset($config['plugin']) === true) {
            $this->logger->debug('Running plugin installer for a single plugin: '.$config['plugin']);
            $this->install($config['plugin'], $config);
        } else {
            // If we don't want to update a single plugin then we want to install al the plugins.
            $plugins = $this->composerService->getAll();

            $this->logger->debug('Running plugin installer for all plugins');

            foreach ($plugins as $plugin) {
                $this->install($plugin['name'], $config);
            }
        }//end if

        $this->logger->debug('Do a cache warmup after installer is done...');

        if ($style !== null) {
            $this->cacheService->setStyle($style);
            $style->info('Done running installer...');
            $style->section('Running cache warmup');
        }

        $this->cacheService->warmup();

        return Command::SUCCESS;

    }//end update()


    /**
     * Installs the files from a bundle.
     *
     * Based on the default action handler so schould supoprt a config parrameter even if we do not use it.
     *
     * @param string $bundle The bundle.
     * @param array  $config Optional config.
     *
     * @throws Exception
     *
     * @return bool The result of the installation.
     */
    public function install(string $bundle, array $config=[]): bool
    {
        $this->logger->debug('Installing plugin '.$bundle, ['plugin' => $bundle]);

        // First we want to read all the files so that we have all the content we should install.
        $this->logger->debug('Installing plugin '.$bundle);

        // Let's check the basic folders for legacy purposes. todo: remove these at some point.
        $this->readDirectory($this->vendorFolder.'/'.$bundle.'/Action');
        $this->readDirectory($this->vendorFolder.'/'.$bundle.'/Schema');
        // Entity.
        $this->readDirectory($this->vendorFolder.'/'.$bundle.'/Source');
        // Gateway.
        $this->readDirectory($this->vendorFolder.'/'.$bundle.'/Mapping');
        $this->readDirectory($this->vendorFolder.'/'.$bundle.'/Data');
        // A function that translates old core schema references to the new ones. Only here for backwards compatibility.
        $this->translateCoreReferences();

        // Then the folder where everything should be.
        $this->readDirectory($this->vendorFolder.'/'.$bundle.'/Installation');

        // Handling all the found files.
        $this->handlePluginFiles($bundle, $config);

        $this->logger->debug('All Done installing plugin '.$bundle, ['bundle' => $bundle]);

        return true;

    }//end install()


    /**
     * Will handle all files found in the plugin, creating new objects using the $this->objects array.
     *
     * @param string $bundle The bundle.
     * @param array  $config Optional config.
     *
     * @throws Exception
     *
     * @return void
     */
    private function handlePluginFiles(string $bundle, array $config)
    {
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

        // Make sure we set default data for creating testdata before we start creating ObjectEntities.
        if (Uuid::isValid($this->testDataDefault['owner']) === false) {
            $testDataUser                   = $this->entityManager->getRepository('App:User')->findOneBy(['reference' => $this->testDataDefault['owner']]);
            $this->testDataDefault['owner'] = $testDataUser ? $testDataUser->getId()->toString() : $testDataUser;
        }

        // Handle all the other objects.
        foreach ($this->objects as $ref => $schemas) {
            // Only do handleObjectType if we want to load in ALL testdata, when user has used the argument data.
            // Or if it is a core schema, of course.
            if ((isset($config['data']) === true && $config['data'] !== false) || in_array($ref, $this::ALLOWED_CORE_SCHEMAS)) {
                $this->logger->debug('Found '.count($schemas).' objects types for schema '.$ref, ['bundle' => $bundle, 'reference' => $ref]);
                $this->handleObjectType($ref, $schemas);
            }

            unset($this->objects[$ref]);
        }//end foreach

        // Find and handle the data.json file, if it exists.
        $this->handleDataJson($bundle, $config);

        // Save the all other objects to the database.
        $this->entityManager->flush();

        // Find and handle the installation.json file, if it exists.
        $this->handleInstallationJson($bundle);

    }//end handlePluginFiles()


    /**
     * Handles default / required test data from the data.json file if we are not loading in ALL testdata.
     *
     * @param string $bundle The bundle.
     * @param array  $config Optional config.
     *
     * @return void
     */
    private function handleDataJson(string $bundle, array $config)
    {
        // Handle default / required testdata in data.json file if we are not loading in ALL testdata.
        if (isset($config['data']) === false || $config['data'] === false) {
            $finder = new Finder();
            $files  = $finder->in($this->vendorFolder.'/'.$bundle.'/Installation')->files()->name('data.json');
            $this->logger->debug('Found '.count($files).' data.json file(s)', ['bundle' => $bundle]);
            foreach ($files as $file) {
                $this->readfile($file);
            }

            foreach ($this->objects as $ref => $schemas) {
                $this->handleObjectType($ref, $schemas);
                unset($this->objects[$ref]);
            }
        }

    }//end handleDataJson()


    /**
     * @param string $bundle The bundle.
     *
     * @throws Exception
     *
     * @return void
     */
    private function handleInstallationJson(string $bundle)
    {
        if ($this->filesystem->exists($this->vendorFolder.'/'.$bundle.'/Installation/installation.json') !== false) {
            $finder = new Finder();
            // todo: maybe only allow installation.json file in root of Installation folder?
            // $finder->depth('== 0');
            $files = $finder->in($this->vendorFolder.'/'.$bundle.'/Installation')->files()->name('installation.json');
            if (count($files) === 1) {
                $this->logger->debug('Found an installation.json file', ['bundle' => $bundle]);
                foreach ($files as $file) {
                    $this->handleInstaller($file);
                }
            } else {
                $this->logger->error('Found '.count($files).' installation.json files', ['location' => $this->vendorFolder.'/'.$bundle.'/Installation']);
            }

            // Save the objects created during handling installation.json to the database.
            $this->entityManager->flush();
        }

    }//end handleInstallationJson()


    /**
     * For backwards compatibility, support old core schema reference and translate them to the new ones.
     * Todo: remove this function when we no longer need it.
     *
     * @return void
     */
    private function translateCoreReferences()
    {
        foreach (array_keys($this->objects) as $translateFrom) {
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
        }//end foreach

    }//end translateCoreReferences()


    /**
     * This function reads a folder to find other folders or json objects.
     *
     * @TODO: Split this function into 2, one function for reading files and one function for checking if a folder doesn't contain to many files.
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

        // Make sure we only check directories and files on this ($location) level deep, use recursion for lower levels.
        $hits->depth('== 0');

        // Handle directories and files.
        $this->logger->debug('Found '.count($hits->directories()).' directories and '.count($hits->files()).' files.', ['location' => $location, 'files' => count($hits->directories()), 'files' => count($hits->files())]);

        // Check if we have any directories/folders at this $location.
        if (count($hits->directories()) > 0) {
            foreach ($hits->directories() as $directory) {
                // Let's check out 1 level deeper.
                $this->readDirectory($directory->getPathname());
            }
        }

        // Make sure to warn users if they have to many files in a folder. (36 is maximum).
        if (count($hits->files()) > 25) {
            $this->logger->warning("Found {strval(count($hits->files()))} files in directory, try limiting your files to 32 per directory. Or you won\'t be able to load in these schema\'s locally on a windows machine.", ['location' => $location, 'files' => count($hits->files())]);
        }

        // Read all files in this folder.
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

        // Todo: validateJsonMapping does not exist.
        // Check if it is a valid schema.
        // $mappingSchema = $this->validateJsonMapping($mappingSchema);
        // if ($this->validateJsonMapping($mappingSchema) === true) {
        // $this->logger->error($file->getFilename().' is not a valid json-mapping object');
        // return false;
        // }
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
                foreach ($value as $object) {
                    $this->objects[$key][] = $object;
                }

                continue;
            }

            // The use of gettype is discouraged, but we don't use it as a bl here and only for logging text purposes. So a design decision was made te allow it.
            $this->logger->error('Expected to find array for schema type '.$key.' but found '.gettype($value).' instead', ['value' => $value, 'schema' => $key]);
        }//end foreach

        return true;

    }//end addToObjects()


    /**
     * Handles schemas of a certain type.
     *
     * @param string $type    The type of the object
     * @param array  $schemas The schemas to handle
     *
     * @return array The objects.
     */
    private function handleObjectType(string $type, array $schemas): array
    {
        $objects = [];

        foreach ($schemas as $schema) {
            $object = $this->handleObject($type, $schema);
            if ($object === null) {
                continue;
            }

            // Save it to the database.
            $this->entityManager->persist($object);
            $objects[] = $object;
        }//end foreach

        return $objects;

    }//end handleObjectType()


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

        // Handle core schema's.
        if (in_array($type, $this::ALLOWED_CORE_SCHEMAS) === true) {
            $object = $this->loadCoreSchema($schema, $type);
        }//end if

        // Handle Other schema's.
        if (in_array($type, $this::ALLOWED_CORE_SCHEMAS) === false) {
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
                    'class' => get_class($object),
                    // If you get a "::$id must not be accessed before initialization" error here, remove type UuidInterface from the class^ $id declaration. Something to do with read_secure I think.
                    'id'    => $object->getId(),
                    // TODO: using toSchema on an object that is not persisted yet breaks stuff... "must not be accessed before initialization" errors
                    // 'object' => method_exists(get_class($object), 'toSchema') === true ? $object->toSchema() : 'toSchema function does not exists.',
                ]
            );
        }//end if

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
        // @todo remove $query? its not being used.
        // $query = explode(',', ltrim($matches[2], '?'));
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
        }//end if

        // Make sure we have a fromSchema function for this type of object.
        if (method_exists(get_class($object), 'fromSchema') === false) {
            $this->logger->critical('fromSchema function does not exists for this core schema type: '.get_class($object));

            return null;
        }

        // Load the data. Compare version to check if we need to update or not.
        if (array_key_exists('version', $schema) === true && version_compare($schema['version'], $object->getVersion()) <= 0) {
            $this->logger->debug('The schema has a version number equal or lower then the already present version, the object is NOT updated', ['schemaVersion' => $schema['version'], 'objectVersion' => $object->getVersion()]);

            return $object;
        }

        if (array_key_exists('version', $schema) === true && version_compare($schema['version'], $object->getVersion()) > 0) {
            $this->logger->debug('The schema has a version number higher then the already present version, the object data is updated', ['schemaVersion' => $schema['version'], 'objectVersion' => $object->getVersion()]);
            $object->fromSchema($schema);

            return $object;
        }

        if (array_key_exists('version', $schema) === false || $object->getVersion() === null) {
            $this->logger->debug('The new schema doesn\'t have a version number, the object data is created', ['schemaVersion' => $schema['version'] ?? null, 'objectVersion' => $object->getVersion()]);
            $object->fromSchema($schema);

            return $object;
        }

        return $object;

    }//end loadCoreSchema()


    /**
     * Creates a new object of the given type.
     *
     * @param string $type The type to create an object of.
     *
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
        case 'User':
            return new User();
        default:
            return null;
        }//end switch

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
            $object = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $schema['id']]);
        }

        // Create it if we don't.
        if (isset($object) === false || $object === null) {
            $object = new ObjectEntity($entity);
            $object->setOwner($this->testDataDefault['owner']);
        }

        // TODO: testdata objects seem to have twice as much subobjects as they should have. Duplicates... (example: kiss->klanten->telefoonnummers).
        // Now it gets a bit specif but for EAV data we allow nested fixed id's so let dive deep.
        if ($this->entityManager->contains($object) === false && (array_key_exists('id', $schema) === true || array_key_exists('_id', $schema) === true)) {
            $object = $this->schemaService->hydrate($object, $schema);
        }

        // EAV objects arn't cast from schema but hydrated from array's.
        $object->hydrate($schema);

        return $object;

    }//end loadSchema()


    /**
     * Specifically handles the installation file.
     *
     * @todo: clean up this function, split it into multiple smaller pieces.
     *
     * @param SplFileInfo $file The installation file.
     *
     * @throws Exception
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

        // Collection prefixes for schema's.
        $this->updateSchemasCollection(($data['collections'] ?? []));

        // Endpoints for schema's and/or sources.
        $this->createEndpoints(($data['endpoints'] ?? []));

        // Actions for action handlers.
        $this->createActions(($data['actions']['handlers'] ?? []));

        // Fix references in configuration of these actions.
        $this->fixConfigRef(($data['actions']['fixConfigRef'] ?? []));

        // Cronjobs for actions for action handlers.
        $this->createCronjobs(($data['cronjobs']['actions'] ?? []));

        // Create users with given Organization, Applications & SecurityGroups.
        $this->createApplications(($data['applications'] ?? []));

        // Create users with given Organization, Applications & SecurityGroups.
        $this->createUsers(($data['users'] ?? []));

        // Lets see if we have things that we want to create cards for stuff (Since this might create cards for the stuff above this should always be last).
        $this->createCards(($data['cards'] ?? []));

        // Set the default source for a schema.
        $this->editSchemaProperties(($data['schemas'] ?? []));

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
            $this->logger->error(($error ?? "{$file->getFilename()} Could not be loaded from container"));

            return false;
        }

        try {
            $install = $installationService->install();

            return is_bool($install) === true ? $install : empty($install) === false;
        } catch (\Throwable $throwable) {
            $this->logger->critical("Failed to install installationService {$data['installationService']}: {$throwable->getMessage()}", ['file' => $throwable->getFile(), 'line' => $throwable->getLine()]);

            return false;
        }

    }//end handleInstaller()


    /**
     * This function adds a given default source to the schema.
     *
     * @param array $schemasData The array with data of the schemas
     *
     * @throws Exception
     *
     * @return void
     */
    private function editSchemaProperties(array $schemasData=[]): void
    {
        foreach ($schemasData as $schemaData) {
            // Get the schema and source from the schemadata.
            $schema = $this->resourceService->getSchema($schemaData['reference'], 'commongateway/corebundle');

            if (key_exists('defaultSource', $schemaData) === true) {
                $source = $this->resourceService->getSource($schemaData['defaultSource'], 'commongateway/corebundle');
                // Set the source as defaultSource to the schema.
                $schema->setDefaultSource($source);
            }

            if (key_exists('createAuditTrails', $schemaData) === true) {
                $schema->setCreateAuditTrails($schemaData['createAuditTrails']);
            }

            $this->entityManager->persist($schema);
        }//end foreach

    }//end editSchemaProperties()


    /**
     * This functions connects schema's with a reference containing the collection schemaPrefix to the given collection.
     * This way endpoints will be created with the correct prefix.
     *
     * @param array $collectionsData An array of references of collections + a schemaPrefix.
     *
     * @return void
     */
    private function updateSchemasCollection(array $collectionsData=[])
    {
        $collections = 0;

        foreach ($collectionsData as $collectionData) {
            $collection = $this->entityManager->getRepository('App:CollectionEntity')->findOneBy(['reference' => $collectionData['reference']]);
            if ($collection === null) {
                $this->logger->error('No collection found with this reference: '.$collectionData['reference']);
                continue;
            }

            if (isset($collectionData['schemaPrefix']) === false || empty($collectionData['schemaPrefix']) === true) {
                $this->logger->error('No valid schemaPrefix given while trying to add collection to schema\'s', ['reference' => $collectionData['reference']]);
                continue;
            }

            $this->addSchemasToCollection($collection, $collectionData['schemaPrefix']);

            $collections++;

            $this->logger->debug("Updated schemas with a reference starting with {$collectionData['schemaPrefix']} for Collection {$collectionData['reference']}");
        }

        $this->logger->info("Updated schemas for $collections Collections");

    }//end updateSchemasCollection()


    /**
     * Adds a collection to all schemas that have a reference starting with $schemaPrefix.
     *
     * @param CollectionEntity $collection   The collection to add.
     * @param string           $schemaPrefix The prefix to find schemas for.
     *
     * @return void
     */
    private function addSchemasToCollection(CollectionEntity $collection, string $schemaPrefix)
    {
        $entities = $this->entityManager->getRepository('App:Entity')->findByReferencePrefix($schemaPrefix);
        foreach ($entities as $entity) {
            $entity->addCollection($collection);
        }

    }//end addSchemasToCollection()


    /**
     * This function creates endpoints for an array of schema references or source references.
     *
     * @param array $endpointsData An array of data used for creating endpoints.
     *
     * @return array An array of endpoints
     */
    private function createEndpoints(array $endpointsData=[]): array
    {
        $endpoints = [];

        // Let's loop through the endpointsData.
        foreach ($endpointsData as $type => $endpointTypeData) {
            // Check for what type of object we are creating an Endpoint.
            if (in_array($type, ['multipleSchemas', 'schemas', 'sources']) === false) {
                $this->logger->error('Unknown type used for endpoint creation: '.$type);
                continue;
            }

            // Then we can handle some data.
            foreach ($endpointTypeData as $endpointData) {
                // Just in case unset these.
                $subEndpoints = ($endpointData['subEndpoints'] ?? []);
                unset($endpointData['subEndpoints']);
                $subSchemaEndpoints = ($endpointData['subSchemaEndpoints'] ?? []);
                unset($endpointData['subSchemaEndpoints']);

                // Create the base Endpoint.
                $endpoint = $this->createEndpoint($type, $endpointData);
                if ($endpoint === null) {
                    continue;
                }

                $endpoints[] = $endpoint;

                // Handle sub and subSchema Endpoints. (will always create an endpoint for type 'schemas').
                $endpointData['$id'] = $endpoint->getReference();
                $endpoints           = array_merge($endpoints, $this->handleSubEndpoints($endpointData, $subEndpoints));
                $endpoints           = array_merge($endpoints, $this->handleSubSchemaEndpoints($endpointData, $subSchemaEndpoints));
            }//end foreach
        }//end foreach

        $this->logger->info(count($endpoints).' Endpoints Created');

        return $endpoints;

    }//end createEndpoints()


    /**
     * Creates a single endpoint for an Entity or a Source using the data from installation.json.
     *
     * @param string $type         The type, used in installation.json['endpoints'][$type] we are creating an Endpoint for.
     * @param array  $endpointData The data used to create an Endpoint containing a reference (of type $type), path & methods.
     *
     * @return Endpoint|null The created Endpoint or null.
     */
    private function createEndpoint(string $type, array $endpointData): ?Endpoint
    {
        if ($type === 'sources') {
            $repository = $this->entityManager->getRepository('App:Gateway');
        } else {
            $repository = $this->entityManager->getRepository('App:Entity');
        }

        if ($type === 'multipleSchemas') {
            $endpointData['entities'] = [];
            foreach ($endpointData['schemas'] as $schema) {
                $object = $this->checkIfObjectExists($repository, $schema, $type);
                if ($object instanceof Entity) {
                    $endpointData['entities'][] = $object;
                }
            }

            unset($endpointData['schemas']);
        } else {
            $object = $this->checkIfObjectExists($repository, $endpointData['reference'], $type);
        }

        // Todo: this works, we should go to php 8.0 later.
        if (isset($endpointData['$id']) === false || str_contains($endpointData['$id'], '.endpoint.json') === false) {
            $endpointData['$id'] = $this->createEndpointReference($object ?? null, $type);
            if ($endpointData['$id'] === null) {
                return null;
            }
        }

        $endpoint = $this->entityManager->getRepository('App:Endpoint')->findOneBy(['reference' => $endpointData['$id']]);
        if ($endpoint !== null) {
            $this->logger->debug('Endpoint found with reference '.$endpointData['$id']);

            return null;
        }

        $endpoint = $this->constructEndpoint($type, $object ?? null, $endpointData);
        $this->entityManager->persist($endpoint);
        $this->logger->debug('Endpoint created for '.(isset($object) === true ? $object->getReference() : 'multipleSchemas').' with reference: '.$endpointData['$id']);

        return $endpoint;

    }//end createEndpoint()


    /**
     * Constructs an Endpoint using the Endpoint constructor, but how the constructor is called depends on the $type.
     *
     * @param string             $type         The type of Endpoint we are creating.
     * @param Entity|Source|null $object       The object we are creating an Endpoint for.
     * @param array              $endpointData The data used to create the Endpoint.
     *
     * @return Endpoint|null The created Endpoint or null.
     */
    private function constructEndpoint(string $type, $object, array $endpointData): ?Endpoint
    {
        // todo ? maybe create a second constructor? So we can do Endpoint($object, $endpointData);
        switch ($type) {
        case 'sources':
            return new Endpoint(null, $object, $endpointData);
        case 'schemas':
            return new Endpoint($object, null, $endpointData);
        case 'multipleSchemas':
            return new Endpoint(null, null, $endpointData);
        }

        $this->logger->error('Unknown type used for endpoint construction: '.$type);

        return null;

    }//end constructEndpoint()


    /**
     * Checks if an object exists, using the given repository and reference.
     *
     * @param mixed  $repository The repository to search in. Entity or Source repository.
     * @param string $reference  A schema reference of an Entity or Source.
     * @param string $type       The type used in installation.json['endpoints'][$type]. The type we are creating a new Endpoint for.
     *
     * @return Entity|Source|null Null if we found nothing, else the object found, Entity or Source.
     */
    private function checkIfObjectExists($repository, string $reference, string $type)
    {
        $object = $repository->findOneBy(['reference' => $reference]);
        if ($object === null) {
            $this->logger->error('No object found for '.$reference.' while trying to create an Endpoint or User.', ['type' => $type]);

            return null;
        }

        return $object;

    }//end checkIfObjectExists()


    /**
     * Creates a reference for a new Endpoint using the name of the object we are creating it for and the domain of its reference.
     *
     * @param Entity|Source $object The object (Entity or Source) we are creating an Endpoint (reference) for.
     * @param string        $type   The type, used in installation.json['endpoints'][$type] we are creating an Endpoint for.
     *
     * @return string|null The reference for the Entity or Proxy Endpoint.
     */
    private function createEndpointReference($object, string $type): ?string
    {
        if ($object === null) {
            $this->logger->error('Could not create a unique reference for a new endpoint', ['type' => $type, 'object' => $object]);

            return null;
        }

        $parsedUrl = parse_url($object->getReference());
        if (array_key_exists('host', $parsedUrl) === false || empty($parsedUrl['host']) === true || empty($object->getName()) === true) {
            $this->logger->error('Could not create a unique reference for a new endpoint while trying to create an endpoint for '.$object->getReference(), ['type' => $type]);

            return null;
        }

        $endpointType = $type === 'sources' ? 'Proxy' : 'Entity';
        $name         = str_replace(' ', '-', $object->getName());

        return "https://{$parsedUrl['host']}/{$endpointType}Endpoint/$name.endpoint.json";

    }//end createEndpointReference()


    /**
     * Creates the basics for a new subEndpoint or subSchemaEndpoint.
     *
     * @param array $baseEndpointData The base endpoint data from installation.json['endpoints']['schemas'][someEndpoint] for which we are creating subEndpoints or subSchemaEndpoints.
     * @param array $newEndpointData  Data for creating a new subEndpoint or subSchemaEndpoint, used for creating a unique reference.
     *
     * @return Endpoint|null A newly created Endpoint with basic settings and e unique reference. Or null if it already existed / is not a new Endpoint.
     */
    private function createBaseEndpoint(array $baseEndpointData, array $newEndpointData): ?Endpoint
    {
        $name = ucfirst($newEndpointData['path']);

        $endpointData = $baseEndpointData;
        if (isset($newEndpointData['$id']) === true) {
            $endpointData['$id'] = $newEndpointData['$id'];
        } else {
            $endpointData['$id'] = str_replace('.endpoint.json', $name.'.endpoint.json', $baseEndpointData['$id']);
        }

        return $this->createEndpoint('schemas', $endpointData);

    }//end createBaseEndpoint()


    /**
     * Updates some basic fields like name, description and throws for a new subEndpoint or subSchemaEndpoint.
     *
     * @param Endpoint $newEndpoint     A newly created subEndpoint or subSchemaEndpoint.
     * @param array    $newEndpointData The data from installation.json for this specific subEndpoint or subSchemaEndpoint.
     *
     * @return Endpoint The updated Endpoint.
     */
    private function setEndpointBasics(Endpoint $newEndpoint, array $newEndpointData): Endpoint
    {
        $name = ucfirst($newEndpointData['path']);
        $newEndpoint->setName($newEndpoint->getName().' '.$name);
        if (isset($newEndpointData['description']) === true) {
            $newEndpoint->setDescription($newEndpointData['description']);
        }

        $newEndpoint->setThrows($newEndpointData['throws']);

        // Check for reference to entity, if so, add entity to endpoint.
        if (isset($newEndpointData['reference']) === true) {
            $newEndpoint->setEntity(null);
            // Old way of setting Entity for Endpoints.
            foreach ($newEndpoint->getEntities() as $removeEntity) {
                $newEndpoint->removeEntity($removeEntity);
            }

            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $newEndpointData['reference']]);
            if ($entity !== null) {
                $newEndpoint->addEntity($entity);
            }
        }

        return $newEndpoint;

    }//end setEndpointBasics()


    /**
     * Creates subEndpoints for an Entity Endpoint. Example: domain.com/api/entities/subEndpoint['path'].
     *
     * @param array $baseEndpointData The base endpoint data from installation.json['endpoints']['schemas'][someEndpoint] for which we are creating subEndpoints.
     * @param array $subEndpoints     An array of data from installation.json[someEndpoint]['subEndpoints'] used for creating subEndpoints.
     *
     * @return array An array of created subEndpoints.
     */
    private function handleSubEndpoints(array $baseEndpointData, array $subEndpoints): array
    {
        $endpoints = [];

        foreach ($subEndpoints as $subEndpointData) {
            $this->logger->debug('Creating a subEndpoint '.$subEndpointData['path'].' for '.$baseEndpointData['reference']);

            if (isset($subEndpointData['path']) === false || isset($subEndpointData['throws']) === false) {
                $this->logger->error('SubEndpointData is missing a path or throws', ['SubEndpointData' => $subEndpointData]);
                continue;
            }

            // Create base $subEndpoint Endpoint with a unique reference.
            $subEndpoint = $this->createBaseEndpoint($baseEndpointData, $subEndpointData);
            if ($subEndpoint === null) {
                continue;
            }

            $path   = $subEndpoint->getPath();
            $path[] = $subEndpointData['path'];
            $subEndpoint->setPath($path);

            $pathRegex = rtrim($subEndpoint->getPathRegex(), '$');
            $pathRegex = str_replace('?([a-z0-9-]+)?', '([a-z0-9-]+)', $pathRegex);
            $subEndpoint->setPathRegex($pathRegex.'/'.$subEndpointData['path'].'$');

            $subEndpoint = $this->setEndpointBasics($subEndpoint, $subEndpointData);

            $this->entityManager->persist($subEndpoint);
        }//end foreach

        return $endpoints;

    }//end handleSubEndpoints()


    /**
     * Creates subSchemaEndpoints for an Entity Endpoint. Example: domain.com/api/entities/{uuid}/subSchemaEndpoint['path']/{uuid}.
     *
     * @param array $baseEndpointData   The base endpoint data from installation.json['endpoints']['schemas'][someEndpoint] for which we are creating subSchemaEndpoints.
     * @param array $subSchemaEndpoints An array of data from installation.json[someEndpoint]['subSchemaEndpoints'] used for creating subSchemaEndpoints.
     *
     * @return array An array of created subSchemaEndpoints.
     */
    private function handleSubSchemaEndpoints(array $baseEndpointData, array $subSchemaEndpoints): array
    {
        $endpoints = [];

        foreach ($subSchemaEndpoints as $subSchemaEndpointData) {
            $this->logger->debug('Creating a subSchemaEndpoint '.$subSchemaEndpointData['path'].' for '.$baseEndpointData['reference']);

            if (isset($subSchemaEndpointData['path']) === false || isset($subSchemaEndpointData['throws']) === false || isset($subSchemaEndpointData['reference']) === false) {
                $this->logger->error('SubSchemaEndpointData is missing a reference, path or throws', ['SubSchemaEndpointData' => $subSchemaEndpointData]);
                continue;
            }

            // Create base $subSchemaEndpoint Endpoint with a unique reference.
            $subSchemaEndpoint = $this->createBaseEndpoint($baseEndpointData, $subSchemaEndpointData);
            if ($subSchemaEndpoint === null) {
                continue;
            }

            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $subSchemaEndpointData['reference']]);
            if ($entity === null) {
                $this->logger->error('No entity found for '.$subSchemaEndpointData['reference'].' while trying to create a subSchemaEndpoint.');
                continue;
            }

            $path                            = $subSchemaEndpoint->getPath();
            $path[array_search('id', $path)] = '{'.strtolower($entity->getName()).'._self.id}';
            $path[]                          = $subSchemaEndpointData['path'];
            $path[]                          = '{id}';
            $subSchemaEndpoint->setPath($path);

            $pathRegex = rtrim($subSchemaEndpoint->getPathRegex(), '$');
            $pathRegex = str_replace('?([a-z0-9-]+)?', '([a-z0-9-]+)', $pathRegex);
            $subSchemaEndpoint->setPathRegex($pathRegex.'/'.$subSchemaEndpointData['path'].'/?([a-z0-9-]+)?$');

            $subSchemaEndpoint = $this->setEndpointBasics($subSchemaEndpoint, $subSchemaEndpointData);

            $this->entityManager->persist($subSchemaEndpoint);
        }//end foreach

        return $endpoints;

    }//end handleSubSchemaEndpoints()


    /**
     * This functions creates actions for an array of handlers.
     *
     * @param array $handlersData An array of references of handlers for wih actions schould be created
     *
     * @return array An array of Action objects
     */
    private function createActions(array $handlersData=[]): array
    {
        $actions = [];

        foreach ($handlersData as $handlerData) {
            $actionHandler = $this->container->get($handlerData['actionHandler']);

            $action = $this->entityManager->getRepository('App:Action')->findOneBy(['class' => get_class($actionHandler)]);

            $blockUpdate = true;
            if ($action !== null && $action->getVersion() && isset($handlerData['version']) === true) {
                $blockUpdate = version_compare($handlerData['version'], $action->getVersion()) <= 0;
            } else if ($action === null && isset($handlerData['version']) === true) {
                $blockUpdate = false;
            }

            if ($action !== null && $blockUpdate === true) {
                $this->logger->debug('Action found for '.$handlerData['actionHandler'].' with class '.get_class($actionHandler));
                continue;
            }

            $schema = $actionHandler->getConfiguration();
            if (empty($schema) === true) {
                $this->logger->error('Handler '.$handlerData['actionHandler'].' has no configuration');
                continue;
            }

            if ($action === null) {
                $action = new Action($actionHandler);
            }

            array_key_exists('name', $handlerData) === true ? $action->setName($handlerData['name']) : '';
            array_key_exists('reference', $handlerData) === true ? $action->setReference($handlerData['reference']) : '';
            $action->setListens(($handlerData['listens'] ?? []));
            $action->setConditions(($handlerData['conditions'] ?? ['==' => [1, 1]]));

            $defaultConfig = $this->addActionConfiguration($actionHandler);
            // todo: maybe use: Action->getDefaultConfigFromSchema() instead?
            isset($handlerData['configuration']) === true && $defaultConfig = $this->overrideConfig($defaultConfig, ($handlerData['configuration'] ?? []));
            $action->setConfiguration($defaultConfig);

            if (isset($handlerData['version']) === true) {
                $action->setVersion($handlerData['version']);
            }

            $this->entityManager->persist($action);
            $actions[] = $action;

            $this->logger->debug('Action created for '.$handlerData['actionHandler'].' with class '.get_class($actionHandler));
        }//end foreach

        $this->logger->info(count($actions).' Actions Created');

        return $actions;

    }//end createActions()


    /**
     * This functions replaces references in the action->configuration array with corresponding ids of the entity/source.
     *
     * @param array $actionRefs An array of references of Actions we are going to check.
     *
     * @return void An array of Action objects
     */
    private function fixConfigRef(array $actionRefs=[]): void
    {
        $actions = 0;

        foreach ($actionRefs as $reference) {
            $action = $this->entityManager->getRepository('App:Action')->findOneBy(['reference' => $reference]);
            if ($action === null) {
                $this->logger->error('No action found with reference: '.$reference);
                continue;
            }

            if ($action->getClass() === null) {
                $this->logger->error('No actionHandler (/class) found for Action: '.$reference);
                continue;
            }

            $actionHandler = $this->container->get($action->getClass());
            $schema        = $actionHandler->getConfiguration();
            if (empty($schema) === true) {
                $this->logger->error('Handler '.$action->getClass().' has no configuration');
                continue;
            }

            $defaultConfig = $this->addActionConfiguration($actionHandler);
            // todo: maybe use: Action->getDefaultConfigFromSchema() instead?
            empty($action->getConfiguration()) === false && $defaultConfig = $this->overrideConfig($defaultConfig, ($action->getConfiguration() ?? []));

            $action->setConfiguration($defaultConfig);
            $this->entityManager->persist($action);

            $actions++;
        }//end foreach

        $this->logger->info($actions.' Actions configuration updated.');

    }//end fixConfigRef()


    /**
     * This function creates default configuration for the action.
     *
     * @param mixed $actionHandler The actionHandler for witch the default configuration is set.
     *
     * @return array
     */
    public function addActionConfiguration($actionHandler): array
    {
        $defaultConfig = [];
        foreach ($actionHandler->getConfiguration()['properties'] as $key => $value) {
            switch ($value['type']) {
            case 'string':
            case 'array':
                if (isset($value['example']) === true) {
                    $defaultConfig[$key] = $value['example'];
                }
                break;
            case 'object':
                break;
            case 'uuid':
                if (isset($value['$ref']) === true) {
                    try {
                        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $value['$ref']]);
                    } catch (Exception $exception) {
                        $this->logger->error("No entity found with reference {$value['$ref']} (addActionConfiguration() for installation.json)");
                    }

                    $defaultConfig[$key] = $entity->getId()->toString();
                }
                break;
            default:
                // throw error.
            }//end switch
        }//end foreach

        return $defaultConfig;

    }//end addActionConfiguration()


    /**
     * Overrides the default configuration of an Action. Will also set entity and source to id if a reference is given.
     *
     * @param array $defaultConfig
     * @param array $overrides
     *
     * @return array
     */
    public function overrideConfig(array $defaultConfig, array $overrides): array
    {
        foreach ($overrides as $key => $override) {
            if (is_array($override) === true && $this->isAssociative($override)) {
                $defaultConfig[$key] = $this->overrideConfig(isset($defaultConfig[$key]) === true ? $defaultConfig[$key] : [], $override);

                continue;
            }//end if

            if ($key == 'entity' && is_string($override) === true && Uuid::isValid($override) === false) {
                $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $override]);
                if (!$entity) {
                    $this->logger->error("No entity found with reference {$override} (overrideConfig() for installation.json)");
                    continue;
                }

                $defaultConfig[$key] = $entity->getId()->toString();

                continue;
            }//end if

            if ($key == 'source' && is_string($override) === true && Uuid::isValid($override) === false) {
                $source = $this->entityManager->getRepository('App:Gateway')->findOneBy(['reference' => $override]);
                if (!$source) {
                    $this->logger->error("No source found with reference {$override} (overrideConfig() for installation.json)");
                    continue;
                }

                $defaultConfig[$key] = $source->getId()->toString();

                continue;
            }//end if

            $defaultConfig[$key] = $override;
        }//end foreach

        return $defaultConfig;

    }//end overrideConfig()


    /**
     * Decides if an array is associative.
     *
     * @param array $array The array to check.
     *
     * @return bool True if the array is associative.
     */
    private function isAssociative(array $array): bool
    {
        if ([] === $array) {
            return false;
        }

        return array_keys($array) !== range(0, (count($array) - 1));

    }//end isAssociative()


    /**
     * This function creates cronjobs for an array of action references.
     *
     * @param array $actions An array of references of actions for wih actions cronjobs be created
     *
     * @return array An array of cronjobs.
     */
    private function createCronjobs(array $actions=[]): array
    {
        $cronjobs = [];

        foreach ($actions as $reference) {
            $action = $this->entityManager->getRepository('App:Action')->findOneBy(['reference' => $reference]);

            if ($action === null) {
                $this->logger->error('No action found for reference '.$reference);
                continue;
            }

            // TODO: CHECK IF THIS CRONJOB ALREADY EXISTS ?!?
            $cronjob = new Cronjob($action);
            $this->entityManager->persist($cronjob);
            $cronjobs[] = $cronjob;
            $this->logger->debug('Cronjob created for action '.$reference);
        }//end foreach

        $this->logger->info(count($cronjobs).' Cronjobs Created');

        return $cronjobs;

    }//end createCronjobs()


    /**
     * This function creates applications with the given $applications data.
     * Each application in this array should have an organization = reference.
     *
     * @param array $applicationsData An array of arrays describing the application objects we want to create or update.
     *
     * @return array An array of applications.
     */
    private function createApplications(array $applicationsData): array
    {
        $orgRepository = $this->entityManager->getRepository('App:Organization');

        foreach ($applicationsData as $key => &$applicationData) {
            if (isset($applicationData['$id']) === false) {
                $this->logger->error("Can't create an Application without '\$id': 'reference'", ['applicationData' => $applicationData]);
                unset($applicationsData[$key]);

                continue;
            }

            $organization                    = ($applicationData['organization'] ?? 'https://docs.commongateway.nl/organization/default.organization.json');
            $applicationData['organization'] = $this->checkIfObjectExists($orgRepository, $organization, 'Organization');
            if ($applicationData['organization'] instanceof Organization === false) {
                unset($applicationsData[$key]);

                continue;
            }

            if (isset($applicationData['title']) === false) {
                $applicationData['title'] = ($applicationData['name'] ?? '');
            }
        }//end foreach

        if (empty($applicationsData) === false) {
            $applications = $this->handleObjectType('https://docs.commongateway.nl/schemas/Application.schema.json', $applicationsData);
        }

        $this->logger->info(count($applications).' Applications Created');

        return $applications;

    }//end createApplications()


    /**
     * This function creates users with the given $users data.
     * Each user in this array should have a securityGroups array with references to SecurityGroups.
     *
     * @param array $usersData An array of arrays describing the user objects we want to create or update.
     *
     * @return array An array of users.
     */
    private function createUsers(array $usersData=[]): array
    {
        $orgRepository = $this->entityManager->getRepository('App:Organization');

        foreach ($usersData as $key => &$userData) {
            if (isset($userData['email']) === false || isset($userData['securityGroups']) === false || isset($userData['$id']) === false) {
                $this->logger->error("Can't create an User without 'email': 'username', '\$id': 'reference' and 'securityGroups': [securityGroup-references]", ['userData' => $userData]);
                unset($usersData[$key]);

                continue;
            }

            $this->handleUserGroups($userData);
            $this->handleUserApps($userData);

            $organization             = ($userData['organization'] ?? 'https://docs.commongateway.nl/organization/default.organization.json');
            $userData['organization'] = $this->checkIfObjectExists($orgRepository, $organization, 'Organization');
            if ($userData['organization'] instanceof Organization === false) {
                unset($usersData[$key]);

                continue;
            }

            if (isset($userData['title']) === false) {
                $userData['title'] = ($userData['name'] ?? $userData['email']);
            }
        }//end foreach

        if (empty($usersData) === false) {
            $users = $this->handleObjectType('https://docs.commongateway.nl/schemas/User.schema.json', $usersData);
        }

        $this->logger->info(count($users).' Users Created');

        return $users;

    }//end createUsers()


    /**
     * Replaces $userData['securityGroups'] references with real SecurityGroups objects,
     * so the fromSchema function for User can create a user with this.
     * Will also check if someone is trying to add admin scopes through this method.
     *
     * @param array $userData An array describing the user object we want to create or update.
     *
     * @return array The updated $userData array.
     */
    private function handleUserGroups(array &$userData): array
    {
        $repository = $this->entityManager->getRepository('App:SecurityGroup');

        $securityGroups = $userData['securityGroups'];
        unset($userData['securityGroups']);

        foreach ($securityGroups as $reference) {
            $securityGroup = $this->checkIfObjectExists($repository, $reference, 'SecurityGroup');
            if ($securityGroup instanceof SecurityGroup === false) {
                continue;
            }

            foreach ($securityGroup->getScopes() as $scope) {
                // Todo: This works, we should go to php 8.0 later.
                if (str_contains(strtolower($scope), 'admin')) {
                    $this->logger->error('It is forbidden to change or add users with admin scopes!', ['securityGroup' => $reference, 'userData' => $userData]);
                    continue 2;
                }
            }

            $userData['securityGroups'][] = $securityGroup;
        }

        return $userData;

    }//end handleUserGroups()


    /**
     * Replaces $userData['applications'] references with real Application objects,
     * so the fromSchema function for User can create a user with this.
     *
     * @param array $userData An array describing the user object we want to create or update.
     *
     * @return array The updated $userData array.
     */
    private function handleUserApps(array &$userData): array
    {
        $repository = $this->entityManager->getRepository('App:Application');

        if (isset($userData['applications']) === false) {
            $userData['applications'] = ['https://docs.commongateway.nl/application/default.application.json'];
        }

        $applications = $userData['applications'];
        unset($userData['applications']);

        foreach ($applications as $reference) {
            $application = $this->checkIfObjectExists($repository, $reference, 'Application');
            if ($application instanceof Application === false) {
                continue;
            }

            $userData['applications'][] = $application;
        }

        return $userData;

    }//end handleUserApps()


    /**
     * This functions creates dashboard cars for an array of endpoints, sources, schema's or objects.
     *
     * @todo: clean up this function, split it into multiple smaller pieces.
     *
     * @param array $cardsData An array of data used for creating dashboardCards.
     *
     * @return array An array of dashboardCard objects
     */
    private function createCards(array $cardsData=[]): array
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
            // case 'securityGroups':
            // $repository = $this->entityManager->getRepository('App:SecurityGroup');
            // break;
            // case 'users':
            // $repository = $this->entityManager->getRepository('App:User');
            // break;
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
                    $this->logger->debug('DashboardCard found for '.get_class($object).' with id: '.$object->getId());
                    continue;
                }

                $dashboardCard = new DashboardCard($object);
                $cards[]       = $dashboardCard;
                $this->entityManager->persist($dashboardCard);
                $this->logger->debug('Dashboard Card created for '.$reference);
            }//end foreach
        }//end foreach

        $this->logger->info(count($cards).' Cards Created');

        return $cards;

    }//end createCards()


}//end class
