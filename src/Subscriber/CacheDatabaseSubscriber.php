<?php

// src/Subscriber/DatabaseActivitySubscriber.php

namespace CommonGateway\CoreBundle\src\Subscriber;

use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use CommonGateway\CoreBundle\src\Service\CacheService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @Author Ruben van der Linde <ruben@conduction.nl>, Barry Brands <barry@conduction.nl>, Robert Zondervan <robert@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Subscriber
 */
class CacheDatabaseSubscriber implements EventSubscriberInterface
{
    private CacheService $cacheService;
    private EntityManagerInterface $entityManager;
    private SessionInterface $session;

    public function __construct(
        CacheService $cacheService,
        EntityManagerInterface $entityManager,
        SessionInterface $session
    ) {
        $this->cacheService = $cacheService;
        $this->entityManager = $entityManager;
        $this->session = $session;
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
//            $this->updateParents($object);
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
}
