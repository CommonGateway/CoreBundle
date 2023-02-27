<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Attribute;
use App\Entity\Entity;
use Doctrine\ORM\EntityManagerInterface;

class EavService
{
    private EntityManagerInterface $entityManager;
    private CacheService $cacheService;
    private array $configuration;
    private array $data;
    private ObjectEntity $object;
    private string $id;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CacheService $cacheService
    ) {
        $this->entityManager = $entityManager;
        $this->cacheService = $cacheService;
    }

    /**
     * Checks an entity to see if there are anny atributtes waiting for it.
     *
     * @param Entity $entity
     *
     * @return Entity
     */
    public function checkEntityforAttribute(Entity $entity): Entity
    {
        // Make sure we have a reference
        if (!$entity->getReference()) {
            return $entity;
        }
        // Find the atribbutes
        $attributes = $this->entityManager->getRepository('App:Attribute')->findBy(['reference'=>$entity->getReference(), 'object'=>null]);

        // Add them to the entity
        foreach ($attributes as $attribute) {
            $attribute->setObject($entity);
        }

        return $entity;
    }

    /**
     * Checks an atribute to see if a schema for its reference has becomme available.
     *
     * @param Attribute $attribute
     *
     * @return Attribute
     */
    public function checkAttributeforEntity(Attribute $attribute): Attribute
    {
        // Make sure we have a referende
        if (!$attribute->getReference() || $attribute->getObject()) {
            return $attribute;
        }

        if ($entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference'=>$attribute->getReference()])) {
            $attribute->setObject($entity);
        }

        return $attribute;
    }

    /**
     * Removes all object entities from the database (should obviusly not be used in production)
     *
     * @param Entity|null $entity An optionall entity to remove all the objects from
     * @return bool True is succesfull or false otherwise
     */
    public function deleteAllObjects(Entity $entity):bool
    {
        // Get al the objects for a specifi entity
        if(isset($entity) === true){
            $objects = $this->entityManager->getRepository('App:ObjectEntity')->findBy(['entity'=>$entity]);
        }

        // Or just get all the objects
        if(isset($entity) === false){
            //$objects = $this->entityManager->getRepository('App:ObjectEntity')->findAll();
        }

        // Annnnnnd lets delete them
        foreach ($objects as $object){
            $this->entityManager->remove($object);
        }

        $this->entityManager->flush();

        return true;
    }
}
