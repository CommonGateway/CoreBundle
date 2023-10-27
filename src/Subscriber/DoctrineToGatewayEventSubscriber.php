<?php

// src/Subscriber/DatabaseActivitySubscriber.php
namespace CommonGateway\CoreBundle\Subscriber;

use App\Entity\ObjectEntity;
use App\Event\ActionEvent;
use CommonGateway\CoreBundle\Service\CacheService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Monolog\Logger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Provides commongateway events and logs based on doctrine events.
 *
 * This subscriber turns doctrine events into common gateway action events and provides those to listeners.
 * As a second function it also creates appropriate logging for doctrine events.
 *
 * @Author Ruben van der Linde <ruben@conduction.nl>, Robert Zondervan <robert@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Subscriber
 */
class DoctrineToGatewayEventSubscriber implements EventSubscriberInterface
{

    /**
     * The CacheService
     *
     * @var CacheService $cacheService
     */
    private CacheService $cacheService;

    /**
     * @var EntityManagerInterface $entityManager
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var SessionInterface $session
     */
    private SessionInterface $session;

    /**
     * @var EventDispatcherInterface $eventDispatcher
     */
    private EventDispatcherInterface $eventDispatcher;

    /**
     * @var Logger $logger
     */
    private Logger $logger;

    /**
     * Load required services, should not be approached directly.
     *
     * @param CacheService             $cacheService
     * @param EntityManagerInterface   $entityManager
     * @param SessionInterface         $session
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(
        CacheService $cacheService,
        EntityManagerInterface $entityManager,
        SessionInterface $session,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->cacheService    = $cacheService;
        $this->entityManager   = $entityManager;
        $this->session         = $session;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger          = new Logger('object');

    }//end __construct()

    /**
     * This method can only return the event names; you cannot define a
     * custom method name to execute when each event triggers.
     *
     * @return array
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::preFlush,
            Events::postFlush,
        ];

    }//end getSubscribedEvents()

    /**
     * Flushing entity manager.
     *
     * @return void Nothing.
     */
    public function preFlush(): void
    {
        // Write the log
        $this->logger->info(
            'Flushing entity manager',
            []
        );

        // Throw the event
        $event = new ActionEvent('commongateway.action.event', [], 'commongateway.object.pre.flush');
        $this->eventDispatcher->dispatch($event, 'commongateway.action.event');

    }//end preFlush()

    /**
     * Flushed entity manager.
     *
     * @return void Nothing.
     */
    public function postFlush(): void
    {
        // Write the log.
        $this->logger->info(
            'Flushed entity manager',
            []
        );

        // Throw the event.
        $event = new ActionEvent('commongateway.action.event', [], 'commongateway.object.post.flush');
        $this->eventDispatcher->dispatch($event, 'commongateway.action.event');

    }//end postFlush()
}//end class
