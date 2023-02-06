<?php

namespace CommonGateway\CoreBundle\Subscriber;

use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use CommonGateway\CoreBundle\Service\CacheService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CacheDatabaseSubscriber implements EventSubscriberInterface
{
    /**
     * @var CacheService
     */
    private CacheService $cacheService;

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var SessionInterface
     */
    private SessionInterface $session;

    /**
     * @param CacheService           $cacheService  The CacheService
     * @param EntityManagerInterface $entityManager The EntityManagerInterface
     * @param SessionInterface       $session       The SessionInterface
     */
    public function __construct(
        CacheService $cacheService,
        EntityManagerInterface $entityManager,
        SessionInterface $session
    ) {
        $this->cacheService = $cacheService;
        $this->entityManager = $entityManager;
        $this->session = $session;
    }//end __construct()

    /**
     * @return array
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::preRemove,
            Events::postUpdate,
        ];
    }

    /**
     * @param LifecycleEventArgs $args
     *
     * @return void
     */
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
     * @param LifecycleEventArgs $args
     *
     * @return void
     */
    public function preUpdate(LifecycleEventArgs $args): void
    {
        $this->prePersist($args);
    }

    /**
     * @param LifecycleEventArgs $args
     *
     * @return void
     */
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
}
