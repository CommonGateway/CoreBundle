<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Attribute;
use App\Entity\Entity;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @Author Ruben van der Linde <ruben@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 *
 * This service belongs to the open registers framework.
 */
class EavService
{

    /**
     * @param EntityManagerInterface $entityManager
     */
    private EntityManagerInterface $entityManager;

    /**
     * The constructor sets al needed variables.
     *
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;

    }//end __construct()

    /**
     * Checks an entity to see if there are anny attributes waiting for it.
     *
     * @param Entity $entity
     *
     * @return Entity
     */
    public function checkEntityforAttribute(Entity $entity): Entity
    {
        // Make sure we have a reference.
        if ($entity->getReference() === null) {
            return $entity;
        }

        // Find the attributes.
        $attributes = $this->entityManager->getRepository('App:Attribute')->findBy(['reference' => $entity->getReference(), 'object' => null]);

        // Add them to the entity.
        foreach ($attributes as $attribute) {
            $attribute->setObject($entity);
        }

        return $entity;

    }//end checkEntityforAttribute()

    /**
     * Checks an attribute to see if a schema for its reference has become available.
     *
     * @param Attribute $attribute
     *
     * @return Attribute
     */
    public function checkAttributeforEntity(Attribute $attribute): Attribute
    {
        // Make sure we have a reference.
        if ($attribute->getReference() === null || $attribute->getObject() !== null) {
            return $attribute;
        }

        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $attribute->getReference()]);
        if ($entity !== null) {
            $attribute->setObject($entity);
        }

        return $attribute;

    }//end checkAttributeforEntity()

    /**
     * Removes all object entities from the database (should obviously not be used in production).
     *
     * @param Entity|null $entity An optional entity to remove all the objects from
     *
     * @return int The amount of objects deleted.
     */
    public function deleteAllObjects(?Entity $entity): int
    {
        $objects       = [];
        $deleteObjects = 0;

        // Get all the objects for a specific entity
        if ($entity !== null) {
            $objects = $this->entityManager->getRepository('App:ObjectEntity')->findBy(['entity' => $entity]);
        }

        // Or just get all the objects
        if ($entity === null) {
            // $objects = $this->entityManager->getRepository('App:ObjectEntity')->findAll();.
        }

        // And let's delete them
        // TODO: we should use a function from RequestService specific for deleting objects, in case we ever add custom BL for deletion.
        foreach ($objects as $object) {
            $this->entityManager->remove($object);
            $this->entityManager->flush();
            $deleteObjects++;
        }

        return $deleteObjects;

    }//end deleteAllObjects()
}//end class
