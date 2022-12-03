<?php

namespace CommonGateway\CoreBundle\Service;


use App\Entity\Entity;
use App\Entity\ObjectEntity;
use CommonGateway\CoreBundle\Service\ComposerService;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Doctrine\ORM\EntityManagerInterface;
use App\Kernel;


class InstallationService
{
    private ComposerService $composerService;
    private EntityManagerInterface $em;
    private SymfonyStyle $io;
    private $container;

    public function __construct(
        ComposerService $composerService,
        EntityManagerInterface $em,
        Kernel $kernel
    ) {
        $this->composerService = $composerService;
        $this->em = $em;
        $this->container = $kernel->getContainer();
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
     *
     *
     */
    public function composerupdate():int
    {
        $plugins = $this->composerService->getAll();

        if ($this->io) {
            $this->io->writeln([
                '',
                '<info>Common Gateway Bundle Updater</info>',
                '============',
                '',
                'Found: <comment> ' . count($plugins) . ' </comment> to check for updates',
                '',
            ]);
        }

        foreach($plugins as $plugin){
            $this->install($plugin['name']);
        }

        return Command::SUCCESS;
    }

    /**
     * Performs installation actions on a common Gataway bundle
     *
     * @param SymfonyStyle $io
     * @param string $bundle
     * @param bool $noSchema
     * @return int
     */
    public function install(string $bundle, bool $noSchema = false):int
    {

        if ($this->io) {
            $this->io->writeln([
                'Trying to install: <comment> ' . $bundle . ' </comment>',
                '',
            ]);
        }

        $packadges = $this->composerService->getAll();

        $found = array_filter($packadges,function($v,$k) use ($bundle) {
            return $v["name"] == $bundle;
        },ARRAY_FILTER_USE_BOTH); // With latest PHP third parameter is optional.. Available Values:- ARRAY_FILTER_USE_BOTH OR ARRAY_FILTER_USE_KEY

        $packadge = reset($found);
        if ($packadge) {
            $this->io->writeln([
                '<info>Packadge '. $bundle.' found</info>',
                '',
                'Name: '.$packadge['name'],
                'Version: '.$packadge['version'],
                'Description: '.$packadge['description'],
                'Homepage :'.$packadge['homepage'],
                'Source: '.$packadge['source']['url']
            ]);
        } else {
            $this->io->error($bundle.' not found');
            return Command::FAILURE;
        }

        $vendorFolder = 'vendor';
        $filesystem = new Filesystem();

        // Handling the schema's
        $this->io->section('Looking for schema\'s');
        $schemaDir = $vendorFolder . '/' . $bundle . '/Schema';

        if ($filesystem->exists($schemaDir)) {
            $this->io->writeln('Schema folder found');
            $schemas = new Finder();
            $schemas = $schemas->in($schemaDir);
            $this->io->writeln('Files found: ' . count($schemas));


            //$progressBar =  $this->io->createProgressBar(count($schemas));
            //$progressBar->start();

            if (count($schemas->files() > 0)) {
                $this->collection = new CollectionEntity();
                $this->collection->setName($packadge['name']);
                isset($packadge['description']) && $this->collection->setDescription($packadge['description']);
            } else {
                $this->collection = null;
            }

            $schemaRefs = [];
            foreach ($schemas->files() as $schema) {
                $this->handleSchema($schema, $schemaRefs);
            }

            // Connect all $refs together
            $this->connectRefs($schemaRefs);

            // Persist collection
            if (isset($this->collection)) {
                $this->em->persist($this->collection);
                $this->em->flush();
            }


            //$progressBar->finish();
        } else {
            $this->io->writeln('No schema folder found');
        }

        // Handling the data
        $this->io->section('Looking for data');
        $dataDir = $vendorFolder . '/' . $bundle . '/Data';

        if ($filesystem->exists($dataDir)) {

            $this->io->writeln('Data folder found');
            $datas = new Finder();
            $datas =  $datas->in($dataDir);
            $this->io->writeln('Files found: ' . count($datas));

            foreach ($datas->files() as $data) {
                $this->handleData($data);
            }

            // We need to clear the finder
        } else {
            $this->io->writeln('No data folder found');
        }


        // Handling the installations
        $this->io->section('Looking for installers');
        $installationDir = $vendorFolder . '/' . $bundle . '/Installation';
        if ($filesystem->exists($installationDir)) {

            $this->io->writeln('Installation folder found');
            $installers = new Finder();
            $installers =  $installers->in($installationDir);
            $this->io->writeln('Files found: ' . count($installers));

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

    public function uninstall( string $bundle, string $data)
    {
        $this->io->writeln([
            'Common Gateway Bundle Uninstaller',
            '============',
            '',
        ]);
        return Command::SUCCESS;
    }

    private function connectRefs(array $schemaRefs)
    {
        if (!isset($this->collection) || empty($schemaRefs)) {
            return;
        }

        // Bind objects/properties that are needed from other collections
        foreach ($schemaRefs as $ref) {
            $entity = null;
            $attributes = null;

            // Bind Attribute to the correct Entity by schema
            if ($ref['type'] == 'attribute') {
                $entity = $this->em->getRepository('App:Entity')->findOneBy(['schema' => $ref['schema']]);
                $attribute = $this->em->getRepository('App:Attribute')->find($ref['id']);
                $entity && $attribute && $this->bindAttributeToEntity($attribute, $entity);
            } elseif ($ref['type'] == 'entity') {
                // Bind all Attributes that refer to this Entity by schema
                $attributes = $this->em->getRepository('App:Attribute')->findBy(['schema' => $ref['schema']]);
                $entity = $this->em->getRepository('App:Entity')->find($ref['id']);

                if ($entity && $attributes) {
                    foreach ($attributes as $attribute) {
                        $this->bindAttributeToEntity($attribute, $entity);
                    }
                }
            }
        }
        $this->entityManager->flush();
    }

    private function bindAttributeToEntity(Attribute $attribute, Entity $entity): void
    {
        if ($attribute->getType() !== 'object' && $attribute->getObject() == null) {
            $attribute->setFormat(null);
            $attribute->setType('object');
            $attribute->setObject($entity);
            $attribute->setCascade(true);

            $this->entityManager->persist($attribute);
        }
    }

    public function handleSchema($file, array &$schemaRefs = [])
    {

        if (!$schema = json_decode($file->getContents(), true)) {
            $this->io->writeln($file->getFilename() . ' is not a valid json opbject');
            return false;
        }

        if (!$this->valdiateJsonSchema($schema)) {
            $this->io->writeln($file->getFilename() . ' is not a valid json-schema opbject');
            return false;
        }

        if (!$entity = $this->em->getRepository('App:Entity')->findOneBy(['reference' => $schema['$id']])) {
            $this->io->writeln('Schema not pressent, creating schema ' . $schema['title'] . ' under reference ' . $schema['$id']);
            $entity = new Entity();
        } else {
            $this->io->writeln('Schema already pressent, looking to update');
            if (array_key_exists('version', $schema) && version_compare($schema['version'], $entity->getVersion()) < 0) {
                $this->io->writeln('The new schema has a version number equal or lower then the already pressent version');
            }
        }

        $entity->fromSchema($schema, $schemaRefs);

        $this->em->persist($entity);
        $this->em->flush();

        $entity->getSchema() !== null && $schemaRefs[] = [
            'id' => $entity->getId()->toString(),
            'schema' => $entity->getSchema(),
            'type' => 'entity'
        ];

        foreach ($entity->getAttributes() as $attribute) {
            if ($attribute->getSchema() !== null) {
                $schemaRefs[] = [
                    'id' => $attribute->getId()->toString(),
                    'schema' => $attribute->getSchema(),
                    'type' => 'attribute'
                ];
            }
        }

        $this->collection->addEntity($entity);

        $this->io->writeln('Done with schema ' . $entity->getName());
    }

    /**
     * Performce a very basic check to see if a schema file is a valid json-schema file
     *
     * @param array $schema
     * @return bool
     */
    public function valdiateJsonSchema(array $schema): bool
    {
        if (
            array_key_exists('$id',$schema) &&
            array_key_exists('$schema',$schema) &&
            $schema['$schema'] == "https://json-schema.org/draft/2020-12/schema" &&
            array_key_exists('type', $schema) &&
            $schema['type'] == "object" &&
            array_key_exists('properties', $schema)
        ) {
            return true;
        }
        return false;
    }

    public function handleData( $file)
    {

        if (!$data = json_decode($file->getContents(), true)) {
            $this->io->writeln($file->getFilename().' is not a valid json opbject');
            return false;
        }

        foreach ($data as $reference => $objects) {
            // Lets see if we actuelly have a shema to upload the objects to
            if (!$entity = $this->em->getRepository('App:Entity')->findOneBy(['reference'=>$reference])) {
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

                if (array_key_exists('_id',$object) && $objectEntity = $this->em->getRepository('App:ObjectEntity')->findOneBy(['id'=>$object['_id']])) {
                    $this->io->writeln(['','Object '.$object['_id'].' already exsists, so updating']);
                } else {
                    $objectEntity = New ObjectEntity($entity);
                    $this->io->writeln(['','Creating new object']);

                    // We need to do something tricky if we want to overwrite the id (doctrine dosn't alow that)
                    if (array_key_exists('_id', $object)) {

                        $this->io->writeln('Forcing id to '.$object['_id']);

                        // Force doctrine id creation
                        $this->em->persist($objectEntity);
                        $this->em->flush();

                        // Overwrite that creation
                        $objectEntity->setId($object['_id']);
                        $this->em->persist($objectEntity);
                        $this->em->flush();

                        // Reload the object
                        $this->em->clear('App:ObjectEntity');
                        $objectEntity = $this->em->getRepository('App:ObjectEntity')->findOneBy(['id'=>$object['_id']]);
                    }
                }

                $this->io->writeln('Writing data to the object');
                $objectEntity->hydrate($object);
                $this->em->persist($objectEntity);
                $this->em->flush();
                $this->io->writeln('Object saved as ' . $objectEntity->getId());
            }
        }
    }

    public function handleInstaller($file)
    {

        if (!$data = json_decode($file->getContents(), true)) {
            $this->io->writeln($file->getFilename().' is not a valid json opbject');
            return false;
        }

        if (!isset($data['installationService']) || !$installationService = $data['installationService']) {
            $this->io->writeln($file->getFilename().' Dosnt contain an installation service');
            return false;
        }

        if (!$installationService =  $this->container->get($installationService)) {
            $this->io->writeln($file->getFilename().' Could not be loaded from container');
            return false;
        }

        $installationService->setStyle($this->io);

        return $installationService->install();
    }
}
