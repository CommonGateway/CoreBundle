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
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
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
    private EntityManagerInterface $em;

    /**
     * @var SymfonyStyle
     */
    private SymfonyStyle $io;

    /**
     * Holds the symfony container interface
     *
     * @var
     */
    private $container;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var CacheService
     */
    private CacheService $cacheService;

    public function __construct(
        ComposerService $composerService,
        EntityManagerInterface $em,
        Kernel $kernel,
        CacheService $cacheService
    ) {
        $this->composerService = $composerService;
        $this->em = $em;
        $this->container = $kernel->getContainer();
        $this->collection = null;
        $this->logger = new Logger('installation');
        $this->cacheService = $cacheService;
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
     * Updates all commonground bundles on the common gateway installation.
     *
     * @param array $config
     *
     * @return int
     */
    public function composerupdate(array $config = []): int
    {
        $plugins = $this->composerService->getAll();

        if ($this->io) {
            $this->io->writeln([
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

        if ($this->io) {
            $this->cacheService->setStyle($this->io);
            $this->cacheService->warmup();
        }

        return Command::SUCCESS;
    }

    /**
     * Validates the  objects in the EAV setup.
     *
     * @return void
     */
    public function validateObjects(): int
    {
        $objects = $this->em->getRepository('App:ObjectEntity')->findAll();

        if ($this->io) {
            $this->io->writeln([
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
     * @return void
     */
    public function validateValues(): int
    {
        $values = $this->em->getRepository('App:Value')->findAll();

        if ($this->io) {
            $this->io->writeln([
                'Validating: <comment> '.count($values).' </comment> values\'s',
            ]);
        }
        $this->logger->info('Validating:'.count($values).'values\'s');

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
     * @return void
     */
    public function validateSchemas(): int
    {
        $schemas = $this->em->getRepository('App:Entity')->findAll();

        if ($this->io) {
            $this->io->writeln([
                'Validating: <comment> '.count($schemas).' </comment> schema\'s',
            ]);
        }
        $this->logger->info('Validating:'.count($schemas).'schema\'s');

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
                        if ($this->io) {
                            $this->io->error($message);
                        }
                        $statusOk = false;
                    } else {
                        $message = 'Schema '.$schema->getName().' ('.$schema->getId().') has attribute '.$attribute->getName().' ('.$attribute->getId().') that is linked to object '.$attribute->getObject()->getName().' ('.$attribute->getObject()->getId();
                        $this->logger->debug($message);
                        if ($this->io) {
                            $this->io->note($message);
                        }
                    }
                    // Check for reference link
                    if (!$attribute->getReference()) {

                        //$message = 'Schema '.$schema->getName().' ('.$schema->getId().') has attribute '.$attribute->getName().' ('.$attribute->getId().') that is of type Object but is not linked to an reference';
                        //$this->logger->info($message);
                        //if ($this->io) { $this->io->info($message);}
                    }
                }

                // Specific wierdnes
                // Check for reference link
                if ($attribute->getReference() && !$attribute->getType() == 'object') {
                    $message = 'Schema '.$schema->getName().' ('.$schema->getId().') has attribute '.$attribute->getName().' ('.$attribute->getId().') that has a reference ('.$attribute->getReference().') but isn\'t of the type object';
                    $this->logger->error($message);
                    if ($this->io) {
                        $this->io->error($message);
                    }
                    $statusOk = false;
                }
            }

            if ($statusOk) {
                $message = 'Schema '.$schema->getName().' ('.$schema->getId().') has been checked and is fine';
                $this->logger->info($message);
                if ($this->io) {
                    $this->io->info($message);
                }
            } else {
                $message = 'Schema '.$schema->getName().' ('.$schema->getId().') has been checked and has an error';
                $this->logger->error($message);
                if ($this->io) {
                    $this->io->error($message);
                }
            }
        }

        return 1;
    }

    /**
     * Performs installation actions on a common Gataway bundle.
     *
     * @param SymfonyStyle $io
     * @param string       $bundle
     * @param bool         $noSchema
     *
     * @return int
     */
    public function install(string $bundle, array $config = []): int
    {
        if ($this->io) {
            $this->io->writeln([
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
            $this->io->writeln([
                '<info>Package '.$bundle.' found</info>',
                '',
                'Name: '.$package['name'],
                'Version: '.$package['version'],
                'Description: '.$package['description'],
                'Homepage :'.$package['homepage'],
                'Source: '.$package['source']['url'],
            ]);
        } else {
            $this->io->error($bundle.' not found');

            return Command::FAILURE;
        }

        $vendorFolder = 'vendor';
        $filesystem = new Filesystem();

        // Handling the actions's
        $this->io->section('Looking for actions\'s');
        $actionDir = $vendorFolder.'/'.$bundle.'/Action';
        if ($filesystem->exists($actionDir)) {
            $this->io->writeln('Action folder found');
            $actions = new Finder();
            $actions = $actions->in($actionDir);
            $this->io->writeln('Files found: '.count($actions));
            foreach ($actions->files() as $action) {
                $this->handleAction($action);
            }

            //$progressBar->finish();
        } else {
            $this->io->writeln('No action folder found');
        }

        // Handling the mappings
        $this->io->section('Looking for mappings\'s');
        $mappingDir = $vendorFolder.'/'.$bundle.'/Mapping';
        if ($filesystem->exists($mappingDir)) {
            $this->io->writeln('Mapping folder found');
            $mappings = new Finder();
            $mappings = $mappings->in($mappingDir);
            $this->io->writeln('Files found: '.count($mappings));

            foreach ($mappings->files() as $mapping) {
                $this->handleMapping($mapping);
            }

            //$progressBar->finish();
        } else {
            $this->io->writeln('No mapping folder found');
        }

        // Handling the schema's
        $this->io->section('Looking for schema\'s');
        $schemaDir = $vendorFolder.'/'.$bundle.'/Schema';

        if ($filesystem->exists($schemaDir)) {
            $this->io->writeln('Schema folder found');
            $schemas = new Finder();
            $schemas = $schemas->in($schemaDir);
            $this->io->writeln('Files found: '.count($schemas));

            // We want each plugin to also be a collection (if it contains schema's that is)
            if (count($schemas) > 0) {
                if (!$this->collection = $this->em->getRepository('App:CollectionEntity')->findOneBy(['plugin' => $package['name']])) {
                    $this->logger->debug('Created a collection for plugin '.$bundle);
                    $this->io->writeln(['Created a collection for this plugin', '']);
                    $this->collection = new CollectionEntity();
                    $this->collection->setName($package['name']);
                    $this->collection->setPlugin($package['name']);
                    isset($package['description']) && $this->collection->setDescription($package['description']);
                } else {
                    $this->io->writeln(['Found a collection for this plugin', '']);
                    $this->logger->debug('Found a collection for plugin '.$bundle);
                }
            }

            // Persist collection
            if (isset($this->collection)) {
                $this->em->persist($this->collection);
                $this->em->flush();
            }
            foreach ($schemas->files() as $schema) {
                $this->handleSchema($schema);
            }

            //$progressBar->finish();
        } else {
            $this->io->writeln('No schema folder found');
            $this->logger->debug('No schema folder found for plugin '.$bundle);
        }

        // Handling the data
        $this->io->section('Looking for data');
        if (array_key_exists('data', $config) && $config['data']) {
            $dataDir = $vendorFolder.'/'.$bundle.'/Data';

            if ($filesystem->exists($dataDir)) {
                $this->io->writeln('Data folder found');
                $datas = new Finder();
                $datas = $datas->in($dataDir);
                $this->io->writeln('Files found: '.count($datas));

                foreach ($datas->files() as $data) {
                    $this->handleData($data);
                }

                // We need to clear the finder
            } else {
                $this->logger->debug('No data folder found for plugin '.$bundle);
                $this->io->writeln('No data folder found');
            }
        } else {
            $this->io->warning('No test data loaded for bundle, run command with -data to load (test) data');
        }

        // Handling the installations
        $this->io->section('Looking for installers');
        $installationDir = $vendorFolder.'/'.$bundle.'/Installation';
        if ($filesystem->exists($installationDir)) {
            $this->io->writeln('Installation folder found');
            $installers = new Finder();
            $installers = $installers->in($installationDir);
            $this->io->writeln('Files found: '.count($installers));

            foreach ($installers->files() as $installer) {
                $this->handleInstaller($installer);
            }
        } else {
            $this->logger->debug('No Installation folder found for plugin '.$bundle);
            $this->io->writeln('No Installation folder found');
        }

        $this->io->success('All Done');
        $this->logger->debug('All Done installing plugin '.$bundle);

        return Command::SUCCESS;
    }

    public function update(string $bundle, string $data)
    {
        $this->io->writeln([
            'Common Gateway Bundle Updater',
            '============',
            '',
        ]);

        if (isset($bundle)) {
            $this->io->writeln([
                'Trying to update: <comment> '.$bundle.' </comment>',
                '',
            ]);
        }


        return Command::SUCCESS;
    }

    public function uninstall(string $bundle, string $data)
    {
        $this->io->writeln([
            'Common Gateway Bundle Uninstaller',
            '============',
            '',
        ]);

        if (isset($bundle)) {
            $this->io->writeln([
                'Trying to uninstall: <comment> '.$bundle.' </comment>',
                '',
            ]);
        }

        return Command::SUCCESS;
    }

    public function handleAction($file)
    {
        if (!$action = json_decode($file->getContents(), true)) {
            $this->io->writeln($file->getFilename().' is not a valid json object');

            return false;
        }

        if (!$this->validateJsonSchema($action)) {
            $this->io->writeln($file->getFilename().' is not a valid json-schema object');

            return false;
        }

        if (!$entity = $this->em->getRepository('App:Action')->findOneBy(['reference' => $action['$id']])) {
            $this->io->writeln('Action not present, creating action '.$action['title'].' under reference '.$action['$id']);
            $entity = new Action();
        } else {
            $this->io->writeln('Action already present, looking to update');
            if (array_key_exists('version', $action) && version_compare($action['version'], $entity->getVersion()) < 0) {
                $this->io->writeln('The new action has a version number equal or lower then the already present version');
            }
        }

        $entity->fromSchema($action);

        $this->em->persist($entity);

        $this->em->flush();
        $this->io->writeln('Done with action '.$entity->getName());
    }

    public function handleMapping($file)
    {
        if (!$mapping = json_decode($file->getContents(), true)) {
            $this->io->writeln($file->getFilename().' is not a valid json object');

            return false;
        }

        if (!$this->validateJsonMapping($mapping)) {
            $this->io->writeln($file->getFilename().' is not a valid json-mapping object');

            return false;
        }

        if (!$entity = $this->em->getRepository('App:Mapping')->findOneBy(['reference' => $mapping['$id']])) {
            $this->io->writeln('Maping not present, creating mapping '.$mapping['title'].' under reference '.$mapping['$id']);
            $entity = new Mapping();
        } else {
            $this->io->writeln('Mapping already present, looking to update');
            if (array_key_exists('version', $mapping) && version_compare($mapping['version'], $entity->getVersion()) < 0) {
                $this->io->writeln('The new mapping has a version number equal or lower then the already present version');
            }
        }

        $entity->fromSchema($mapping);

        $this->em->persist($entity);
        $this->em->flush();
        $this->io->writeln('Done with mapping '.$entity->getName());
    }

    public function handleSchema($file)
    {
        if (!$schema = json_decode($file->getContents(), true)) {
            $this->io->writeln($file->getFilename().' is not a valid json object');

            return false;
        }

        if (!$this->validateJsonSchema($schema)) {
            $this->io->writeln($file->getFilename().' is not a valid json-schema object');

            return false;
        }

        if (!$entity = $this->em->getRepository('App:Entity')->findOneBy(['reference' => $schema['$id']])) {
            $this->io->writeln('Schema not present, creating schema '.$schema['title'].' under reference '.$schema['$id']);
            $entity = new Entity();
        } else {
            $this->io->writeln('Schema already present, looking to update');
            if (array_key_exists('version', $schema) && version_compare($schema['version'], $entity->getVersion()) < 0) {
                $this->io->writeln('The new schema has a version number equal or lower then the already present version');
            }
        }

        $entity->fromSchema($schema);

        $this->em->persist($entity);

        // Add the schema to collection
        if (isset($this->collection)) {
            $entity->addCollection($this->collection);
        }

        $this->em->flush();
        $this->io->writeln('Done with schema '.$entity->getName());
    }

    /**
     * Perform a very basic check to see if a schema file is a valid json-schema file.
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

    public function handleData($file)
    {
        if (!$data = json_decode($file->getContents(), true)) {
            $this->io->writeln($file->getFilename().' is not a valid json object');

            return false;
        }

        foreach ($data as $reference => $objects) {
            // Lets see if we actuelly have a shema to upload the objects to
            if (!$entity = $this->em->getRepository('App:Entity')->findOneBy(['reference' => $reference])) {
                $this->io->writeln('No Schema found for reference '.$reference);
                continue;
            }

            $this->io->writeln([
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

                if (isset($object['id']) && $objectEntity = $this->em->getRepository('App:ObjectEntity')->findOneBy(['id' => $object['id']])) {
                    $this->io->writeln(['', 'Object '.$object['id'].' already exists, so updating']);
                } else {
                    $objectEntity = new ObjectEntity($entity);
                }

                $this->io->writeln('Writing data to the object');

                $this->saveOnFixedId($objectEntity, $object);

                $this->io->writeln(['Object saved as '.$objectEntity->getId(), '']);
            }
        }
    }

    public function handleInstaller($file)
    {
        if (!$data = json_decode($file->getContents(), true)) {
            $this->io->writeln($file->getFilename().' is not a valid json object');

            return false;
        }

        if (!isset($data['installationService']) || !$installationService = $data['installationService']) {
            $this->io->writeln($file->getFilename().' Doesn\'t contain an installation service');

            return false;
        }

        if (!$installationService = $this->container->get($installationService)) {
            $this->io->writeln($file->getFilename().' Could not be loaded from container');

            return false;
        }

        $installationService->setStyle($this->io);

        return $installationService->install();
    }

    /**
     * Handles forced id's on object entities.
     *
     * @param ObjectEntity $objectEntity
     *
     * @return ObjectEntity
     */
    private function saveOnFixedId(ObjectEntity $objectEntity, array $hydrate = []): ObjectEntity
    {
        // This savetey dosn't make sense but we need it
        if (!$objectEntity->getEntity()) {
            $this->logger->error('Object can\'t be persisted due to missing schema');
            $this->io->writeln(['', 'Object can\'t be persisted due to missing schema']);

            return $objectEntity;
        }

        // Save the values
        //$values = $objectEntity->getObjectValues()->toArray();
        //$objectEntity->clearAllValues();

        // We have an object entity with a fixed id that isn't in the database, so we need to act
        if (isset($hydrate['id']) && !$this->em->contains($objectEntity)) {
            $this->io->writeln(['Creating new object ('.$objectEntity->getEntity()->getName().') on a fixed id ('.$hydrate['id'].')']);

            // save the id
            $id = $hydrate['id'];
            // Create the entity
            $this->em->persist($objectEntity);
            $this->em->flush();
            $this->em->refresh($objectEntity);
            // Reset the id
            $objectEntity->setId($id);
            $this->em->persist($objectEntity);
            $this->em->flush();
            $objectEntity = $this->em->getRepository('App:ObjectEntity')->findOneBy(['id' => $id]);

            $this->io->writeln(['Defintive object id ('.$objectEntity->getId().')']);
        } else {
            $this->io->writeln(['Creating new object ('.$objectEntity->getEntity()->getName().') on a generated id']);
        }

        // We already dit this so lets skip it
        unset($hydrate['_id']);

        foreach ($hydrate as $key => $value) {
            // Try to get a value object
            $valueObject = $objectEntity->getValueObject($key);

            // If we find the Value object we set the value
            if ($valueObject instanceof Value) {
                // Value is an array so lets create an object
                if ($valueObject->getAttribute()->getType() == 'object') {

                    // I hate arrays
                    if ($valueObject->getAttribute()->getMultiple()) {
                        $this->io->info('an array for objects
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
                                    $subvalue = $this->em->getRepository('App:ObjectEntity')->findOneBy(['id' => $idValue]);
                                    // Savety
                                    if (!$subvalue) {
                                        $this->io->error('Could not find an object for id '.$idValue);
                                    } else {
                                        $valueObject->addObject($subvalue);
                                    }
                                }
                            }
                        } else {
                            $this->io->error($valueObject->getAttribute()->getName().' Is a multiple so should be filled with an array, but provided value was '.$value.'(type: '.gettype($value).')');
                        }
                        continue;
                    }
                    // End of array hate, we are friends again

                    // is array
                    if (is_array($value)) {
                        // Savety
                        if (!$valueObject->getAttribute()->getObject()) {
                            $this->io->error('Could not find an object for atribute  '.$valueObject->getAttribute()->getname().' ('.$valueObject->getAttribute()->getId().')');
                            continue;
                        }
                        $newObject = new ObjectEntity($valueObject->getAttribute()->getObject());
                        $value = $this->saveOnFixedId($newObject, $value);
                        $valueObject->setValue($value);
                    }
                    // Is not an array
                    else {
                        $idValue = $value;
                        $value = $this->em->getRepository('App:ObjectEntity')->findOneBy(['id' => $idValue]);
                        // Savety
                        if (!$value) {
                            $this->io->error('Could not find an object for id '.$idValue);
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

        $this->em->persist($objectEntity);
        $this->em->flush();

        return $objectEntity;
    }
}
