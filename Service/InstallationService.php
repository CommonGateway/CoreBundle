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

    /**
     * @var Filesystem
     */
    private Filesystem $filesystem;

    /**
     * @var array The Objects aquired durring a installation
     */
    private array $objects;

    /**
     * @param ComposerService $composerService
     * @param EntityManagerInterface $em
     * @param Kernel $kernel
     * @param CacheService $cacheService
     */
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

        $this->cacheService->warmup();

        return Command::SUCCESS;
    }//end composerupdate()

    /**
     * Validates the  objects in the EAV setup.
     *
     * @return void
     */
    public function validateObjects(): int
    {
        $objects = $this->em->getRepository('App:ObjectEntity')->findAll();

        $this->logger->info('Validating:'.count($objects).'objects\'s');

        // Lets go go go !
        foreach ($objects as $object) {
            if ($object->get == true) {
                // ToDo: Build
            }
        }
    }//end validateObjects()

    /**
     * Validates the  objects in the EAV setup.
     *
     * @return void
     */
    public function validateValues(): int
    {
        $values = $this->em->getRepository('App:Value')->findAll();

        $this->logger->info('Validating:'.count($values).'values\'s');

        // Lets go go go !
        foreach ($values as $value) {
            if ($value->getObjectEntity() === null) {
                $message = 'Value '.$value->getStringValue().' ('.$value->getId().') that belongs to  '.$value->getAttribute()->getName().' ('.$value->getAttribute()->getId().') is orpahned';
            }
        }
    }//end validateValues()

    /**
     * Validates the schemas in the EAV setup.
     *
     * @return void
     */
    public function validateSchemas(): int
    {
        $schemas = $this->em->getRepository('App:Entity')->findAll();

        $this->logger->info('Validating:'.count($schemas).'schema\'s');

        // Lets go go go !
        foreach ($schemas as $schema) {
            $statusOk = true;
            // Gereric check
            if ($schema->getReference() === null) {
                $this->logger->info('Schema '.$schema->getName().' ('.$schema->getId().') dosn\t have a reference');
            }

            // Gereric check.

            /*
            if(!$schema->getApplication()){
                $this->logger->info( 'Schema '.$schema->getName().' ('.$schema->getId().') dosn\t have a application');
            }
            // Gereric check
            if(!$schema->getOrganization()){
                $this->logger->info( 'Schema '.$schema->getName().' ('.$schema->getId().') dosn\t have a organization');
            }
            */

            // Check atributes.
            foreach ($schema->getAttributes() as $attribute) {
                // Specific checks for objects
                if ($attribute->getType() == 'object') {

                    // Check for object link.
                    if ($attribute->getObject() === false) {
                        $message = 'Schema '.$schema->getName().' ('.$schema->getId().') has attribute '.$attribute->getName().' ('.$attribute->getId().') that is of type Object but is not linked to an object';
                        $this->logger->error($message);
                         $statusOk = false;
                    } else {
                        $message = 'Schema '.$schema->getName().' ('.$schema->getId().') has attribute '.$attribute->getName().' ('.$attribute->getId().') that is linked to object '.$attribute->getObject()->getName().' ('.$attribute->getObject()->getId();
                        $this->logger->debug($message);
                    }

                    // Check for reference link.
                    if ($attribute->getReference() === false) {

                        //$message = 'Schema '.$schema->getName().' ('.$schema->getId().') has attribute '.$attribute->getName().' ('.$attribute->getId().') that is of type Object but is not linked to an reference';
                        //$this->logger->info($message);
                        //if ($this->io) { $this->io->info($message);}
                    }

                }//end if

                // Check for reference link.
                if ($attribute->getReference() && $attribute->getType() !== 'object') {
                    $message = 'Schema '.$schema->getName().' ('.$schema->getId().') has attribute '.$attribute->getName().' ('.$attribute->getId().') that has a reference ('.$attribute->getReference().') but isn\'t of the type object';
                    $this->logger->error($message);
                    $statusOk = false;
                }

            }

            if ($statusOk === true) {
                $this->logger->info('Schema '.$schema->getName().' ('.$schema->getId().') has been checked and is fine');
            } else {
                $this->logger->error('Schema '.$schema->getName().' ('.$schema->getId().') has been checked and has an error');
            }

        }//end foreach

        return 1;
    }//validateSchemas ()

    /**
     * Installs the files from a bundle
     *
     * @param string $bundle The bundle
     * @param array $config Optional config
     * @return bool The result of the installation
     */
    public function install(string $bundle, array $config = []): bool
    {

        $this->logger->debug('Installing plugin '.$bundle,['bundle'=>$bundle]);

        $vendorFolder = 'vendor';

        // Lets check the basic folders for lagacy pruposes.
        $this->logger->debug('Installing plugin '.$bundle);
        $this->readDirectory($vendorFolder.'/'.$bundle.'/Action');
        $this->readDirectory($vendorFolder.'/'.$bundle.'/Schema');
        $this->readDirectory($vendorFolder.'/'.$bundle.'/Mapping');
        $this->readDirectory($vendorFolder.'/'.$bundle.'/Data');
        $this->readDirectory($vendorFolder.'/'.$bundle.'/Installation');

        // Handling al the files.
        $this->logger->debug('Found '.count($this->objects).' schema types for '.$bundle,['bundle' => $bundle]);

        foreach ($this->objects as $ref => $schemas){
            $this->logger->debug('Found '.count($schemas).' objects types for schema '.$ref,['bundle' => $bundle,"reference" => $ref]);
            foreach($schemas as $schema){
                $object = $this->handleObject($schema);
                // Save it to the database
                $this->em->persist($object);
            }
        }

        // Save the results to the database.
        $this->em->flush();

        $this->logger->debug('All Done installing plugin '.$bundle,['bundle' => $bundle]);

        return true;
    }//end install()

    /**
     * This function read a folder to find other folders or json objects
     *
     * @param string $location The location of the folder
     * @return bool Whether or not the function was succefully executed
     */
    public function readDirectory(string $location): bool{

        // Lets see if the folder exisits to start with,
        if ($this->filesystem->exists($location) === false) {
            $this->logger->debug('Installation folder not found',["location" => $location]);
            return false;
        }

        // Get the folder content.
        $hits = new Finder();
        $hits = $hits->in($location);

        // Handle files
        $this->logger->debug('Found '.count($hits->files()). 'files for installer',["location"=>$location,"files" => count($hits->files())]);

        if(count($hits->files()) > 32){
            $this->logger->warning('Found more then 32 files in directory, try limiting your files to 32 per directory',["location"=>$location,"files" => count($hits->files())]);
        }

        foreach ($hits->files() as $file) {
            $this->readfile($file);
        }

        return true;
    }//end readDirectory()

    /**
     * This function read a folder to find other folders or json objects
     *
     * @param File $file The file location
     * @return bool|array The file contents, or false if content could not be establisched
     */
    public function readfile(File $file): mixed
    {

        // Check if it is a valid json object
        $mappingSchema = json_decode($file->getContents(), true);
        if ($mappingSchema === false) {
            $this->logger->error($file->getFilename().' is not a valid json object');
            return false;
        }

        // Check if it is a valid schema
        $mappingSchema = $this->validateJsonMapping($mappingSchema);

        if ($this->validateJsonMapping($mappingSchema)) {
            $this->logger->error($file->getFilename().' is not a valid json-mapping object');

            return false;
        }

        // Add the file to the object
        return $this->addToObjects($mappingSchema);
    }//end readfile()


    /**
     * Adds an object to the objects stack if it is vallid
     *
     * @param array $schema The schema
     * @return bool|array The file contents, or false if content could not be establisched
     */
    public function addToObjects(array $schema): mixed{

        // It is a schema so lets save it like that
        if(array_key_exists('$schema', $schema) === true){
            $this->objects[$schema['$schema']] = $schema;
            return $schema;
        }

        //If it is not a schema of itself it might be an array of objects
        foreach($schema as $key => $value){
            if(is_array($value)){
                $this->objects[$key] = $value;
                continue;
            }

            $this->logger->error("Expected to find array for schema type ".$key." but found ".gettype($value)." instead",["value"=>$value,"schema"=>$key]);
        }

        return true;
    }//end addToObjects()

    /**
     * Create an object bases on an type and a schema (the object as an array)
     *
     * This function breaks complexity rules, but since a switch is the most effective way of doing it a design decicion was made to allow it
     *
     * @param string $type The type of the object
     * @param array $schema The object as an array
     * @return bool|object
     */
    public function handleObject(string $type, array $schema):bool
    {
        // Only base we need it the assumption that on object isn't valid until we made is so
        $object = null;

        switch ($type){
            case 'https://json-schema.org/draft/2020-12/action':
                //Load it if we have it
                if(array_key_exists('$id', $schema) === true) {
                    $object = $this->em->getRepository('App:Action')->findOneBy(['reference' => $schema['$id']]);
                }

                //Create it if we don't
                if($object === null){
                    $object = New Action;
                }
                break;
            case 'https://json-schema.org/draft/2020-12/source':
                //Load it if we have it
                if(array_key_exists('$id', $schema) === true) {
                    $object = $this->em->getRepository('App:Source')->findOneBy(['reference' => $schema['$id']]);
                }

                //Create it if we don't
                if($object === null){
                    $object = New Source;
                }
                break;
            case 'https://json-schema.org/draft/2020-12/entity':
                //Load it if we have it
                if(array_key_exists('$id', $schema) === true) {
                    $object = $this->em->getRepository('App:Entity')->findOneBy(['reference' => $schema['$id']]);
                }

                //Create it if we don't
                if($object === null){
                    $object = New Entity;
                }
                break;
            case 'https://json-schema.org/draft/2020-12/mapping':
                //Load it if we have it
                if(array_key_exists('$id', $schema) === true ) {
                    $object = $this->em->getRepository('App:Mapping')->findOneBy(['reference' => $schema['$id']]);
                }

                //Create it if we don't
                if($object === null){
                    $object = New Mapping;
                }
                break;
            case 'https://json-schema.org/draft/2020-12/organization':
                //Load it if we have it
                if(array_key_exists('$id', $schema) === true) {
                    $object = $this->em->getRepository('App:Organization')->findOneBy(['reference' => $schema['$id']]);
                }

                //Create it if we don't
                if($object === null){
                    $object = New Organization;
                }
                break;
            case 'https://json-schema.org/draft/2020-12/application':
                //Load it if we have it
                if(array_key_exists('$id', $schema) === true) {
                    $object = $this->em->getRepository('App:Application')->findOneBy(['reference' => $schema['$id']]);
                }

                //Create it if we don't
                if($object === null){
                    $object = New Application;
                }
            case 'https://json-schema.org/draft/2020-12/cronjob':
                //Load it if we have it
                if(array_key_exists('$id', $schema) === true) {
                    $object = $this->em->getRepository('App:Cronjob')->findOneBy(['reference' => $schema['$id']]);
                }

                //Create it if we don't
                if($object === null){
                    $object = New Cronjob;
                }
            case 'https://json-schema.org/draft/2020-12/securityGroup':
                //Load it if we have it
                if(array_key_exists('$id', $schema) === true) {
                    $object = $this->em->getRepository('App:SecurityGroup')->findOneBy(['reference' => $schema['$id']]);
                }

                //Create it if we dont
                if($object === null){
                    $object = New SecurityGroup;
                }
            case 'https://json-schema.org/draft/2020-12/user':
                //Load it if we have it
                if(array_key_exists('$id', $schema) === true) {
                    $object = $this->em->getRepository('App:User')->findOneBy(['reference' => $schema['$id']]);
                }

                //Create it if we don't
                if($object === null){
                    $object = New User;
                }
            case 'https://json-schema.org/draft/2020-12/endpoint':
                //Load it if we have it
                if(array_key_exists('$id', $schema) === true) {
                    $object = $this->em->getRepository('App:Endpoint')->findOneBy(['reference' => $schema['$id']]);
                }

                //Create it if we don't
                if($object === null){
                    $object = New Endpoint;
                }

                // Add to collection
                if (isset($this->collection)) {
                    $object->addCollection($this->collection);
                }
            case 'https://json-schema.org/draft/2020-12/schema':
                //Load it if we have it
                if(array_key_exists('$id', $schema) === true){
                    $object = $this->em->getRepository('App:Entity')->findOneBy(['reference' => $schema['$id']]);
                }

                //Create it if we don't
                if($object === null){
                    $object = New Entity;
                }

                // Add to collection
                if (isset($this->collection)) {
                    $object->addCollection($this->collection);
                }
            default:
                // We have an undifned type so lets try to find it
                $entity = $this->em->getRepository('App:Entity')->findOneBy(['reference' => $type]);
                if($entity === null){
                    $this->logger->error('trying to create data for non-exisitng entity',['reference'=>$type,"object"=> $object->toSchema()]);
                    return false;
                }

                // If we have an id let try to grab an object
                if(array_key_exists('id', $schema)){
                    $object = $this->em->getRepository('App:ObjectEntity')->findOneBy(['id' => $schema['$id']]);
                }

                //Create it if we don't
                if($object === null){
                    $object = New ObjectEntity($entity);
                }

                // Now it gets a bit specif but for EAV data we allow nested fixed id's so let dive deep.
                if($this->em->contains($object) === false && (array_key_exists('id', $schema) || array_key_exists('_id', $schema))){
                    $object = $this->saveOnFixedId($object, $schema);
                    break;
                }

                // EAV objects arn't cast from schema but hydrated from array's
                $object->hydrate($schema);
                break;
        }//end switch

        // Load the data
        if (
            array_key_exists('version', $schema) === true &&
            version_compare($schema['version'], $object->getVersion()) <= 0
        ) {
            $this->loger->debug('The new mapping has a version number equal or lower then the already present version, the object is NOT is updated',['schemaVersion'=>$schema['version'],'objectVersion'=>$object->getVersion()]);

        }
        elseif (
            array_key_exists('version', $schema) === true &&
            version_compare($schema['version'], $object->getVersion()) < 0
        ){
            $this->loger->debug('The new mapping has a version number higher then the already present version, the object is data is updated',['schemaVersion'=>$schema['version'],'objectVersion'=>$object->getVersion()]);
            $object->fromSchema($schema);
        }
        elseif (array_key_exists('version', $schema) === false){
            $this->loger->debug('The new mapping don\'t have a version number, the object is data is updated',['schemaVersion'=>$schema['version'],'objectVersion'=>$object->getVersion()]);
            $object->fromSchema($schema);

        }

        // Lets see if it is a new object
        if($this->em->contains($object) === false){
            $this->loger->info('A new object has been created trough the installation service',
                [
                    "class"=> get_class($object),
                    "object"=> $object->toSchema(),
                ]
            );
        }

        return $object;
    }//end handleObject()

    public function handleInstaller($file)
    {
        $data = json_decode($file->getContents(), true)
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
    }// handleInstaller()

    /**
     * Handles forced id's on object entities.
     *
     * @param ObjectEntity $objectEntity The object entity on wich to force an id
     *
     * @return ObjectEntity The PERSISTED object entity on the forced id
     */
    private function saveOnFixedId(ObjectEntity $objectEntity, array $hydrate = []): ObjectEntity
    {
        // This savetey dosn't make sense but we need it
        if ($objectEntity->getEntity() === null) {
            $this->logger->error('Object can\'t be persisted due to missing schema');

            return $objectEntity;
        }

        // Save the values
        //$values = $objectEntity->getObjectValues()->toArray();
        //$objectEntity->clearAllValues();

        // We have an object entity with a fixed id that isn't in the database, so we need to act
        if (isset($hydrate['id']) && $this->em->contains($objectEntity) === false) {
            $this->logger->debug('Creating new object ('.$objectEntity->getEntity()->getName().') on a fixed id ('.$hydrate['id'].')');

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


            $this->logger->debug('Defintive object id ('.$objectEntity->getId().')');
        } else {
            $this->logger->debug('Creating new object ('.$objectEntity->getEntity()->getName().') on a generated id');
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

                        $this->logger->debug('an array for objects');
                        if (is_array($value)) {
                            foreach ($value as $subvalue) {
                                // Savety
                                if ($valueObject->getAttribute()->getObject() === null) {
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
                                    if ($subvalue === null) {
                                        $this->io->error('Could not find an object for id '.$idValue);
                                    } else {
                                        $valueObject->addObject($subvalue);
                                    }
                                }
                            }
                        } else {
                            $this->logger->error($valueObject->getAttribute()->getName().' Is a multiple so should be filled with an array, but provided value was '.$value.'(type: '.gettype($value).')');

                        }
                        continue;
                    }
                    // End of array hate, we are friends again

                    // is array
                    if (is_array($value)) {
                        // Savety
                        if (!$valueObject->getAttribute()->getObject()) {
                            $this->logger->error('Could not find an object for atribute  '.$valueObject->getAttribute()->getname().' ('.$valueObject->getAttribute()->getId().')');
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
                            $this->logger->error('Could not find an object for id '.$idValue);
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
    }//end saveOnFixedId()
}
