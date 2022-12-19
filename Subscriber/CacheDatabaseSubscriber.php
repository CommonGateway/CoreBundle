<?php

// src/Subscriber/DatabaseActivitySubscriber.php

namespace CommonGateway\CoreBundle\Subscriber;

use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use CommonGateway\CoreBundle\Service\CacheService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class CacheDatabaseSubscriber implements EventSubscriberInterface
{
    private CacheService $cacheService;
    private EntityManagerInterface $entityManager;

    public function __construct(
        CacheService $cacheService,
        EntityManagerInterface $entityManager
    ) {
        $this->cacheService = $cacheService;
        $this->entityManager = $entityManager;
    }

    // this method can only return the event names; you cannot define a
    // custom method name to execute when each event triggers
    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::preRemove,
            Events::postUpdate,
        ];
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->postPersist($args);
    }

    /**
     * Updates the chache whenever an object is put into the database.
     *
     * @param LifecycleEventArgs $args
     *
     * @return void
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        // if this subscriber only applies to certain entity types,
        if ($object instanceof ObjectEntity) {
            $this->updateParents($object);
            $this->cacheService->cacheObject($object);

            return;
        }
        if ($object instanceof Entity) {
            $this->cacheService->cacheShema($object);

            return;
        }
        if ($object instanceof Endpoint) {
            $this->cacheService->cacheEndpoint($object);

            return;
        }
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $this->prePersist($args);
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        if ($object instanceof ObjectEntity) {
            $this->updateParents($object);
        }
    }

    /**
     * Remove objects from the cache afther they are removed from the database.
     *
     * @param LifecycleEventArgs $args
     *
     * @return void
     */
    public function preRemove(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        // if this subscriber only applies to certain entity types,
        if ($object instanceof ObjectEntity) {
            $this->cacheService->removeObject($object);

            return;
        }
        if ($object instanceof Entity) {
            $this->cacheService->removeSchema($object);

            return;
        }
        if ($object instanceof Endpoint) {
            $this->cacheService->removeEndpoint($object);

            return;
        }
    }

    public function updateParents(ObjectEntity $objectEntity, array $handled = [])
    {
        foreach ($objectEntity->getSubresourceOf() as $subresourceOf) {
            if (
                in_array($subresourceOf->getObjectEntity()->getId(), $handled) ||
                $subresourceOf->getObjectEntity()->getDateModified()->diff($objectEntity->getDateModified()) < new \DateInterval('30 seconds')
            ) {
                continue;
            }
            $subresourceOf->getObjectEntity()->setDateModified($objectEntity->getDateModified());
            $this->entityManager->persist($subresourceOf->getObjectEntity());
            $handled[] = $subresourceOf->getObjectEntity()->getId();
        }
        $this->entityManager->flush();
    }
}
