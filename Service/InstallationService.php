<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Action;
use App\Entity\CollectionEntity;
use App\Entity\Entity;
use App\Entity\Mapping;
use App\Entity\ObjectEntity;
use App\Entity\Value;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * The installation service handled the installation of plugins (bundles) and is based on composer and packagist.
 *
 * @Author Wilco Louwerse <wilco@conduction.nl>, Robert Zondervan <robert@conduction.nl>, Ruben van der Linde <ruben@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class InstallationService
{
    /**
     * @var ComposerService The composer service.
     */
    private ComposerService $composerService;

    /**
     * @var EntityManagerInterface The entity manager.
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var SymfonyStyle Symfony style for user feedback in command line.
     */
    private SymfonyStyle $symfonyStyle;

    /**
     * @var mixed Holds the symfony container interface.
     */
    private $container;

    /**
     * @var LoggerInterface The logger interface.
     */
    private LoggerInterface $logger;

    /**
     * @var CacheService The cache service.
     */
    private CacheService $cacheService;

    /**
     * @var CollectionEntity|null A collectionEntity.
     */
    private ?CollectionEntity $collection;

    /**
     * @param ComposerService        $composerService The composer service.
     * @param EntityManagerInterface $entityManager   The entity manager.
     * @param Kernel                 $kernel          Todo ?
     * @param CacheService           $cacheService    The cache service.
     * @param LoggerInterface        $pluginLogger    The logger interface.
     */
    public function __construct(
        ComposerService $composerService,
        EntityManagerInterface $entityManager,
        Kernel $kernel,
        CacheService $cacheService,
        LoggerInterface $pluginLogger
    ) {
        $this->composerService = $composerService;
        $this->entityManager = $entityManager;
        $this->container = $kernel->getContainer();
        $this->collection = null;
        $this->logger = $pluginLogger;
        $this->cacheService = $cacheService;
    }//end __construct()

    /**
     * Set symfony style in order to output to the console.
     *
     * @param SymfonyStyle $symfonyStyle Symfony style for user feedback in command line.
     *
     * @return self This installationService.
     */
    public function setStyle(SymfonyStyle $symfonyStyle): self
    {
        $this->symfonyStyle = $symfonyStyle;

        return $this;
    }

    /**
     * Updates all commonground bundles on the common gateway installation.
     *
     * @param array $config
     *
     * @return int
     */
    public function composerupdate(array $config = []): int
    {
        $plugins = $this->composerService->getAll();

        if (isset($this->symfonyStyle) === true) {
            $this->symfonyStyle->writeln([
                '',
                '<info>Common Gateway Bundle Updater</info>',
                '============',
                '',
                'Found: <comment> '.count($plugins).' </comment> to check for updates',
                '',
            ]);
        }

        $this->logger->debug('Running plugin installer');

        foreach ($plugins as $plugin) {
            $this->install($plugin['name'], $config);
        }

        if (isset($this->symfonyStyle) === true) {
            $this->cacheService->setStyle($this->symfonyStyle);
            $this->cacheService->warmup();
        }

        return Command::SUCCESS;
    }

    /**
     * Validates the  objects in the EAV setup.
     *
     * @return void Nothing.
     */
    public function validateObjects(): int
    {
        $objects = $this->entityManager->getRepository('App:ObjectEntity')->findAll();

        if (isset($this->symfonyStyle) === true) {
            $this->symfonyStyle->writeln([
                'Validating: <comment> '.count($objects).' </comment> objects\'s',
            ]);
        }
        $this->logger->info('Validating:'.count($objects).'objects\'s');

        // Lets go go go !
        foreach ($objects as $object) {
            if ($object->get) {
            }
        }
    }

    /**
     * Validates the  objects in the EAV setup.
     *
     * @return void Nothing.
     */
    public function validateValues(): int
    {
        $values = $this->entityManager->getRepository('App:Value')->findAll();

        $this->logger->debug('Validating:'.count($values).'values\'s');

        // Lets go go go !
        foreach ($values as $value) {
            if (!$value->getObjectEntity()) {
                $message = 'Value '.$value->getStringValue().' ('.$value->getId().') that belongs to  '.$value->getAttribute()->getName().' ('.$value->getAttribute()->getId().') is orpahned';
            }
        }
    }

    /**
     * Validates the schemas in the EAV setup.
     *
     * @return void Nothing.
     */
    public function validateSchemas(): int
    {
        $schemas = $this->entityManager->getRepository('App:Entity')->findAll();

        $this->logger->debug('Validating:'.count($schemas).'schema\'s');

        // Lets go go go !
        foreach ($schemas as $schema) {
            $statusOk = true;
            // Gereric check
            if (!$schema->getReference()) {
                $this->logger->info('Schema '.$schema->getName().' ('.$schema->getId().') dosn\t have a reference');
            }

            // Gereric check
            /*
            if(!$schema->getApplication()){
                $this->logger->info( 'Schema '.$schema->getName().' ('.$schema->getId().') dosn\t have a application');
            }
            // Gereric check
            if(!$schema->getOrganization()){
                $this->logger->info( 'Schema '.$schema->getName().' ('.$schema->getId().') dosn\t have a organization');
            }
            */

            // Check atributes
            foreach ($schema->getAttributes() as $attribute) {
                // Specific checks for objects
                if ($attribute->getType() == 'object') {

                    // Check for object link
                    if (!$attribute->getObject()) {
                        $message = 'Schema '.$schema->getName().' ('.$schema->getId().') has attribute '.$attribute->getName().' ('.$attribute->getId().') that is of type Object but is not linked to an object';
                        $this->logger->error($message);
                        if (isset($this->symfonyStyle) === true) {
                            $this->symfonyStyle->error($message);
                        }
                        $statusOk = false;
                    } else {
                        $message = 'Schema '.$schema->getName().' ('.$schema->getId().') has attribute '.$attribute->getName().' ('.$attribute->getId().') that is linked to object '.$attribute->getObject()->getName().' ('.$attribute->getObject()->getId();
                        $this->logger->debug($message);
                        if (isset($this->symfonyStyle) === true) {
                            $this->symfonyStyle->note($message);
                        }
                    }
                    // Check for reference link
                    if (!$attribute->getReference()) {

                        //$message = 'Schema '.$schema->getName().' ('.$schema->getId().') has attribute '.$attribute->getName().' ('.$attribute->getId().') that is of type Object but is not linked to an reference';
                        //$this->logger->info($message);
                        //if ($this->symfonyStyle) { $this->symfonyStyle->info($message);}
                    }
                }

                // Specific wierdnes
                // Check for reference link
                if ($attribute->getReference() && !$attribute->getType() == 'object') {
                    $message = 'Schema '.$schema->getName().' ('.$schema->getId().') has attribute '.$attribute->getName().' ('.$attribute->getId().') that has a reference ('.$attribute->getReference().') but isn\'t of the type object';
                    $this->logger->error($message);
                    if (isset($this->symfonyStyle) === true) {
                        $this->symfonyStyle->error($message);
                    }
                    $statusOk = false;
                }
            }

            if ($statusOk) {
                $message = 'Schema '.$schema->getName().' ('.$schema->getId().') has been checked and is fine';
                $this->logger->info($message);
                if (isset($this->symfonyStyle) === true) {
                    $this->symfonyStyle->info($message);
                }
            } else {
                $message = 'Schema '.$schema->getName().' ('.$schema->getId().') has been checked and has an error';
                $this->logger->error($message);
                if (isset($this->symfonyStyle) === true) {
                    $this->symfonyStyle->error($message);
                }
            }
        }

        return 1;
    }

    /**
     * Performs installation actions on a common Gataway bundle.
     *
     * @param string $bundle The bundle name that you want to install
     * @param array  $config Optional configuration
     *
     * @return int
     */
    public function install(string $bundle, array $config = []): int
    {
        if (isset($this->symfonyStyle) === true) {
            $this->symfonyStyle->writeln([
                'Trying to install: <comment> '.$bundle.' </comment>',
                '',
            ]);
        }

        $this->logger->debug('Trying to install: '.$bundle);

        $packages = $this->composerService->getAll();

        $found = array_filter($packages, function ($v, $k) use ($bundle) {
            return $v['name'] == $bundle;
        }, ARRAY_FILTER_USE_BOTH); // With the latest PHP third parameter is optional.. Available Values:- ARRAY_FILTER_USE_BOTH OR ARRAY_FILTER_USE_KEY

        $package = reset($found);
        if ($package) {
            $this->symfonyStyle->writeln([
                '<info>Package '.$bundle.' found</info>',
                '',
                'Name: '.$package['name'],
                'Version: '.$package['version'],
                'Description: '.$package['description'],
                'Homepage :'.$package['homepage'],
                'Source: '.$package['source']['url'],
            ]);
        } else {
            $this->symfonyStyle->error($bundle.' not found');

            return Command::FAILURE;
        }

        $vendorFolder = 'vendor';
        $filesystem = new Filesystem();

        // Handling the actions's
        $this->symfonyStyle->section('Looking for actions\'s');
        $actionDir = $vendorFolder.'/'.$bundle.'/Action';
        if ($filesystem->exists($actionDir)) {
            $this->symfonyStyle->writeln('Action folder found');
            $actions = new Finder();
            $actions = $actions->in($actionDir);
            $this->symfonyStyle->writeln('Files found: '.count($actions));
            foreach ($actions->files() as $action) {
                $this->handleAction($action);
            }

            //$progressBar->finish();
        } else {
            $this->symfonyStyle->writeln('No action folder found');
        }

        // Handling the mappings
        $this->symfonyStyle->section('Looking for mappings\'s');
        $mappingDir = $vendorFolder.'/'.$bundle.'/Mapping';
        if ($filesystem->exists($mappingDir)) {
            $this->symfonyStyle->writeln('Mapping folder found');
            $mappings = new Finder();
            $mappings = $mappings->in($mappingDir);
            $this->symfonyStyle->writeln('Files found: '.count($mappings));

            foreach ($mappings->files() as $mapping) {
                $this->handleMapping($mapping);
            }

            //$progressBar->finish();
        } else {
            $this->symfonyStyle->writeln('No mapping folder found');
        }

        // Handling the schema's
        $this->symfonyStyle->section('Looking for schema\'s');
        $schemaDir = $vendorFolder.'/'.$bundle.'/Schema';

        if ($filesystem->exists($schemaDir)) {
            $this->symfonyStyle->writeln('Schema folder found');
            $schemas = new Finder();
            $schemas = $schemas->in($schemaDir);
            $this->symfonyStyle->writeln('Files found: '.count($schemas));

            // We want each plugin to also be a collection (if it contains schema's that is)
            if (count($schemas) > 0) {
                if (!$this->collection = $this->entityManager->getRepository('App:CollectionEntity')->findOneBy(['plugin' => $package['name']])) {
                    $this->logger->debug('Created a collection for plugin '.$bundle);
                    $this->symfonyStyle->writeln(['Created a collection for this plugin', '']);
                    $this->collection = new CollectionEntity();
                    $this->collection->setName($package['name']);
                    $this->collection->setPlugin($package['name']);
                    isset($package['description']) && $this->collection->setDescription($package['description']);
                } else {
                    $this->symfonyStyle->writeln(['Found a collection for this plugin', '']);
                    $this->logger->debug('Found a collection for plugin '.$bundle);
                }
            }

            // Persist collection
            if (isset($this->collection)) {
                $this->entityManager->persist($this->collection);
                $this->entityManager->flush();
            }
            foreach ($schemas->files() as $schema) {
                $this->handleSchema($schema);
            }

            //$progressBar->finish();
        } else {
            $this->symfonyStyle->writeln('No schema folder found');
            $this->logger->debug('No schema folder found for plugin '.$bundle);
        }

        // Handling the data
        $this->symfonyStyle->section('Looking for data');
        if (array_key_exists('data', $config) && $config['data']) {
            $dataDir = $vendorFolder.'/'.$bundle.'/Data';

            if ($filesystem->exists($dataDir)) {
                $this->symfonyStyle->writeln('Data folder found');
                $datas = new Finder();
                $datas = $datas->in($dataDir);
                $this->symfonyStyle->writeln('Files found: '.count($datas));

                foreach ($datas->files() as $data) {
                    $this->handleData($data);
                }

                // We need to clear the finder
            } else {
                $this->logger->debug('No data folder found for plugin '.$bundle);
                $this->symfonyStyle->writeln('No data folder found');
            }
        } else {
            $this->symfonyStyle->warning('No test data loaded for bundle, run command with -data to load (test) data');
        }

        // Handling the installations
        $this->symfonyStyle->section('Looking for installers');
        $installationDir = $vendorFolder.'/'.$bundle.'/Installation';
        if ($filesystem->exists($installationDir)) {
            $this->symfonyStyle->writeln('Installation folder found');
            $installers = new Finder();
            $installers = $installers->in($installationDir);
            $this->symfonyStyle->writeln('Files found: '.count($installers));

            foreach ($installers->files() as $installer) {
                $this->handleInstaller($installer);
            }
        } else {
            $this->logger->debug('No Installation folder found for plugin '.$bundle);
            $this->symfonyStyle->writeln('No Installation folder found');
        }

        $this->symfonyStyle->success('All Done');
        $this->logger->debug('All Done installing plugin '.$bundle);

        return Command::SUCCESS;
    }

    /**
     * @param string $bundle The bundle that you want to update
     * @param array  $config Optional configuration
     *
     * @return mixed
     */
    public function update(string $bundle, array $config = [])
    {
        $this->logger->debug('Trying to update: '.$bundle, ['bundle' => $bundle]);

        return Command::SUCCESS;
    }

    /**
     * @param string $bundle The bundle that you want to uninstall (delete))
     * @param array  $config Optional configuration
     *
     * @return mixed
     */
    public function uninstall(string $bundle, string $data)
    {
        $this->logger->debug('Trying to uninstall: '.$bundle, ['bundle' => $bundle]);

        return Command::SUCCESS;
    }

    /**
     * @param $file
     *
     * @return false|void
     */
    public function handleAction($file)
    {
        if (!$actionSchema = json_decode($file->getContents(), true)) {
            $this->symfonyStyle->writeln($file->getFilename().' is not a valid json object');

            return false;
        }

        if (!$this->validateJsonAction($actionSchema)) {
            $this->symfonyStyle->writeln($file->getFilename().' is not a valid json-schema object');

            return false;
        }

        if (!$actionObject = $this->entityManager->getRepository('App:Action')->findOneBy(['reference' => $actionSchema['$id']])) {
            $this->symfonyStyle->writeln('Action not present, creating action '.$actionSchema['title'].' under reference '.$actionSchema['$id']);
            $actionObject = new Action();
        } else {
            $this->symfonyStyle->writeln('Action already present, looking to update');
            if (array_key_exists('version', $actionSchema) && version_compare($actionSchema['version'], $actionObject->getVersion()) < 0) {
                $this->symfonyStyle->writeln('The new action has a version number equal or lower then the already present version');
            }
        }

        $actionObject->fromSchema($actionSchema);

        $this->entityManager->persist($actionObject);

        $this->entityManager->flush();
        $this->symfonyStyle->writeln('Done with action '.$actionObject->getName());
    }

    /**
     * @param $file
     *
     * @return false|void
     */
    public function handleMapping($file)
    {
        if (!$mappingSchema = json_decode($file->getContents(), true)) {
            $this->symfonyStyle->writeln($file->getFilename().' is not a valid json object');

            return false;
        }

        if (!$this->validateJsonMapping($mappingSchema)) {
            $this->symfonyStyle->writeln($file->getFilename().' is not a valid json-mapping object');

            return false;
        }

        if (!$mappingObject = $this->entityManager->getRepository('App:Mapping')->findOneBy(['reference' => $mappingSchema['$id']])) {
            $this->symfonyStyle->writeln('Maping not present, creating mapping '.$mappingSchema['title'].' under reference '.$mappingSchema['$id']);
            $mappingObject = new Mapping();
        } else {
            $this->symfonyStyle->writeln('Mapping already present, looking to update');
            if (array_key_exists('version', $mappingSchema) && version_compare($mappingSchema['version'], $mappingObject->getVersion()) < 0) {
                $this->symfonyStyle->writeln('The new mapping has a version number equal or lower then the already present version');
            }
        }

        $mappingObject->fromSchema($mappingSchema);

        $this->entityManager->persist($mappingObject);
        $this->entityManager->flush();
        $this->symfonyStyle->writeln('Done with mapping '.$mappingObject->getName());
    }

    /**
     * @param $file
     *
     * @return false|void
     */
    public function handleSchema($file)
    {
        if (!$entitySchema = json_decode($file->getContents(), true)) {
            $this->symfonyStyle->writeln($file->getFilename().' is not a valid json object');

            return false;
        }

        if (!$this->validateJsonSchema($entitySchema)) {
            $this->symfonyStyle->writeln($file->getFilename().' is not a valid json-schema object');

            return false;
        }

        if (!$entityObject = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $entitySchema['$id']])) {
            $this->symfonyStyle->writeln('Schema not present, creating schema '.$entitySchema['title'].' under reference '.$entitySchema['$id']);
            $entityObject = new Entity();
        } else {
            $this->symfonyStyle->writeln('Schema already present, looking to update');
            if (array_key_exists('version', $entitySchema) && version_compare($entitySchema['version'], $entityObject->getVersion()) < 0) {
                $this->symfonyStyle->writeln('The new schema has a version number equal or lower then the already present version');
            }
        }

        $entityObject->fromSchema($entitySchema);

        $this->entityManager->persist($entityObject);

        // Add the schema to collection
        if (isset($this->collection)) {
            $entityObject->addCollection($this->collection);
        }

        $this->entityManager->flush();
        $this->symfonyStyle->writeln('Done with schema '.$entityObject->getName());
    }

    /**
     * Perform a very basic check to see if a schema file is a valid json-action file.
     *
     * @param array $schema
     *
     * @return bool
     */
    public function validateJsonAction(array $schema): bool
    {
        if (
            array_key_exists('$id', $schema) &&
            array_key_exists('$schema', $schema) &&
            $schema['$schema'] == 'https://json-schema.org/draft/2020-12/action' &&
            array_key_exists('listens', $schema) &&
            array_key_exists('class', $schema)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Perform a very basic check to see if a schema file is a valid json-mapping file.
     *
     * @param array $schema
     *
     * @return bool
     */
    public function validateJsonMapping(array $schema): bool
    {
        if (
            array_key_exists('$id', $schema) &&
            array_key_exists('$schema', $schema) &&
            $schema['$schema'] == 'https://json-schema.org/draft/2020-12/mapping'
        ) {
            return true;
        }

        return false;
    }

    /**
     * Performce a very basic check to see if a schema file is a valid json-schema file.
     *
     * @param array $schema
     *
     * @return bool
     */
    public function validateJsonSchema(array $schema): bool
    {
        if (
            array_key_exists('$id', $schema) &&
            array_key_exists('$schema', $schema) &&
            $schema['$schema'] == 'https://json-schema.org/draft/2020-12/schema' &&
            array_key_exists('type', $schema) &&
            $schema['type'] == 'object' &&
            array_key_exists('properties', $schema)
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param $file
     *
     * @return false|void
     */
    public function handleData($file)
    {
        if (!$data = json_decode($file->getContents(), true)) {
            $this->symfonyStyle->writeln($file->getFilename().' is not a valid json object');

            return false;
        }

        foreach ($data as $reference => $objects) {
            // Lets see if we actuelly have a shema to upload the objects to
            if (!$entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $reference])) {
                $this->symfonyStyle->writeln('No Schema found for reference '.$reference);
                continue;
            }

            $this->symfonyStyle->writeln([
                '',
                '<info> Found data for schema '.$reference.'</info> containing '.count($objects).' object(s)',
            ]);

            // Then we can handle data
            foreach ($objects as $object) {
                // Lets see if we need to update

                // Backwarsd competability
                if (isset($object['_id'])) {
                    $object['id'] = $object['_id'];
                    unset($object['_id']);
                }

                if (isset($object['id']) && $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $object['id']])) {
                    $this->symfonyStyle->writeln(['', 'Object '.$object['id'].' already exists, so updating']);
                } else {
                    $objectEntity = new ObjectEntity($entity);
                }

                $this->symfonyStyle->writeln('Writing data to the object');

                $this->saveOnFixedId($objectEntity, $object);

                $this->symfonyStyle->writeln(['Object saved as '.$objectEntity->getId(), '']);
            }
        }
    }

    /**
     * @param $file
     *
     * @return false
     */
    public function handleInstaller($file)
    {
        if (!$data = json_decode($file->getContents(), true)) {
            $this->symfonyStyle->writeln($file->getFilename().' is not a valid json object');

            return false;
        }

        if (!isset($data['installationService']) || !$installationService = $data['installationService']) {
            $this->symfonyStyle->writeln($file->getFilename().' Doesn\'t contain an installation service');

            return false;
        }

        if (!$installationService = $this->container->get($installationService)) {
            $this->symfonyStyle->writeln($file->getFilename().' Could not be loaded from container');

            return false;
        }

        $installationService->setStyle($this->symfonyStyle);

        return $installationService->install();
    }

    /**
     * Handles forced id's on object entities.
     *
     * @param ObjectEntity $objectEntity
     * @param array        $hydrate
     *
     * @return ObjectEntity
     */
    private function saveOnFixedId(ObjectEntity $objectEntity, array $hydrate = []): ObjectEntity
    {
        // This savetey dosn't make sense but we need it
        if (!$objectEntity->getEntity()) {
            $this->logger->error('Object can\'t be persisted due to missing schema');
            $this->symfonyStyle->writeln(['', 'Object can\'t be persisted due to missing schema']);

            return $objectEntity;
        }

        // Save the values
        //$values = $objectEntity->getObjectValues()->toArray();
        //$objectEntity->clearAllValues();

        // We have an object entity with a fixed id that isn't in the database, so we need to act
        if (isset($hydrate['id']) && !$this->entityManager->contains($objectEntity)) {
            $this->symfonyStyle->writeln(['Creating new object ('.$objectEntity->getEntity()->getName().') on a fixed id ('.$hydrate['id'].')']);

            // save the id
            $id = $hydrate['id'];
            // Create the entity
            $this->entityManager->persist($objectEntity);
            $this->entityManager->flush();
            $this->entityManager->refresh($objectEntity);
            // Reset the id
            $objectEntity->setId($id);
            $this->entityManager->persist($objectEntity);
            $this->entityManager->flush();
            $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $id]);

            $this->symfonyStyle->writeln(['Defintive object id ('.$objectEntity->getId().')']);
        } else {
            $this->symfonyStyle->writeln(['Creating new object ('.$objectEntity->getEntity()->getName().') on a generated id']);
        }

        // We already dit this so let's skip it
        unset($hydrate['_id']);

        foreach ($hydrate as $key => $value) {
            // Try to get a value object
            $valueObject = $objectEntity->getValueObject($key);

            // If we find the Value object we set the value
            if ($valueObject instanceof Value) {
                // Value is an array so let's create an object
                if ($valueObject->getAttribute()->getType() == 'object') {

                    // I hate arrays
                    if ($valueObject->getAttribute()->getMultiple()) {
                        $this->symfonyStyle->info('an array for objects
                        ');
                        if (is_array($value)) {
                            foreach ($value as $subvalue) {
                                // Savety
                                if (!$valueObject->getAttribute()->getObject()) {
                                    continue;
                                }
                                // is array

                                if (is_array($subvalue)) {
                                    $newObject = new ObjectEntity($valueObject->getAttribute()->getObject());
                                    $newObject = $this->saveOnFixedId($newObject, $subvalue);
                                    $valueObject->addObject($newObject);
                                }
                                // Is not an array
                                else {
                                    $idValue = $subvalue;
                                    $subvalue = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $idValue]);
                                    // Savety
                                    if (!$subvalue) {
                                        $this->symfonyStyle->error('Could not find an object for id '.$idValue);
                                    } else {
                                        $valueObject->addObject($subvalue);
                                    }
                                }
                            }
                        } else {
                            $this->symfonyStyle->error($valueObject->getAttribute()->getName().' Is a multiple so should be filled with an array, but provided value was '.$value.'(type: '.gettype($value).')');
                        }
                        continue;
                    }
                    // End of array hate, we are friends again

                    // is array
                    if (is_array($value)) {
                        // Savety
                        if (!$valueObject->getAttribute()->getObject()) {
                            $this->symfonyStyle->error('Could not find an object for atribute  '.$valueObject->getAttribute()->getname().' ('.$valueObject->getAttribute()->getId().')');
                            continue;
                        }
                        $newObject = new ObjectEntity($valueObject->getAttribute()->getObject());
                        $value = $this->saveOnFixedId($newObject, $value);
                        $valueObject->setValue($value);
                    }
                    // Is not an array
                    else {
                        $idValue = $value;
                        $value = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $idValue]);
                        // Savety
                        if (!$value) {
                            $this->symfonyStyle->error('Could not find an object for id '.$idValue);
                        } else {
                            $valueObject->setValue($value);
                        }
                    }
                } else {
                    $valueObject->setValue($value);
                }

                // Do the normaul stuf
                $objectEntity->addObjectValue($valueObject);
            }
        }

        // Lets force the default values
        $objectEntity->hydrate([]);

        $this->entityManager->persist($objectEntity);
        $this->entityManager->flush();

        return $objectEntity;
    }
}
