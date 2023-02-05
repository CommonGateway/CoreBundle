<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Attribute;
use App\Entity\Entity;
use Doctrine\ORM\EntityManagerInterface;

class EavService
{
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var CacheService
     */
    private CacheService $cacheService;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CacheService $cacheService
    ) {
        $this->entityManager = $entityManager;
        $this->cacheService = $cacheService;
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
}
