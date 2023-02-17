<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Action;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * The installation service is used to install plugins (or actually symfony bundles) on the gateway.
 *
 * This class breacks complixity,methods and coupling rules. This could be solved by devidng the class into smaller classes but that would deminisch the readbilly of the code as a whole. All the code in this class is only used in an installation context and it makes more sence to keep it together. Therefore a design decicion was made to keep al this code in one class.
 *
 * @author Ruben van der Linde
 *
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
     * @var Logger
     */
    private Logger $logger;

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
        SchemaService $schemaService
    ) {
        $this->composerService = $composerService;
        $this->entityManager = $entityManager;
        $this->container = $kernel->getContainer();
        $this->collection = null;
        $this->logger = new Logger('installation');
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

        // First we want to read all the filles so that we have all the content the we should install.
        $this->logger->debug('Installing plugin '.$bundle);

        // Lets check the basic folders for lagacy pruposes.
        $this->readDirectory($vendorFolder.'/'.$bundle.'/Action');
        $this->readDirectory($vendorFolder.'/'.$bundle.'/Schema');
        $this->readDirectory($vendorFolder.'/'.$bundle.'/Mapping');
        $this->readDirectory($vendorFolder.'/'.$bundle.'/Data');

        // Then the folder where everything should be.
        $this->readDirectory($vendorFolder.'/'.$bundle.'/Installation');

        // Handling al the found  files.
        $this->logger->debug('Found '.count($this->objects).' schema types for '.$bundle, ['bundle' => $bundle]);

        // There is a certain order to this, meaning that we want to handle certain schema types before other schema types.
        if (isset($this->object['https://docs.commongateway.nl/schemas/Entity.schema.json']) === true && is_array($this->object['https://docs.commongateway.nl/schemas/Entity.schema.json']) === true) {
            $schemas = $this->object['https://docs.commongateway.nl/schemas/Entity.schema.json'];
            $this->logger->debug('Found '.count($schemas).' objects types for schema https://docs.commongateway.nl/schemas/Entity.schema.json', ['bundle' => $bundle, 'reference' => 'https://docs.commongateway.nl/schemas/Entity.schema.json']);
            $this->handleObjectType($schemas);
            unset($this->objects[$this->object['https://docs.commongateway.nl/schemas/Organization.schema.json']]);
        }

        // Handle all the other objects.
        foreach ($this->objects as $ref => $schemas) {
            $this->logger->debug('Found '.count($schemas).' objects types for schema '.$ref, ['bundle' => $bundle, 'reference' => $ref]);
            $this->handleObjectType($schemas);
            unset($this->objects[$ref]);
        }

        // Find and handle the installation.json file.
        if ($this->filesystem->exists($vendorFolder.'/'.$bundle.'/Installation/installation.json') !== false) {
            $finder = new Finder();
            foreach ($finder->in($vendorFolder.'/'.$bundle.'/Installation/installation.json') as $file) {
                $this->handleInstaller($file);
            }
        }

        // Save the results to the database.
        $this->entityManager->flush();

        $this->logger->debug('All Done installing plugin '.$bundle, ['bundle' => $bundle]);

        return true;
    }//end install()

    /**
     * This function read a folder to find other folders or json objects.
     *
     * @param string $location The location of the folder
     *
     * @return bool Whether or not the function was succefully executed
     */
    private function readDirectory(string $location): bool
    {

        // Lets see if the folder exisits to start with.
        if ($this->filesystem->exists($location) === false) {
            $this->logger->debug('Installation folder not found', ['location' => $location]);

            return false;
        }

        // Get the folder content.
        $hits = new Finder();
        $hits = $hits->in($location);

        // Handle files.
        $this->logger->debug('Found '.count($hits->files()).'files for installer', ['location' => $location, 'files' => count($hits->files())]);

        if (count($hits->files()) > 32) {
            $this->logger->warning('Found more then 32 files in directory, try limiting your files to 32 per directory', ['location' => $location, 'files' => count($hits->files())]);
        }

        foreach ($hits->files() as $file) {
            $this->readfile($file);
        }

        return true;
    }//end readDirectory()

    /**
     * This function read a folder to find other folders or json objects.
     *
     * @param File $file The file location
     *
     * @return bool|array The file contents, or false if content could not be establisched
     */
    private function readfile(File $file): mixed
    {

        // Check if it is a valid json object.
        $mappingSchema = json_decode($file->getContents(), true);
        if ($mappingSchema === false) {
            $this->logger->error($file->getFilename().' is not a valid json object');

            return false;
        }

        // Check if it is a valid schema.
        $mappingSchema = $this->validateJsonMapping($mappingSchema);

        if ($this->validateJsonMapping($mappingSchema) === true) {
            $this->logger->error($file->getFilename().' is not a valid json-mapping object');

            return false;
        }

        // Add the file to the object.
        return $this->addToObjects($mappingSchema);
    }//end readfile()

    /**
     * Adds an object to the objects stack if it is vallid.
     *
     * @param array $schema The schema
     *
     * @return bool|array The file contents, or false if content could not be establisched
     */
    private function addToObjects(array $schema): mixed
    {

        // It is a schema so lets save it like that.
        if (array_key_exists('$schema', $schema) === true) {
            $this->objects[$schema['$schema']] = $schema;

            return $schema;
        }

        // If it is not a schema of itself it might be an array of objects.
        foreach ($schema as $key => $value) {
            if (is_array($value) === true) {
                $this->objects[$key] = $value;
                continue;
            }

            // The use of gettype is discoureged, but we don't use it as a bl here and only for logging text purposes. So a design decicion was made te allow it.
            $this->logger->error('Expected to find array for schema type '.$key.' but found '.gettype($value).' instead', ['value' => $value, 'schema' => $key]);
        }

        return true;
    }//end addToObjects()


    /**
     * Handels schemas of a certain type
     *
     * @param array $schemas The schemas to handle
     * @return void
     */
    private function handleObjectType(array $schemas):void
    {
        foreach ($schemas as $schema) {
            $object = $this->handleObject($schema);
            // Save it to the database.
            $this->entityManager->persist($object);
        }

        return;
    }//end handleObjectType();

    /**
     * Create an object bases on an type and a schema (the object as an array).
     *
     * This function breaks complexity rules, but since a switch is the most effective way of doing it a design decicion was made to allow it
     *
     * @param string $type   The type of the object
     * @param array  $schema The object as an array
     *
     * @return bool|object
     */
    private function handleObject(string $type, array $schema): bool
    {
        // Only base we need it the assumption that on object isn't valid until we made is so.
        $object = null;

        // For security reasons we define allowed resources.
        $allowdCoreObjects
            = [
                'https://docs.commongateway.nl/schemas/Action.schema.json',
                'https://docs.commongateway.nl/schemas/Entity.schema.json',
                'https://docs.commongateway.nl/schemas/Mapping.schema.json',
                'https://docs.commongateway.nl/schemas/Organization.schema.json',
                'https://docs.commongateway.nl/schemas/Application.schema.json',
                'https://docs.commongateway.nl/schemas/User.schema.json',
                'https://docs.commongateway.nl/schemas/SecurityGroup.schema.json',
                'https://docs.commongateway.nl/schemas/Cronjob.schema.json',
                'https://docs.commongateway.nl/schemas/Endpoint.schema.json',
            ];

        // Handle core schema's.
        if (in_array($type, $allowdCoreObjects) === true) {
            $object = $this->loadCoreSchema($schema, $type);
        }//end if

        // Handle Other schema's.
        if (in_array($type, $allowdCoreObjects) === false) {
            $object = $this->loadSchema($schema, $type);
        }//end if

        // Lets see if it is a new object.
        if ($this->entityManager->contains($object) === false) {
            $this->loger->info(
                'A new object has been created trough the installation service',
                [
                    'class'  => get_class($object),
                    'object' => $object->toSchema(),
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
     * @return ObjectEntity The loaded object
     */
    private function loadCoreSchema(array $schema, string $type): ObjectEntity
    {
        // Clearup the entity.
        $entity = str_replace('https://docs.commongateway.nl/schemas/', '', $type);
        $entity = str_replace('.schema.json', '', $entity);

        // Load it if we have it.
        if (array_key_exists('$id', $schema) === true) {
            $object = $this->entityManager->getRepository('App:'.$entity)->findOneBy(['reference' => $schema['$id']]);
        }

        // Create it if we don't.
        if ($object === null) {
            $object = new $type();
        }

        // Load the data.
        if (array_key_exists('version', $schema) === true && version_compare($schema['version'], $object->getVersion()) <= 0) {
            $this->loger->debug('The new mapping has a version number equal or lower then the already present version, the object is NOT is updated', ['schemaVersion' => $schema['version'], 'objectVersion' => $object->getVersion()]);
        } else if (array_key_exists('version', $schema) === true && version_compare($schema['version'], $object->getVersion()) < 0) {
            $this->loger->debug('The new mapping has a version number higher then the already present version, the object is data is updated', ['schemaVersion' => $schema['version'], 'objectVersion' => $object->getVersion()]);
            $object->fromSchema($schema);
        } else if (array_key_exists('version', $schema) === false) {
            $this->loger->debug('The new mapping don\'t have a version number, the object is data is updated', ['schemaVersion' => $schema['version'], 'objectVersion' => $object->getVersion()]);
            $object->fromSchema($schema);
        }

        return $object;
    }//end loadCoreSchema()

    /**
     * This function loads an non-core schema.
     *
     * @param array  $schema The schema
     * @param string $type   The type of the schema
     *
     * @return ObjectEntity The loaded object
     */
    private function loadSchema(array $schema, string $type): ObjectEntity
    {
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $type]);
        if ($entity === null) {
            $this->logger->error('trying to create data for non-exisitng entity', ['reference' => $type]);

            return false;
        }

        // If we have an id let try to grab an object.
        if (array_key_exists('id', $schema) === true) {
            $object = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $schema['$id']]);
        }

        // Create it if we don't.
        if ($object === null) {
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
     * @param $file The installation file
     *
     * @return bool
     */
    private function handleInstaller($file): bool
    {
        $data = json_decode($file->getContents(), true);

        if ($data === false) {
            $this->logger->error($file->getFilename().' is not a valid json object');

            return false;
        }

        // Endpoints for schema's.
        if (isset($data['endpoints']['schemas']) === true) {
            $this->createEndpoints($data['endpoints']['schemas']);
        }

        // Actions for action handlers.
        if (isset($data['actions']['handlers']) === true) {
            $this->createActions($data['actions']['handlers']);
        }

        // Cronjobs for actions for action handlers.
        if (isset($data['cronjobs']['actions']) === true) {
            $this->createCronjobs($data['cronjobs']['actions']);
        }

        // Lets see if we have things that we want to create cards for stuff (ince this might create cards for the stuff above this should always be last).
        if (isset($data['cards']) === true) {
            $this->createCards($data['cards']);
        }

        if (isset($data['installationService']) === false || $installationService = $data['installationService'] === false) {
            $this->logger->error($file->getFilename().' Doesn\'t contain an installation service');

            return true;
        }

        if ($installationService = $this->container->get($installationService) === false) {
            $this->logger->error($file->getFilename().' Could not be loaded from container');

            return false;
        }

        return $installationService->install();
    }//end handleInstaller()



    /**
     * This functions creates dashboard cars for an array of endpoints, sources, schema's or objects
     *
     * @param array $handlers An array of references of handlers for wih actions schould be created
     * @return array An array of Action objects
     */
    private function createCards(array $handlers = []): array
    {
        $cards = [];

        // Lets loop trough the stuff.
        foreach ($handlers as $type => $references) {
            // Let's deterimin the propper repro to use.
            switch ($type) {
                case 'endpoints':
                    $repository = $this->entityManager->getRepository('App:Endpoint');
                    break;
                case 'sources':
                    $repository = $this->entityManager->getRepository('App:Source');
                    break;
                case 'schemas':
                    $repository = $this->entityManager->getRepository('App:Entity');
                    break;
                case 'cronjobs':
                    $repository = $this->entityManager->getRepository('App:Cronjob');
                    break;
                case 'objects':
                    $repository = $this->entityManager->getRepository('App:ObjectEntity');
                    break;
                default:
                    // Euhm we cant't do anything so...
                    $this->logger->error('Unknown type used for the creation of a dashboard card '.$type);
                    continue;
            }//end switch

            // Then we can handle some data.
            foreach ($references as $reference) {
                $object = $repository->findOneBy(['reference' => $reference]);

                if ($object === null) {
                    $this->logger->error('No object found for '.$reference);
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
     * This function creates endpoints for an array of schema references
     *
     * @param array $schemas An array of references of schema's for wich endpoints hould be created
     * @return array An array of endpoints
     */
    private function createEndpoints(array $schemas = []): array
    {
        $endpointRepository = $this->entityManager->getRepository('App:Endpoint');
        $endpoints = [];

        foreach ($schemas as $schema) {
            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $schema['reference']]);

            if ($endpointRepository->findOneBy(['name' => $entity->getName()]) === false) {
                $endpoint = new Endpoint($entity, $schema['path'], $schema['methods']);

                $this->logger->debug('Endpoint created for '.$schema['reference']);
                $this->entityManager->persist($endpoint);
                $endpoints[] = $endpoint;
            }
        }

        $this->logger->info(count($endpoints).' Endpoints Created');

        return $endpoints;
    }//end createEndpoints()

    /**
     * This functions creates actions for an array of handlers
     *
     * @param array $handlers An array of references of handlers for wih actions schould be created
     * @return array An array of Action objects
     */
    private function createActions(array $handlers = []): array
    {
        $actions = [];

        foreach ($handlers as $handler) {
            $actionHandler = $this->container->get($handler);

            if ($this->entityManager->getRepository('App:Action')->findOneBy(['class' => get_class($actionHandler)]) === null) {
                $this->logger->error('Action found for '.$handler);
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
            $this->logger->debug('Action created for '.$handler);
        }


        $this->logger->info(count($actions).' Actions Created');

        return $actions;
    }//end createActions()

    /**
     * This function creates cronjobs for an array of action references
     *
     * @param array $actions An array of references of actions for wih actions cronjobs be created
     * @return array An array of cronjobs
     */
    private function createCronjobs(array $actions = []): array
    {
        $cronjobs = [];

        foreach ($actions as $reference) {
            $action = $this->entityManager->getRepository('App:Cronjob')->findOneBy(['reference' => $reference]);

            if ($action === null) {
                $this->logger->error('No action found for reference'.$reference);
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
