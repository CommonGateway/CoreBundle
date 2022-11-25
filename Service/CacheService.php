<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;

/**
 * Handles the cashing of object entities
 */
class CacheService
{
    private EntityManagerInterface $entityManager;
    private CacheInterface $cache;

    /**
     * @param EntityManagerInterface $entityManager
     * @param SynchronizationService $synchronizationService
     * @param ObjectEntityService $objectEntityService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CacheInterface $cache
    ) {
        $this->entityManager = $entityManager;
        $this->cache = $cache;
    }


    /**
     * Throws all available objects into the cache
     */
    public function cacheWarmup()
    {
        $objectEntities = $this->entityManager->getRepository('App:ObjectEntity')->findAll();
        foreach($objectEntities as $objectEntity){
            $this->cacheObject($objectEntity);
        }
    }

    /**
     * Write an object to the cache
     *
     * @param ObjectEntity $objectEntity
     *
     * @return array The array representation of the object
     */
    public function cacheObject(ObjectEntity $objectEntity): array
    {
        $item = $this->cache->getItem('object_'.$objectEntity->getId());
        $item->set($objectEntity->toArray(1,['id','self','synchronizations']));
        $item->tag('object_'.$objectEntity->getId());
        $item->tag('entity_'.$objectEntity->getEntity()->getId());

        // Let make it searchable on synchronysations
        foreach($objectEntity->getSynchronizations() as $synchronization){
            $item->tag('synchronization_'.$synchronization->getId());
            $item->tag('source_'.$synchronization->getSource()->getId());
        }

        $this->cache->save($item);

        return $item->get();
    }

    /**
     * Get an object from the cache
     *
     * @param string $id The id of the object that you're trying to pull from the cache
     *
     * @return array The array representation of the object
     */
    public function getObject(string $id): array|false
    {
        // Grap the object
        $item = $this->cache->getItem('object_'.$id);
        if ($item->isHit()) {
            return $item->get();
        }

        // let create a backup for when we can not get the object
        if($objectEntity =$this->entityManager->getRepository('App:ObjectEntity')->find($id)){
            return $this->cacheObject($objectEntity);
        }

        // Ow nooz! We really cant find an object
        return false;
    }
}
