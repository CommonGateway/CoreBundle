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
Use Doctrine\ORM\EntityManagerInterface;
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
     * Performs installation actions on a common Gataway bundle
     *
     * @param SymfonyStyle $io
     * @param string $bundle
     * @param string|null $data
     * @param bool $noSchema
     * @return int
     */
    public function install(string $bundle, ?string $data, bool $noSchema = false):int
    {

        if($this->io) {
            $this->io->writeln([
                '',
                '<info>Common Gateway Bundle Installer</info>',
                '============',
                '',
                'Trying to install: <comment> ' . $bundle . ' </comment>',
                '',
            ]);
        }

        $packadges = $this->composerService->getAll()['installed'];

        $found = array_filter($packadges,function($v,$k) use ($bundle){
            return $v["name"] == $bundle;
        },ARRAY_FILTER_USE_BOTH); // With latest PHP third parameter is optional.. Available Values:- ARRAY_FILTER_USE_BOTH OR ARRAY_FILTER_USE_KEY

        $packadge = reset($found);
        if($packadge){
            $this->io->writeln([
                '<info>Packadge '. $bundle.' found</info>',
                '',
                'Name: '.$packadge['name'],
                'Version: '.$packadge['version'],
                'Description: '.$packadge['description'],
                'Direct-dependency: '.($packadge['direct-dependency'] ? 'true' : 'false'),
                'Homepage :'.$packadge['homepage'],
                'Source: '.$packadge['source'],
                'Abandoned: '. ($packadge['abandoned'] ? 'true' : 'false')
            ]);
        }
        else{
            $this->io->error($bundle.' not found');
            return Command::FAILURE;
        }

        $vendorFolder = 'vendor';
        $filesystem = new Filesystem();

        // Handling the schema's
        $this->io->section('Looking for schema\'s');
        $schemaDir = $vendorFolder.'/'.$bundle.'/Schema';

        if($filesystem->exists($schemaDir)){
            $this->io->writeln('Schema folder found');
            $schemas = New Finder();
            $schemas = $schemas->in($schemaDir);
            $this->io->writeln('Files found: '.count($schemas));


            //$progressBar =  $this->io->createProgressBar(count($schemas));
            //$progressBar->start();

            foreach ($schemas->files() as $schema){
                $this->handleSchema($schema);
            }

            //$progressBar->finish();
        }
        else{
            $this->io->writeln('No schema folder found');
        }

        // Handling the data
        $this->io->section('Looking for data');
        $dataDir = $vendorFolder.'/'.$bundle.'/Data';

        if($filesystem->exists($dataDir)){

            $this->io->writeln('Data folder found');
            $datas = New Finder();
            $datas =  $datas->in($dataDir);
            $this->io->writeln('Files found: '.count($datas));

            foreach ($datas->files() as $data){
                $this->handleData($data);

            }

            // We need to clear the finder
        }
        else{
            $this->io->writeln('No data folder found');
        }


        // Handling the installations
        $this->io->section('Looking for installers');
        $installationDir = $vendorFolder.'/'.$bundle.'/Installation';
        if($filesystem->exists($installationDir)){

            $this->io->writeln('Installation folder found');
            $installers = New Finder();
            $installers =  $installers->in($installationDir);
            $this->io->writeln('Files found: '.count($installers));

            foreach ($installers->files() as $installer){
                $this->handleInstaller($installer);
            }
        }
        else{
            $this->io->writeln('No Installation folder found');
        }

        $this->io->success('All Done');

        return Command::SUCCESS;
    }

    public function update(string $bundle, string $data){

        $this->io->writeln([
            'Common Gateway Bundle Updater',
            '============',
            '',
        ]);

        return Command::SUCCESS;

    }

    public function uninstall( string $bundle, string $data){

        $this->io->writeln([
            'Common Gateway Bundle Uninstaller',
            '============',
            '',
        ]);
        return Command::SUCCESS;
    }

    public function handleSchema( $file){

        if(!$schema = json_decode($file->getContents(), true)){
            $this->io->writeln($file->getFilename().' is not a valid json opbject');
            return false;
        }

        if(!$this->valdiateJsonSchema($schema)){
            $this->io->writeln($file->getFilename().' is not a valid json-schema opbject');
            return false;

        }

        if(!$entity = $this->em->getRepository('App:Entity')->findOneBy(['reference'=>$schema['$id']])){
            $this->io->writeln('Schema not pressent, creating schema '.$schema['title'] .' under reference '.$schema['$id']);
            $entity = New Entity();
        }
        else{
            $this->io->writeln('Schema already pressent, looking to update');
            if(array_key_exists('version', $schema) && version_compare($schema['version'], $entity->getVersion()) < 0){
                $this->io->writeln('The new schema has a version number equal or lower then the already pressent version');
            }
        }

        $entity->fromSchema($schema);

        $this->em->persist($entity);
        $this->em->flush();

        $this->io->writeln('Done with schema '.$entity->getName());

    }

    /**
     * Performce a very basic check to see if a schema file is a valid json-schema file
     *
     * @param array $schema
     * @return bool
     */
    public function valdiateJsonSchema(array $schema): bool
    {
        if(
            array_key_exists('$id',$schema) &&
            array_key_exists('$schema',$schema) &&
            $schema['$schema'] == "https://json-schema.org/draft/2020-12/schema" &&
            array_key_exists('type', $schema) &&
            $schema['type'] == "object" &&
            array_key_exists('properties', $schema)
        ){
            return true;
        }
        return false;
    }

    public function handleData( $file){

        if(!$data = json_decode($file->getContents(), true)){
            $this->io->writeln($file->getFilename().' is not a valid json opbject');
            return false;
        }

        foreach($data as $reference => $objects){

            // Lets see if we actuelly have a shema to upload the objects to
            if(!$entity = $this->em->getRepository('App:Entity')->findOneBy(['reference'=>$reference])){
                $this->io->writeln('No Schema found for reference '.$reference);
                continue;
            }

            $this->io->writeln([
                '',
                '<info> Found data for schema '.$reference.'</info> containing '.count($objects).' object(s)',
            ]);

            // Then we can handle data
            foreach($objects as $object){
                // Lets see if we need to update

                if(array_key_exists('_id',$object) && $objectEntity = $this->em->getRepository('App:ObjectEntity')->findOneBy(['id'=>$object['_id']])){
                    $this->io->writeln(['','Object '.$object['_id'].' already exsists, so updating']);
                }
                else{
                    $objectEntity = New ObjectEntity($entity);
                    $this->io->writeln(['','Creating new object']);

                    // We need to do something tricky if we want to overwrite the id (doctrine dosn't alow that)
                    if(array_key_exists('_id', $object)) {

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

    public function handleInstaller($file){

        if(!$data = json_decode($file->getContents(), true)){
            $this->io->writeln($file->getFilename().' is not a valid json opbject');
            return false;
        }

        if(!isset($data['installationService']) || !$installationService = $data['installationService']){
            $this->io->writeln($file->getFilename().' Dosnt contain an installation service');
            return false;
        }

        if(!$installationService =  $this->container->get($installationService)){
            $this->io->writeln($file->getFilename().' Could not be loaded from container');
            return false;
        }

        $installationService->setStyle($this->io);

        return $installationService->install();
    }



}
