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
 * Todo.
 *
 * @Author Robert Zondervan <robert@conduction.nl>, Ruben van der Linde <ruben@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Subscriber
 */
class CacheDatabaseSubscriber implements EventSubscriberInterface
{
    /**
     * @var CacheService The cache service.
     */
    private CacheService $cacheService;

    /**
     * @var EntityManagerInterface The entity manager.
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var SessionInterface The current session.
     */
    private SessionInterface $session;

    /**
     * @param CacheService           $cacheService  The cache service.
     * @param EntityManagerInterface $entityManager The entity manager.
     * @param SessionInterface       $session       The current session.
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
     * Gets the subscribed events.
     *
     * @return array an array containing the subscribed events.
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
     * Updates the cache after an object is changed in the database.
     *
     * @param LifecycleEventArgs $args LifecycleEventArgs.
     *
     * @return void This function doesn't return anything.
     */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->postPersist($args);
    }//end postUpdate()

    /**
     * Updates the cache after an object is put into the database.
     *
     * @param LifecycleEventArgs $args LifecycleEventArgs.
     *
     * @return void This function doesn't return anything.
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        // if this subscriber only applies to certain entity types.
        if (
            $object instanceof Entity === true ||
            $object instanceof ObjectEntity === true ||
            $object instanceof Endpoint === true
        ) {
            $this->cacheService->setToCache($object);
            return;
        }
    }//end  postPersist()

    /**
     * Updates the cache before an object is changed in the database.
     *
     * @param LifecycleEventArgs $args LifecycleEventArgs.
     *
     * @return void This function doesn't return anything.
     */
    public function preUpdate(LifecycleEventArgs $args): void
    {
        $this->prePersist($args);
    }//end preUpdate()

    /**
     * Updates the cache before an object is put into the database.
     *
     * @param LifecycleEventArgs $args LifecycleEventArgs.
     *
     * @return void This function doesn't return anything.
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        if ($object instanceof ObjectEntity === true) {
            $this->updateParents($object);
        }
    }//end prePersist()

    /**
     * Remove objects from the cache after they are removed from the database.
     *
     * @param LifecycleEventArgs $args LifecycleEventArgs.
     *
     * @return void This function doesn't return anything.
     */
    public function preRemove(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        // if this subscriber only applies to certain entity types.
        if (
            $object instanceof Entity === true ||
            $object instanceof ObjectEntity === true ||
            $object instanceof Endpoint === true
        ) {
            $this->cacheService->removeFromCache($object);

            return;
        }
    }//end preRemove()
}
