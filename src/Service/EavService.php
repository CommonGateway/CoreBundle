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
 */
class EavService
{

    private EntityManagerInterface $entityManager;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;

    }//end __construct()

    /**
     * Checks an entity to see if there are anny atributtes waiting for it.
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

        // Find the atribbutes.
        $attributes = $this->entityManager->getRepository('App:Attribute')->findBy(['reference' => $entity->getReference(), 'object' => null]);

        // Add them to the entity.
        foreach ($attributes as $attribute) {
            $attribute->setObject($entity);
        }

        return $entity;

    }//end checkEntityforAttribute()

    /**
     * Checks an atribute to see if a schema for its reference has becomme available.
     *
     * @param Attribute $attribute
     *
     * @return Attribute
     */
    public function checkAttributeforEntity(Attribute $attribute): Attribute
    {
        // Make sure we have a referende.
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
     * Removes all object entities from the database (should obviusly not be used in production).
     *
     * @param Entity|null $entity An optionall entity to remove all the objects from
     *
     * @return bool True is succesfull or false otherwise
     */
    public function deleteAllObjects(Entity $entity): bool
    {
        // Get al the objects for a specific entity.
        if (isset($entity) === true) {
            $objects = $this->entityManager->getRepository('App:ObjectEntity')->findBy(['entity' => $entity]);
        }

        // Or just get all the objects.
        if (isset($entity) === false) {
            // $objects = $this->entityManager->getRepository('App:ObjectEntity')->findAll();.
        }

        // Annnnnnd lets delete them.
        foreach ($objects as $object) {
            $this->entityManager->remove($object);
            $this->entityManager->flush();
        }

        return true;

    }//end deleteAllObjects()
}//end class
