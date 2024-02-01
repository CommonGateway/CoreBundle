<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Entity\Value;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * The schema service is used to validate schema's.
 *
 * @Author Ruben van der Linde <ruben@conduction.nl>, Robert Zondervan <robert@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class SchemaService
{

    /**
     * @var LoggerInterface The schema logger.
     */
    private LoggerInterface $logger;

    /**
     * @param EntityManagerInterface $entityManager The entity manager.
     * @param LoggerInterface        $schemaLogger  The schema logger.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        LoggerInterface $schemaLogger
    ) {
        $this->logger = $schemaLogger;

    }//end __construct()

    /**
     * Validates the  objects in the EAV setup.
     *
     * @return void
     */
    public function validateObjects(): int
    {
        $objects = $this->entityManager->getRepository('App:ObjectEntity')->findAll();

        $this->logger->info('Validating:'.count($objects).'objects\'s');

        // Let's go go go !
        foreach ($objects as $object) {
            $this->session->set('object', $object->getId()->toString());
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

        // Let's go go go !
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

        // Let's go go go !
        foreach ($schemas as $schema) {
            $this->session->set('schema', $this->schema->getId()->toString());
            $this->validateSchema($schema);
        }//end foreach

        return 1;

    }//end validateSchemas()

    /**
     * Validates a single schema.
     *
     * @param Entity $schema The schema to validate
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
        if ($schema->getApplication() === null) {
            $this->logger->debug('Schema '.$schema->getName().' ('.$schema->getId().') dosn\t have a application');
            $status = false;
        }

        // Does the schema have an organization?
        if ($schema->getOrganization() === null) {
            $this->logger->debug('Schema '.$schema->getName().' ('.$schema->getId().') dosn\t have a organization');
            $status = false;
        }

        // Does the schema have an owner?
        if ($schema->getOwner() === null) {
            $this->logger->debug('Schema '.$schema->getName().' ('.$schema->getId().') dosn\t have a owner');
            $status = false;
        }

        // Check attributes.
        foreach ($schema->getAttributes() as $attribute) {
            $valid = $this->validateAttribute($attribute);
            // If the attribute isn't valid then the schema isn't valid.
            if ($valid === false && $status === true) {
                $status = false;
            }
        }

        if ($status === true) {
            $this->logger->info('Schema '.$schema->getName().' ('.$schema->getId().') has been checked and is fine');
        } else if ($status === false) {
            $this->logger->error('Schema '.$schema->getName().' ('.$schema->getId().') has been checked and has an error');
        }

        return $status;

    }//end validateSchema()

    /**
     * Validates a single attribute.
     *
     * @param Attribute $attribute The attribute to validate
     *
     * @return bool
     */
    public function validateAttribute(Attribute $attribute): bool
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

        return $status;

    }//end validateAttribute()

    /**
     * Handles forced id's on object entities.
     *
     * @param ObjectEntity $objectEntity The object entity on wich to force an id
     * @param array        $hydrate      The data to hydrate
     *
     * @return ObjectEntity The PERSISTED object entity on the forced id
     */
    public function hydrate(ObjectEntity $objectEntity, array $hydrate = []): ObjectEntity
    {
        // This safety doesn't make sense but we need it.
        if ($objectEntity->getEntity() === null) {
            $this->logger->error('Object can\'t be persisted due to missing schema');

            return $objectEntity;
        }

        // We have an object entity with a fixed id that isn't in the database, so we need to act.
        if (isset($hydrate['_id']) === true && $this->entityManager->contains($objectEntity) === false) {
            $this->logger->debug('Creating new object ('.$objectEntity->getEntity()->getName().') on a fixed id ('.$hydrate['_id'].')');

            // Save the id.
            $id = $hydrate['_id'];

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

        // Already handled this, so skip it
        unset($hydrate['_id']);

        foreach ($hydrate as $key => $value) {
            // Try to get a value object.
            $valueObject = $objectEntity->getValueObject($key);

            // If we find the Value object we set the value.
            if ($valueObject instanceof Value) {
                // Value is an array so let's create an object.
                if ($valueObject->getAttribute()->getType() === 'object') {
                    // I hate arrays.
                    if ($valueObject->getAttribute()->getMultiple() === true) {
                        $this->logger->debug('an array for objects');
                        if (is_array($value) === true) {
                            // Todo: somehow this foreach creates 1 duplicate object when this $value array doesn't have _id's set in testdata.
                            foreach ($value as $subvalue) {
                                // Is array.
                                if (is_array($subvalue) === true) {
                                    // If we have an id let try to grab an object.
                                    if (array_key_exists('_id', $subvalue) === true) {
                                        $subObject = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $subvalue['_id']]);
                                    }

                                    // Create it if we don't.
                                    if (isset($subObject) === false || $subObject === null) {
                                        // Safety.
                                        if ($valueObject->getAttribute()->getObject() === null) {
                                            $this->logger->error('Could not find an object for attribute  '.$valueObject->getAttribute()->getname().' ('.$valueObject->getAttribute()->getId().')');
                                            continue;
                                        }

                                        $newObject = new ObjectEntity($valueObject->getAttribute()->getObject());
                                        $subObject = $this->hydrate($newObject, $subvalue);
                                    } else {
                                        $subObject = $this->hydrate($subObject, $subvalue);
                                    }
                                } else {
                                    // Is not an array.
                                    $idValue   = $subvalue;
                                    $subObject = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $idValue]);
                                    // Safety.
                                    if ($subObject === null) {
                                        $this->logger->error('Could not find an object for id '.$idValue.' (SchemaService->hydrate)');
                                    }
                                }//end if

                                if ($subObject instanceof ObjectEntity === true && $valueObject->getObjects()->contains($subObject) === false) {
                                    $valueObject->addObject($subObject);
                                }
                            }//end foreach
                        } else {
                            // The use of gettype is discoureged, but we don't use it as a bl here and only for logging text purposes. So a design decicion was made te allow it.
                            $this->logger->error($valueObject->getAttribute()->getName().' Is a multiple so should be filled with an array, but provided value was '.$value.'(type: '.gettype($value).')');
                        }//end if

                        continue;
                    }//end if

                    // Is array.
                    if (is_array($value) === true) {
                        // If we have an id let try to grab an object.
                        if (array_key_exists('_id', $value) === true) {
                            $singleSubObject = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $value['_id']]);
                        }

                        // Create it if we don't.
                        if (isset($singleSubObject) === false || $singleSubObject === null) {
                            // Safety.
                            if ($valueObject->getAttribute()->getObject() === null) {
                                $this->logger->error('Could not find an object for attribute  '.$valueObject->getAttribute()->getname().' ('.$valueObject->getAttribute()->getId().')');
                                continue;
                            }

                            $newObject       = new ObjectEntity($valueObject->getAttribute()->getObject());
                            $singleSubObject = $this->hydrate($newObject, $value);
                        } else {
                            $singleSubObject = $this->hydrate($singleSubObject, $value);
                        }
                    } else {
                        // Is not an array.
                        $idValue         = $value;
                        $singleSubObject = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $idValue]);
                        // Safety.
                        if ($singleSubObject === null) {
                            $this->logger->error('Could not find an object for id '.$idValue.' (SchemaService->hydrate)');
                        }
                    }//end if

                    if ($singleSubObject instanceof ObjectEntity === true && $valueObject->getObjects()->contains($singleSubObject) === false) {
                        $valueObject->setValue($singleSubObject);
                    }
                } else {
                    $valueObject->setValue($value);
                }//end if

                // Do the normal stuf.
                $objectEntity->addObjectValue($valueObject);
            }//end if
        }//end foreach

        // Let's force the default values.
        $objectEntity->hydrate([]);

        $this->entityManager->persist($objectEntity);
        $this->entityManager->flush();

        return $objectEntity;

    }//end hydrate()
}//end class
