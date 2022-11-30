<?php

// src/Subscriber/DatabaseActivitySubscriber.php

namespace CommonGateway\CoreBundle\Subscriber;

use App\Entity\ObjectEntity;
use App\Entity\Entity;
use App\Entity\Endpoint;
use CommonGateway\CoreBundle\Service\CacheService;

use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;

class CacheDatabaseSubscriber implements EventSubscriberInterface
{
    private CacheService $cacheService;

    public function __construct(
        CacheService $cacheService
    ) {
        $this->cacheService = $cacheService;
    }

    // this method can only return the event names; you cannot define a
    // custom method name to execute when each event triggers
    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postRemove
        ];
    }

    /**
     * Updates the chache whenever an object is put into the database
     *
     * @param LifecycleEventArgs $args
     * @return void
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        // if this subscriber only applies to certain entity types,
        if (!$object instanceof ObjectEntity) {
            $this->cacheService->cacheObject($object);
            return;
        }
        if (!$object instanceof Entity) {
            $this->cacheService->cacheShema($object);
            return;
        }
        if (!$object instanceof Endpoint) {
            $this->cacheService->cacheEndpoint($object);
            return;
        }

        return;
    }

    /**
     * Remove objects from the cache afther they are removed from the database
     *
     * @param LifecycleEventArgs $args
     * @return void
     */
    public function postRemove(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        // if this subscriber only applies to certain entity types,
        if (!$object instanceof ObjectEntity) {
            $this->cacheService->removeObject($object);
            return;
        }
        if (!$object instanceof Entity) {
            $this->cacheService->removeShema($object);
            return;
        }
        if (!$object instanceof Endpoint) {
            $this->cacheService->removeEndpoint($object);
            return;
        }

        return;
    }
}
