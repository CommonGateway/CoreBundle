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
use Symfony\Component\DependencyInjection\ContainerInterface;

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
     *
     * @param ComposerService $composerService The Composer service
     * @param EntityManagerInterface $entityManager The entity manager
     * @param Kernel $kernel The kernel
     * @param CacheService $cacheService The cache service
     */
    public function __construct(
        ComposerService $composerService,
        EntityManagerInterface $entityManager,
        Kernel $kernel,
        CacheService $cacheService
    ) {
        $this->composerService = $composerService;
        $this->entityManager = $entityManager;
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
        $objects = $this->entityManager->getRepository('App:ObjectEntity')->findAll();

        $this->logger->info('Validating:'.count($objects).'objects\'s');

        // Lets go go go !
        foreach ($objects as $object) {
            if ($object->get === true) {
                // ToDo: Build.
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
        $values = $this->entityManager->getRepository('App:Value')->findAll();

        $this->logger->info('Validating:'.count($values).'values\'s');

        // Lets go go go !
        foreach ($values as $value) {
            if ($value->getObjectEntity() === null) {
                $this->logger->error('Value '.$value->getStringValue().' ('.$value->getId().') that belongs to  '.$value->getAttribute()->getName().' ('.$value->getAttribute()->getId().') is orpahned');
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
        $schemas = $this->entityManager->getRepository('App:Entity')->findAll();

        $this->logger->info('Validating:'.count($schemas).'schema\'s');

        // Lets go go go !
        foreach ($schemas as $schema) {
            $this->validateSchema($schema);
        }//end foreach

        return 1;
    }//end validateSchemas()


    /**
     * Validates a single schema
     *
     * @param Entity $entity
     *
     * @return bool
     */
    public function validateSchema(Entity $schema): bool
    {
        $status = true;

        // Does the schema have an reference?
        if ($schema->getReference() === null) {
            $this->logger->debug('Schema '.$schema->getName().' ('.$schema->getId().') dosn\t have a reference');
            $status = false;
        }

        // Does the schema have an application?
        if($schema->getApplication() === null) {
            $this->logger->debug( 'Schema '.$schema->getName().' ('.$schema->getId().') dosn\t have a application');
            $status = false;
        }

        // Does the schema have an organization?
        if($schema->getOrganization() === null) {
            $this->logger->debug( 'Schema '.$schema->getName().' ('.$schema->getId().') dosn\t have a organization');
            $status = false;
        }

        // Does the schema have an owner?
        if($schema->getOwner() === null) {
            $this->logger->debug( 'Schema '.$schema->getName().' ('.$schema->getId().') dosn\t have a owner');
            $status = false;
        }

        // Check atributes.
        foreach ($schema->getAttributes() as $attribute) {
            $valid = $this->validateAtribute($attribute);
            // If the atribute isn't valid then the schema isn't valid
            if($valid === false && $status === true ){
                $status = false;
            }
        }

        if ($status === true) {
            $this->logger->info('Schema '.$schema->getName().' ('.$schema->getId().') has been checked and is fine');
        } else {
            $this->logger->error('Schema '.$schema->getName().' ('.$schema->getId().') has been checked and has an error');
        }

        return $status;
    }//end validateSchema()

    /**
     * Validates a single atribute
     *
     * @param Attribute $attribute
     * @return bool
     */
    public function validateAtribute(Attribute  $attribute):bool
    {
        $status = true;

        // Specific checks for objects.
        if ($attribute->getType() === 'object') {
            // Check for object link.
            if ($attribute->getObject() === false) {
                $message = 'Attribute '.$attribute->getName().' ('.$attribute->getId().') that is of type Object but is not linked to an object';
                $this->logger->error($message);
                $status = false;
            } else {
                $message = 'Attribute '.$attribute->getName().' ('.$attribute->getId().') that is linked to object '.$attribute->getObject()->getName().' ('.$attribute->getObject()->getId();
                $this->logger->debug($message);
            }

            // Check for reference link.
            if ($attribute->getReference() === false) {
                $message = 'Attribute '.$attribute->getName().' ('.$attribute->getId().') that is of type Object but is not linked to an reference';
                $this->logger->debug($message);
            }

        }//end if

        // Check for reference link.
        if ($attribute->getReference() === true && $attribute->getType() !== 'object') {
            $message = 'Attribute '.$attribute->getName().' ('.$attribute->getId().') that has a reference ('.$attribute->getReference().') but isn\'t of the type object';
            $this->logger->error($message);
            $status = false;
        }

        return true;
    }//end validateAtribute()

    /**
     * Installs the files from a bundle
     *
     * @param string $bundle The bundle
     * @param array $config Optional config
     * @return bool The result of the installation
     */
    public function install(string $bundle, array $config = []): bool
    {

        $this->logger->debug('Installing plugin '.$bundle,['bundle' => $bundle]);

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

        foreach ($this->objects as $ref => $schemas) {
            $this->logger->debug('Found '.count($schemas).' objects types for schema '.$ref,['bundle' => $bundle,"reference" => $ref]);
            foreach($schemas as $schema){
                $object = $this->handleObject($schema);
                // Save it to the database
                $this->entityManager->persist($object);
            }
        }

        // Save the results to the database.
        $this->entityManager->flush();

        $this->logger->debug('All Done installing plugin '.$bundle,['bundle' => $bundle]);

        return true;
    }//end install()

    /**
     * This function read a folder to find other folders or json objects
     *
     * @param string $location The location of the folder
     * @return bool Whether or not the function was succefully executed
     */
    public function readDirectory(string $location): bool
    {

        // Lets see if the folder exisits to start with,
        if ($this->filesystem->exists($location) === false) {
            $this->logger->debug('Installation folder not found',["location" => $location]);
            return false;
        }

        // Get the folder content.
        $hits = new Finder();
        $hits = $hits->in($location);

        // Handle files.
        $this->logger->debug('Found '.count($hits->files()). 'files for installer',["location"=>$location,"files" => count($hits->files())]);

        if(count($hits->files()) > 32) {
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
     * Adds an object to the objects stack if it is vallid
     *
     * @param array $schema The schema
     * @return bool|array The file contents, or false if content could not be establisched
     */
    public function addToObjects(array $schema): mixed
    {

        // It is a schema so lets save it like that.
        if(array_key_exists('$schema', $schema) === true){
            $this->objects[$schema['$schema']] = $schema;
            return $schema;
        }

        // If it is not a schema of itself it might be an array of objects.
        foreach($schema as $key => $value) {
            if(is_array($value)) {
                $this->objects[$key] = $value;
                continue;
            }

            // The use of gettype is discoureged, but we don't use it as a bl here and only for logging text purposes. So a design decicion was made te allow it.
            $this->logger->error("Expected to find array for schema type ".$key." but found ".gettype($value)." instead",["value" => $value,"schema" => $key]);
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
        // Only base we need it the assumption that on object isn't valid until we made is so.
        $object = null;

        switch ($type) {
            case 'https://json-schema.org/draft/2020-12/action':
                // Load it if we have it.
                if(array_key_exists('$id', $schema) === true) {
                    $object = $this->entityManager->getRepository('App:Action')->findOneBy(['reference' => $schema['$id']]);
                }

                // Create it if we don't.
                if($object === null) {
                    $object = new Action;
                }
                break;
            case 'https://json-schema.org/draft/2020-12/source':
                // Load it if we have it.
                if(array_key_exists('$id', $schema) === true) {
                    $object = $this->entityManager->getRepository('App:Source')->findOneBy(['reference' => $schema['$id']]);
                }

                // Create it if we don't.
                if($object === null) {
                    $object = new Source;
                }
                break;
            case 'https://json-schema.org/draft/2020-12/entity':
                // Load it if we have it.
                if(array_key_exists('$id', $schema) === true) {
                    $object = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $schema['$id']]);
                }

                // Create it if we don't.
                if($object === null) {
                    $object = new Entity;
                }
                break;
            case 'https://json-schema.org/draft/2020-12/mapping':
                // Load it if we have it.
                if(array_key_exists('$id', $schema) === true ) {
                    $object = $this->entityManager->getRepository('App:Mapping')->findOneBy(['reference' => $schema['$id']]);
                }

                // Create it if we don't.
                if($object === null) {
                    $object = new Mapping;
                }
                break;
            case 'https://json-schema.org/draft/2020-12/organization':
                // Load it if we have it.
                if(array_key_exists('$id', $schema) === true) {
                    $object = $this->entityManager->getRepository('App:Organization')->findOneBy(['reference' => $schema['$id']]);
                }

                // Create it if we don't.
                if($object === null) {
                    $object = new Organization;
                }
                break;
            case 'https://json-schema.org/draft/2020-12/application':
                // Load it if we have it.
                if(array_key_exists('$id', $schema) === true) {
                    $object = $this->entityManager->getRepository('App:Application')->findOneBy(['reference' => $schema['$id']]);
                }

                // Create it if we don't.
                if($object === null) {
                    $object = new Application;
                }
            case 'https://json-schema.org/draft/2020-12/cronjob':
                // Load it if we have it.
                if(array_key_exists('$id', $schema) === true) {
                    $object = $this->entityManager->getRepository('App:Cronjob')->findOneBy(['reference' => $schema['$id']]);
                }

                // Create it if we don't.
                if($object === null) {
                    $object = new Cronjob;
                }
            case 'https://json-schema.org/draft/2020-12/securityGroup':
                // Load it if we have it.
                if(array_key_exists('$id', $schema) === true) {
                    $object = $this->entityManager->getRepository('App:SecurityGroup')->findOneBy(['reference' => $schema['$id']]);
                }

                // Create it if we dont.
                if($object === null) {
                    $object = new SecurityGroup;
                }
            case 'https://json-schema.org/draft/2020-12/user':
                // Load it if we have it.
                if(array_key_exists('$id', $schema) === true) {
                    $object = $this->entityManager->getRepository('App:User')->findOneBy(['reference' => $schema['$id']]);
                }

                // Create it if we don't.
                if($object === null) {
                    $object = new User;
                }
            case 'https://json-schema.org/draft/2020-12/endpoint':
                // Load it if we have it.
                if(array_key_exists('$id', $schema) === true) {
                    $object = $this->entityManager->getRepository('App:Endpoint')->findOneBy(['reference' => $schema['$id']]);
                }

                // Create it if we don't.
                if($object === null) {
                    $object = new Endpoint;
                }

                // Add to collection.
                if (isset($this->collection)) {
                    $object->addCollection($this->collection);
                }
            case 'https://json-schema.org/draft/2020-12/schema':
                // Load it if we have it.
                if(array_key_exists('$id', $schema) === true){
                    $object = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $schema['$id']]);
                }

                // Create it if we don't.
                if($object === null) {
                    $object = new Entity;
                }

                // Add to collection.
                if (isset($this->collection)) {
                    $object->addCollection($this->collection);
                }
            default:
                // We have an undifned type so lets try to find it.
                $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $type]);
                if($entity === null) {
                    $this->logger->error('trying to create data for non-exisitng entity',['reference'=>$type,"object" => $object->toSchema()]);
                    return false;
                }

                // If we have an id let try to grab an object.
                if(array_key_exists('id', $schema) === true) {
                    $object = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $schema['$id']]);
                }

                // Create it if we don't.
                if($object === null) {
                    $object = new ObjectEntity($entity);
                }

                // Now it gets a bit specif but for EAV data we allow nested fixed id's so let dive deep.
                if($this->entityManager->contains($object) === false && (array_key_exists('id', $schema) === true || array_key_exists('_id', $schema) === true )) {
                    $object = $this->saveOnFixedId($object, $schema);
                    break;
                }

                // EAV objects arn't cast from schema but hydrated from array's.
                $object->hydrate($schema);
                break;
        }//end switch

        // Load the data.
        if(
            array_key_exists('version', $schema) === true &&
            version_compare($schema['version'], $object->getVersion()) <= 0
        ) {
            $this->loger->debug('The new mapping has a version number equal or lower then the already present version, the object is NOT is updated',['schemaVersion' => $schema['version'],'objectVersion' => $object->getVersion()]);
        }
        else if(
            array_key_exists('version', $schema) === true &&
            version_compare($schema['version'], $object->getVersion()) < 0
        ) {
            $this->loger->debug('The new mapping has a version number higher then the already present version, the object is data is updated',['schemaVersion' => $schema['version'],'objectVersion' => $object->getVersion()]);
            $object->fromSchema($schema);
        }
        else if(array_key_exists('version', $schema) === false) {
            $this->loger->debug('The new mapping don\'t have a version number, the object is data is updated',['schemaVersion' => $schema['version'],'objectVersion' => $object->getVersion()]);
            $object->fromSchema($schema);

        }

        // Lets see if it is a new object.
        if($this->entityManager->contains($object) === false) {
            $this->loger->info('A new object has been created trough the installation service',
                [
                    "class" => get_class($object),
                    "object" => $object->toSchema(),
                ]
            );
        }

        return $object;
    }//end handleObject()

    /**
     * Specifcially handles the installation file
     *
     * @param $file The installation file
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
    }// handleInstaller()

    /**
     * Handles forced id's on object entities.
     *
     * @param ObjectEntity $objectEntity The object entity on wich to force an id
     * @param array $hydrate The data to hydrate
     *
     * @return ObjectEntity The PERSISTED object entity on the forced id
     */
    private function saveOnFixedId(ObjectEntity $objectEntity, array $hydrate = []): ObjectEntity
    {
        // This savetey dosn't make sense but we need it/
        if ($objectEntity->getEntity() === null) {
            $this->logger->error('Object can\'t be persisted due to missing schema');

            return $objectEntity;
        }

        // Save the values.
        //$values = $objectEntity->getObjectValues()->toArray();
        //$objectEntity->clearAllValues();

        // We have an object entity with a fixed id that isn't in the database, so we need to act.
        if (isset($hydrate['id']) === true && $this->entityManager->contains($objectEntity) === false) {
            $this->logger->debug('Creating new object ('.$objectEntity->getEntity()->getName().') on a fixed id ('.$hydrate['id'].')');

            // Save the id.
            $id = $hydrate['id'];
            // Create the entity.
            $this->entityManager->persist($objectEntity);
            $this->entityManager->flush();
            $this->entityManager->refresh($objectEntity);
            // Reset the id.
            $objectEntity->setId($id);
            $this->entityManager->persist($objectEntity);
            $this->entityManager->flush();
            $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $id]);

            $this->logger->debug('Defintive object id ('.$objectEntity->getId().')');
        } else {
            $this->logger->debug('Creating new object ('.$objectEntity->getEntity()->getName().') on a generated id');
        }

        // We already dit this so lets skip it.
        unset($hydrate['_id']);

        foreach ($hydrate as $key => $value) {
            // Try to get a value object.
            $valueObject = $objectEntity->getValueObject($key);

            // If we find the Value object we set the value.
            if ($valueObject instanceof Value) {
                // Value is an array so lets create an object.
                if ($valueObject->getAttribute()->getType() == 'object') {
                    // I hate arrays
                    if ($valueObject->getAttribute()->getMultiple()) {
                        $this->logger->debug('an array for objects');
                        if (is_array($value) === true) {
                            foreach ($value as $subvalue) {
                                // Savety
                                if ($valueObject->getAttribute()->getObject() === null) {
                                    continue;
                                }
                                // Is array.

                                if (is_array($subvalue) === true) {
                                    $newObject = new ObjectEntity($valueObject->getAttribute()->getObject());
                                    $newObject = $this->saveOnFixedId($newObject, $subvalue);
                                    $valueObject->addObject($newObject);
                                }

                                // Is not an array.
                                else {
                                    $idValue = $subvalue;
                                    $subvalue = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $idValue]);
                                    // Savety
                                    if ($subvalue === null) {
                                        $this->io->error('Could not find an object for id '.$idValue);
                                    } else {
                                        $valueObject->addObject($subvalue);
                                    }
                                }
                            }
                        } else {
                            // The use of gettype is discoureged, but we don't use it as a bl here and only for logging text purposes. So a design decicion was made te allow it.
                            $this->logger->error($valueObject->getAttribute()->getName().' Is a multiple so should be filled with an array, but provided value was '.$value.'(type: '.gettype($value).')');
                        }
                        continue;
                    }
                    // End of array hate, we are friends again.

                    // is array.
                    if (is_array($value) === true) {
                        // Savety
                        if ($valueObject->getAttribute()->getObject() === null) {
                            $this->logger->error('Could not find an object for atribute  '.$valueObject->getAttribute()->getname().' ('.$valueObject->getAttribute()->getId().')');
                            continue;
                        }

                        $newObject = new ObjectEntity($valueObject->getAttribute()->getObject());
                        $value = $this->saveOnFixedId($newObject, $value);
                        $valueObject->setValue($value);
                    }

                    // Is not an array.
                    else {
                        $idValue = $value;
                        $value = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $idValue]);
                        // Savety
                        if($value === null) {
                            $this->logger->error('Could not find an object for id '.$idValue);
                        } else {
                            $valueObject->setValue($value);
                        }
                    }
                } else {
                    $valueObject->setValue($value);
                }

                // Do the normaul stuf.
                $objectEntity->addObjectValue($valueObject);
            }
        }

        // Lets force the default values.
        $objectEntity->hydrate([]);

        $this->entityManager->persist($objectEntity);
        $this->entityManager->flush();

        return $objectEntity;
    }//end saveOnFixedId()
}
