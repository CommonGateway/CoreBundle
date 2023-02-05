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
 * Provides commongateway events and logs based on doctrine events.
 *
 * This subscriber turns doctrine events into common gateway action events an provides those to listeners.
 * As a second function it also creates appropriate logging for doctrine events
 */
class DoctrineToGatewayEventSubscriber implements EventSubscriberInterface
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
     * @var EventDispatcherInterface
     */
    private EventDispatcherInterface $eventDispatcher;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * Load requiered services, schould not be aprouched directly.
     *
     * @param CacheService             $cacheService The CacheService
     * @param EntityManagerInterface   $entityManager The EntityManagerInterface
     * @param SessionInterface         $session The SessionInterface
     * @param EventDispatcherInterface $eventDispatcher The EventDispatcherInterface
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
     * @return array
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
    }

    /**
     * Deleting object from database.
     *
     * @param LifecycleEventArgs $args
     *
     * @return void
     */
    public function preRemove(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        // if this subscriber only applies to certain entity types,
        if (!$object instanceof ObjectEntity) {
            return;
        }

        // Write the log
        $this->logger->info(
            'Deleting object from database',
            [
                'object'    => $object->getId(),
                'entity'    => $object->getEntity()->getId(),
            ]
        );

        // Throw the event
        $event = new ActionEvent('commongateway.object.pre.delete', ['object' => $object]);
        $this->eventDispatcher->dispatch($event, 'commongateway.object.pre.delete');
    }

    /**
     * Creating object in database.
     *
     * @param LifecycleEventArgs $args
     *
     * @return void
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        // if this subscriber only applies to certain entity types,
        if (!$object instanceof ObjectEntity) {
            return;
        }

        // Write the log
        $this->logger->info(
            'Creating object in database',
            [
                'object'    => $object->getId(),
                'entity'    => $object->getEntity()->getId(),
            ]
        );

        // Throw the event
        $event = new ActionEvent('commongateway.object.pre.create', ['object' => $object]);
        $this->eventDispatcher->dispatch($event, 'commongateway.object.pre.create');
    }

    /**
     * Updating object to database.
     *
     * @param LifecycleEventArgs $args
     *
     * @return void
     */
    public function preUpdate(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        // if this subscriber only applies to certain entity types,
        if (!$object instanceof ObjectEntity) {
            return;
        }

        // Write the log
        $this->logger->info(
            'Updating object to database',
            [
                'object'    => $object->getId(),
                'entity'    => $object->getEntity()->getId(),
            ]
        );

        // Throw the event
        $event = new ActionEvent('commongateway.object.pre.update', ['object' => $object]);
        $this->eventDispatcher->dispatch($event, 'commongateway.object.pre.update');
    }

    /**
     * Deleted object from database.
     *
     * @param LifecycleEventArgs $args
     *
     * @return void
     */
    public function postRemove(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        // if this subscriber only applies to certain entity types,
        if (!$object instanceof ObjectEntity) {
            return;
        }

        // Write the log
        $this->logger->info(
            'Deleted object from database',
            [
            ]
        );

        // Throw the event
        $event = new ActionEvent('commongateway.object.post.delete', []);
        $this->eventDispatcher->dispatch($event, 'commongateway.object.post.delete');
    }

    /**
     * Created object in database.
     *
     * @param LifecycleEventArgs $args
     *
     * @return void
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        // if this subscriber only applies to certain entity types,
        if (!$object instanceof ObjectEntity) {
            return;
        }

        // Write the log
        $this->logger->info(
            'Created object in database',
            [
                'object'    => $object->getId(),
                'entity'    => $object->getEntity()->getId(),
            ]
        );

        // Throw the event
        $event = new ActionEvent('commongateway.object.post.create', ['object' => $object]);
        $this->eventDispatcher->dispatch($event, 'commongateway.object.post.create');
    }

    /**
     * Updated object in database.
     *
     * @param LifecycleEventArgs $args
     *
     * @return void
     */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        // if this subscriber only applies to certain entity types,
        if (!$object instanceof ObjectEntity) {
            return;
        }

        // Write the log
        $this->logger->info(
            'Updated object in database',
            [
                'object'    => $object->getId(),
                'entity'    => $object->getEntity()->getId(),
            ]
        );

        // Throw the event
        $event = new ActionEvent('commongateway.object.post.update', ['object' => $object]);
        $this->eventDispatcher->dispatch($event, 'commongateway.object.post.update');
    }

    /**
     * Read object from database.
     *
     * @param LifecycleEventArgs $args
     *
     * @return void
     */
    public function postLoad(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        // if this subscriber only applies to certain entity types,
        if (!$object instanceof ObjectEntity) {
            return;
        }

        // Write the log
        $this->logger->info(
            'Read object from database',
            [
                'object'    => $object->getId(),
            ]
        );

        // Throw the event
        $event = new ActionEvent('commongateway.object.post.read', ['object' => $object]);
        $this->eventDispatcher->dispatch($event, 'commongateway.object.post.read');
    }

    /**
     * Flushing entity manager.
     *
     * @param LifecycleEventArgs $args
     *
     * @return void
     */
    public function preFlush(): void
    {
        // Write the log
        $this->logger->info(
            'Flushing entity manager',
            [
            ]
        );

        // Throw the event
        $event = new ActionEvent('commongateway.object.pre.flush', []);
        $this->eventDispatcher->dispatch($event, 'commongateway.object.pre.flush');
    }

    /**
     * Flushed entity manager.
     *
     * @param LifecycleEventArgs $args
     *
     * @return void
     */
    public function postFlush(): void
    {

        // Write the log
        $this->logger->info(
            'Flushed entity manager',
            [
            ]
        );

        // Throw the event
        $event = new ActionEvent('commongateway.object.post.flush', []);
        $this->eventDispatcher->dispatch($event, 'commongateway.object.post.flush');
    }
}
