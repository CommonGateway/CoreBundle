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

/**
 * @Author Robert Zondervan <robert@conduction.nl>, Ruben van der Linde <ruben@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Subscriber
 */
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
     * @param LifecycleEventArgs $args LifecycleEventArgs
     *
     * @return void
     */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->postPersist($args);
    }//end postUpdate()

    /**
     * Updates the chache whenever an object is put into the database.
     *
     * @param LifecycleEventArgs $args LifecycleEventArgs
     *
     * @return void
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        // if this subscriber only applies to certain entity types,
        if ($object instanceof ObjectEntity === true) {
            $this->cacheService->cacheObject($object);

            return;
        }
        if ($object instanceof Entity === true) {
            $this->cacheService->cacheShema($object);

            return;
        }
        if ($object instanceof Endpoint === true) {
            $this->cacheService->cacheEndpoint($object);

            return;
        }
    }//end  postPersist()

    /**
     * @param LifecycleEventArgs $args LifecycleEventArgs
     *
     * @return void
     */
    public function preUpdate(LifecycleEventArgs $args): void
    {
        $this->prePersist($args);
    }//end preUpdate()

    /**
     * @param LifecycleEventArgs $args LifecycleEventArgs
     *
     * @return void
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        if ($object instanceof ObjectEntity === true) {
            $this->updateParents($object);
        }
    }//end prePersist()

    /**
     * Remove objects from the cache afther they are removed from the database.
     *
     * @param LifecycleEventArgs $args LifecycleEventArgs
     *
     * @return void
     */
    public function preRemove(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        // if this subscriber only applies to certain entity types,
        if ($object instanceof ObjectEntity === true) {
            $this->cacheService->removeObject($object);

            return;
        }
        if ($object instanceof Entity === true) {
            $this->cacheService->removeSchema($object);

            return;
        }
        if ($object instanceof Endpoint === true) {
            $this->cacheService->removeEndpoint($object);

            return;
        }
    }//end preRemove()
}
