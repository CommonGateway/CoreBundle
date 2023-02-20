<?php

namespace CommonGateway\CoreBundle\Subscriber;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Event\ActionEvent;
use CommonGateway\CoreBundle\Service\CacheService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Monolog\Logger;
use PhpCsFixer\Event\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Provides common gateway events and logs based on doctrine events.
 *
 * This subscriber turns doctrine events into common gateway action events an provides those to listeners.
 * As a second function it also creates appropriate logging for doctrine events
 *
 * @Author Robert Zondervan <robert@conduction.nl>, Ruben van der Linde <ruben@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Subscriber
 */
class DoctrineToGatewayEventSubscriber implements EventSubscriberInterface
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
     * @var EventDispatcherInterface The event dispatcher.
     */
    private EventDispatcherInterface $eventDispatcher;

    /**
     * @var Logger The logger interface.
     */
    private Logger $logger;

    /**
     * Load required services, should not be approached directly.
     *
     * @param CacheService             $cacheService    The cache service.
     * @param EntityManagerInterface   $entityManager   The entity manager.
     * @param SessionInterface         $session         The current session.
     * @param EventDispatcherInterface $eventDispatcher The event dispatcher.
     */
    public function __construct(
        CacheService $cacheService,
        EntityManagerInterface $entityManager,
        SessionInterface $session,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->cacheService = $cacheService;
        $this->entityManager = $entityManager;
        $this->session = $session;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = new Logger('object');
    }//end __construct()

    /**
     * Get the subscribed events.
     *
     * @return array an array containing the subscribed events.
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::preRemove,
            Events::prePersist,
            Events::preUpdate,
            Events::postPersist,
            Events::postUpdate,
            Events::postRemove,
            Events::postLoad,
            Events::preFlush,
            Events::postFlush,
        ];
    }//end getSubscribedEvents()

    /**
     * Log and throw an event before we remove and object from te database.
     *
     * @param LifecycleEventArgs $args LifecycleEventArgs.
     *
     * @return void This function doesn't return anything.
     */
    public function preRemove(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        // If this subscriber only applies to certain entity types.
        if ($object instanceof ObjectEntity === false) {
            return;
        }

        // Write the log.
        $this->logger->info(
            'Deleting object from database',
            [
                'object'    => $object->getId(),
                'entity'    => $object->getEntity()->getId(),
            ]
        );

        // Throw the event.
        $event = new ActionEvent('commongateway.object.pre.delete', ['object' => $object]);
        $this->eventDispatcher->dispatch($event, 'commongateway.object.pre.delete');
    }//end preRemove()

    /**
     * Log and throw an event before we put a new object into te database.
     *
     * @param LifecycleEventArgs $args LifecycleEventArgs.
     *
     * @return void This function doesn't return anything.
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        // If this subscriber only applies to certain entity types.
        if ($object instanceof ObjectEntity === false) {
            return;
        }

        // Write the log.
        $this->logger->info(
            'Creating object in database',
            [
                'object'    => $object->getId(),
                'entity'    => $object->getEntity()->getId(),
            ]
        );

        // Throw the event.
        $event = new ActionEvent('commongateway.object.pre.create', ['object' => $object]);
        $this->eventDispatcher->dispatch($event, 'commongateway.object.pre.create');
    }//end prePersist()

    /**
     * Log and throw an event before we update an object into te database.
     *
     * @param LifecycleEventArgs $args LifecycleEventArgs.
     *
     * @return void This function doesn't return anything.
     */
    public function preUpdate(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        // If this subscriber only applies to certain entity types.
        if ($object instanceof ObjectEntity === false) {
            return;
        }

        // Write the log.
        $this->logger->info(
            'Updating object to database',
            [
                'object'    => $object->getId(),
                'entity'    => $object->getEntity()->getId(),
            ]
        );

        // Throw the event.
        $event = new ActionEvent('commongateway.object.pre.update', ['object' => $object]);
        $this->eventDispatcher->dispatch($event, 'commongateway.object.pre.update');
    }//end preUpdate()

    /**
     * Log and throw an event after we remove and object from te database.
     *
     * @param LifecycleEventArgs $args LifecycleEventArgs
     *
     * @return void This function doesn't return anything.
     */
    public function postRemove(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        // If this subscriber only applies to certain entity types.
        if ($object instanceof ObjectEntity === false) {
            return;
        }

        // Write the log.
        $this->logger->info(
            'Deleted object from database',
            [
            ]
        );

        // Throw the event.
        $event = new ActionEvent('commongateway.object.post.delete', []);
        $this->eventDispatcher->dispatch($event, 'commongateway.object.post.delete');
    }//end postRemove()

    /**
     * Log and throw an event after we put a new object into te database.
     *
     * @param LifecycleEventArgs $args LifecycleEventArgs
     *
     * @return void This function doesn't return anything.
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        // if this subscriber only applies to certain entity types.
        if ($object instanceof ObjectEntity === false) {
            return;
        }

        // Write the log.
        $this->logger->info(
            'Created object in database',
            [
                'object'    => $object->getId(),
                'entity'    => $object->getEntity()->getId(),
            ]
        );

        // Throw the event.
        $event = new ActionEvent('commongateway.object.post.create', ['object' => $object]);
        $this->eventDispatcher->dispatch($event, 'commongateway.object.post.create');
    }//end postPersist()

    /**
     * Log and throw an event after we update an object into te database.
     *
     * @param LifecycleEventArgs $args LifecycleEventArgs
     *
     * @return void This function doesn't return anything.
     */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        // if this subscriber only applies to certain entity types.
        if ($object instanceof ObjectEntity === false) {
            return;
        }

        // Write the log.
        $this->logger->info(
            'Updated object in database',
            [
                'object'    => $object->getId(),
                'entity'    => $object->getEntity()->getId(),
            ]
        );

        // Throw the event.
        $event = new ActionEvent('commongateway.object.post.update', ['object' => $object]);
        $this->eventDispatcher->dispatch($event, 'commongateway.object.post.update');
    }//end postUpdate()

    /**
     * Log and throw an event after we get an object from te database.
     *
     * @param LifecycleEventArgs $args LifecycleEventArgs
     *
     * @return void This function doesn't return anything.
     */
    public function postLoad(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        // if this subscriber only applies to certain entity types.
        if ($object instanceof ObjectEntity === false) {
            return;
        }

        // Write the log.
        $this->logger->info(
            'Read object from database',
            [
                'object'    => $object->getId(),
            ]
        );

        // Throw the event.
        $event = new ActionEvent('commongateway.object.post.read', ['object' => $object]);
        $this->eventDispatcher->dispatch($event, 'commongateway.object.post.read');
    }//end postLoad()

    /**
     * Log and throw an event before we flush the entity manager.
     *
     * @return void This function doesn't return anything.
     */
    public function preFlush(): void
    {
        // Write the log.
        $this->logger->info(
            'Flushing entity manager',
            [
            ]
        );

        // Throw the event.
        $event = new ActionEvent('commongateway.object.pre.flush', []);
        $this->eventDispatcher->dispatch($event, 'commongateway.object.pre.flush');
    }//end preFlush()

    /**
     * Log and throw an event after we flush the entity manager.
     *
     * @return void This function doesn't return anything.
     */
    public function postFlush(): void
    {
        // Write the log.
        $this->logger->info(
            'Flushed entity manager',
            [
            ]
        );

        // Throw the event.
        $event = new ActionEvent('commongateway.object.post.flush', []);
        $this->eventDispatcher->dispatch($event, 'commongateway.object.post.flush');
    }//end postFlush()
}//end class
