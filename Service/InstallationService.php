<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Action;
use App\Entity\Entity;
use App\Entity\Mapping;
use App\Entity\ObjectEntity;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

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
     * @param array $config The (optional) configuration
     *
     * @return int
     */
    public function composerupdate(array $config = []): int
    {
        $plugins = $this->composerService->getAll();

        $this->logger->debug('Running plugin installer');

        foreach ($plugins as $plugin) {
            $this->install($plugin['name'], $config);
        }

        return Command::SUCCESS;
    }//end composerupdate()

    /**
     * Installs the files from a bundle
     *
     * Based on the default action handler so schould supoprt a config parrameter even if we do not use it
     *
     * @param string $bundle The bundle
     * @param array $config Optional config (ignored on this function)
     * @return bool The result of the installation
     */
    public function install(string $bundle, array $config = []): bool
    {
        $this->logger->debug('Installing plugin '.$bundle, ['bundle' => $bundle]);

        $vendorFolder = 'vendor';

        // Lets check the basic folders for lagacy pruposes.
        $this->logger->debug('Installing plugin '.$bundle);
        $this->readDirectory($vendorFolder.'/'.$bundle.'/Action');
        $this->readDirectory($vendorFolder.'/'.$bundle.'/Schema');
        $this->readDirectory($vendorFolder.'/'.$bundle.'/Mapping');
        $this->readDirectory($vendorFolder.'/'.$bundle.'/Data');
        $this->readDirectory($vendorFolder.'/'.$bundle.'/Installation');

        // Handling al the files.
        $this->logger->debug('Found '.count($this->objects).' schema types for '.$bundle, ['bundle' => $bundle]);

        foreach ($this->objects as $ref => $schemas) {
            $this->logger->debug('Found '.count($schemas).' objects types for schema '.$ref, ['bundle' => $bundle, 'reference' => $ref]);
            foreach ($schemas as $schema) {
                $object = $this->handleObject($schema);
                // Save it to the database.
                $this->entityManager->persist($object);
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
    public function readDirectory(string $location): bool
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
            $this->logger->warning('Found more then 32 files in directory, try limiting your files to 32 per directory',["location" => $location,"files" => count($hits->files())]);
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
    public function readfile(File $file): mixed
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
    public function addToObjects(array $schema): mixed
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
     * Create an object bases on an type and a schema (the object as an array).
     *
     * This function breaks complexity rules, but since a switch is the most effective way of doing it a design decicion was made to allow it
     *
     * @param string $type   The type of the object
     * @param array  $schema The object as an array
     *
     * @return bool|object
     */
    public function handleObject(string $type, array $schema): bool
    {
        // Only base we need it the assumption that on object isn't valid until we made is so.
        $object = null;

        // For security reasons we define allowed resources.
        $allowdCoreObjects
            = [
                'https://docs.commongateway.nl/schemas/Action.schema.json',
                'https://docs.commongateway.nl/schemas/Action.schema.json',
                'https://docs.commongateway.nl/schemas/Entity.schema.json',
                'https://docs.commongateway.nl/schemas/Mapping.schema.json',
                'https://docs.commongateway.nl/schemas/Organization.schema.json',
                'https://docs.commongateway.nl/schemas/Application.schema.json',
                'https://docs.commongateway.nl/schemas/User.schema.json',
                'https://docs.commongateway.nl/schemas/SecurityGroup.schema.json',
                'https://docs.commongateway.nl/schemas/Cronjob.schema.json',
                'https://docs.commongateway.nl/schemas/Endpoint.schema.json'
            ];

        // Handle core schema's.
        if (in_array($type, $allowdCoreObjects) === true) {
            // Clearup the entity.
            $entity = str_replace("https://docs.commongateway.nl/schemas/", "",$type);
            $entity = str_replace(".schema.json", "",$entity);

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
        }//end if

        // Handle Other schema's.
        if (in_array($type, $allowdCoreObjects) === false) {
            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $type]);
            if ($entity === null) {
                $this->logger->error('trying to create data for non-exisitng entity', ['reference' => $type, 'object' => $object->toSchema()]);

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
     * Specifcially handles the installation file.
     *
     * @param $file The installation file
     *
     * @return bool
     */
    public function handleInstaller($file): bool
    {
        $data = json_decode($file->getContents(), true);
        if ($data === false) {
            $this->logger->error($file->getFilename().' is not a valid json object');

            return false;
        }

        if (isset($data['installationService']) === false || $installationService = $data['installationService'] === false) {
            $this->logger->error($file->getFilename().' Doesn\'t contain an installation service');

            return false;
        }

        if ($installationService = $this->container->get($installationService) === false) {
            $this->logger->error($file->getFilename().' Could not be loaded from container');

            return false;
        }

        $installationService->setStyle($this->io);

        return $installationService->install();
    }//end handleInstaller()
}//end class
