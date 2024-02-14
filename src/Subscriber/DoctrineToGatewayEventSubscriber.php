<?php

// src/Subscriber/DatabaseActivitySubscriber.php
namespace CommonGateway\CoreBundle\Subscriber;

use App\Entity\ObjectEntity;
use App\Event\ActionEvent;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
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
     * @var SessionInterface $session
     */
    private SessionInterface $session;

    /**
     * @var LoggerInterface $logger
     */
    private LoggerInterface $logger;

    /**
     * Load required services, should not be approached directly.
     *
     * @param RequestStack             $requestStack
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(
        RequestStack $requestStack,
        private readonly EventDispatcherInterface $eventDispatcher,
        LoggerInterface $objectLogger
    ) {

        try {
            $this->session = $requestStack->getSession();
        } catch (SessionNotFoundException $exception) {
            $this->session = new Session();
        }

        $this->logger = $objectLogger;

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
