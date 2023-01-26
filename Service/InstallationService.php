<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Action;
use App\Entity\Mapping;
use App\Entity\CollectionEntity;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class InstallationService
{
    private ComposerService $composerService;
    private EntityManagerInterface $em;
    private SymfonyStyle $io;
    private $container;
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

    public function composerupdate(): int
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

        foreach ($plugins as $plugin) {
            $this->install($plugin['name']);
        }

        $this->cacheService->warmup();

        return Command::SUCCESS;
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
    public function install(string $bundle, bool $noSchema = false): int
    {
        if ($this->io) {
            $this->io->writeln([
                'Trying to install: <comment> '.$bundle.' </comment>',
                '',
            ]);
        }

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
            $this->io->writeln('Files found: '.count($schemas));

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
                    $this->io->writeln(['Created a collection for this plugin', '']);
                    $this->collection = new CollectionEntity();
                    $this->collection->setName($package['name']);
                    $this->collection->setPlugin($package['name']);
                    isset($package['description']) && $this->collection->setDescription($package['description']);
                } else {
                    $this->io->writeln(['Found a collection for this plugin', '']);
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
        }

        // Handling the data
        $this->io->section('Looking for data');
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
            $this->io->writeln('No data folder found');
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
            $this->io->writeln('No Installation folder found');
        }

        $this->io->success('All Done');

        return Command::SUCCESS;
    }

    public function update(string $bundle, string $data)
    {
        $this->io->writeln([
            'Common Gateway Bundle Updater',
            '============',
            '',
        ]);

        return Command::SUCCESS;
    }

    public function uninstall(string $bundle, string $data)
    {
        $this->io->writeln([
            'Common Gateway Bundle Uninstaller',
            '============',
            '',
        ]);

        return Command::SUCCESS;
    }

    public function handleAction($file)
    {
        if (!$action = json_decode($file->getContents(), true)) {
            $this->io->writeln($file->getFilename().' is not a valid json object');

            return false;
        }

        if (!$this->valdiateJsonSchema($action)) {
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

        if (!$this->valdiateJsonSchema($mapping)) {
            $this->io->writeln($file->getFilename().' is not a valid json-schema object');

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

        if (!$this->valdiateJsonSchema($schema)) {
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
     * Performce a very basic check to see if a schema file is a valid json-schema file.
     *
     * @param array $schema
     *
     * @return bool
     */
    public function valdiateJsonSchema(array $schema): bool
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

                if (array_key_exists('_id', $object) && $objectEntity = $this->em->getRepository('App:ObjectEntity')->findOneBy(['id' => $object['_id']])) {
                    $this->io->writeln(['', 'Object '.$object['_id'].' already exists, so updating']);
                } elseif (array_key_exists('_id', $object)) {
                    $this->io->writeln('Set id to '.$object['_id']);

                    // Nice doctrine setId shizzle
                    $objectEntity = new ObjectEntity();
                    $this->em->persist($objectEntity);
                    $objectEntity->setId($object['_id']);
                    $this->em->persist($objectEntity);
                    $this->em->flush();
                    $this->em->refresh($objectEntity);

                    $objectEntity = $this->em->getRepository('App:ObjectEntity')->findOneBy(['id' => $object['_id']]);

                    $objectEntity->setEntity($entity);

                    $this->io->writeln('Creating new object with existing id '.$objectEntity->getId());
                } else {
                    $objectEntity = new ObjectEntity($entity);
                    $this->io->writeln(['', 'Creating new object']);
                }

                $this->io->writeln('Writing data to the object');
                $objectEntity->hydrate($object);
                $this->em->persist($objectEntity);
                $this->em->flush();
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
}
