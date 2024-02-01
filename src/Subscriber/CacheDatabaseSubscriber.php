<?php

// src/Subscriber/DatabaseActivitySubscriber.php
namespace CommonGateway\CoreBundle\Subscriber;

use App\Entity\Endpoint;
use App\Entity\Entity;
use CommonGateway\CoreBundle\Service\CacheService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @Author Ruben van der Linde <ruben@conduction.nl>, Barry Brands <barry@conduction.nl>, Robert Zondervan <robert@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Subscriber
 */
class CacheDatabaseSubscriber implements EventSubscriberInterface
{

    /**
     * @var SessionInterface $session
     */
    private SessionInterface $session;

    public function __construct(
        private readonly CacheService $cacheService,
        RequestStack $requestStack,
    ) {
        $this->session = $requestStack->getSession();

    }//end __construct()

    // this method can only return the event names; you cannot define a,
    // custom method name to execute when each event triggers.
    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::preRemove,
            Events::postUpdate,
        ];

    }//end getSubscribedEvents()

    /**
     * Executes postPersist().
     *
     * @param LifecycleEventArgs $args
     *
     * @return void Nothing.
     */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->postPersist($args);

    }//end postUpdate()

    /**
     * Updates the cache whenever an object is put into the database.
     *
     * @param LifecycleEventArgs $args
     *
     * @return void Nothing.
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        // if this subscriber only applies to certain entity types,
        if ($object instanceof Entity === true) {
            $this->cacheService->cacheShema($object);

            return;
        }

        if ($object instanceof Endpoint === true) {
            $this->cacheService->cacheEndpoint($object);

            return;
        }

    }//end postPersist()

    /**
     * Remove objects from the cache after they are removed from the database.
     *
     * @param LifecycleEventArgs $args
     *
     * @return void
     */
    public function preRemove(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        // if this subscriber only applies to certain entity types,
        if ($object instanceof Entity) {
            // @todo finish this function.
            // $this->cacheService->removeSchema($object);
            return;
        }

        if ($object instanceof Endpoint) {
            // @todo finish this function.
            $this->cacheService->removeEndpoint($object);

            return;
        }

    }//end preRemove()
}//end class
