<?php

namespace CommonGateway\CoreBundle\Subscriber;

use App\Entity\ObjectEntity;
use App\Event\ActionEvent;
use App\Service\ObjectEntityService as GatewayObjectEntityService;
use CommonGateway\CoreBundle\Message\CacheMessage;
use CommonGateway\CoreBundle\Service\ActionService;
use CommonGateway\CoreBundle\Service\AuditTrailService;
use CommonGateway\CoreBundle\Service\CacheService;
use CommonGateway\CoreBundle\Service\ObjectEntityService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * This subscriber listens for events related to ObjectEntities.
 * The following old subscribers have been combined in this subscriber:
 * AuditTrailSubscriber, CacheDatabaseSubscriber, DoctrineToGatewayEventSubscriber, ObjectSyncSubscriber.
 *
 * @todo: move old subscriber specific code to their own services, if this hasn't been done yet, for each todo. https://conduction.atlassian.net/browse/GW-1470
 *
 * @Author Wilco Louwerse <wilco@conduction.nl>, Sarai Misidjan <sarai@conduction.nl>, Barry Brands <barry@conduction.nl>, Robert Zondervan <robert@conduction.nl>, Ruben van der Linde <ruben@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Subscriber
 */
class ObjectEntitySubscriber implements EventSubscriberInterface
{

    /**
     * The logger interface.
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * The current session.
     *
     * @var SessionInterface
     */
    private SessionInterface $session;

    /**
     * The constructor sets al needed variables.
     *
     * @param ObjectEntityService      $objectEntityService The Object Entity Service.
     * @param LoggerInterface          $pluginLogger        The logger interface.
     * @param RequestStack             $requestStack        The request stack.
     * @param CacheService             $cacheService        The cache service.
     * @param AuditTrailService        $auditTrailService   The Audit Trail service.
     * @param MessageBusInterface      $messageBus          The messageBus for async messages.
     * @param EventDispatcherInterface $eventDispatcher     Event Dispatcher.
     * @param ActionService            $actionService       The action service.
     */
    public function __construct(
        private readonly ObjectEntityService $objectEntityService,
        LoggerInterface $pluginLogger,
        private readonly RequestStack $requestStack,
        private readonly CacheService $cacheService,
        private readonly AuditTrailService $auditTrailService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ActionService $actionService
    ) {
        $this->logger = $pluginLogger;

        try {
            $this->session = $requestStack->getSession();
        } catch (SessionNotFoundException $exception) {
            $this->session = new Session();
        }

    }//end __construct()

    /**
     * Defines the events that the subscriber should subscribe to.
     * This method can only return the event names.
     * you cannot define a custom method name to execute when each event triggers.
     *
     * @return array The subscribed events
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::preUpdate,
            Events::preRemove,
            Events::postLoad,
            Events::postPersist,
            Events::postUpdate,
            Events::postRemove,
        ];

    }//end getSubscribedEvents()

    /**
     * Handles prePersist Event.
     *
     * @param LifecycleEventArgs $args The lifecycle event arguments for this prePersist event.
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        if ($object instanceof ObjectEntity === false) {
            return;
        }

        // Set the Owner and Organization for this ObjectEntity.
        $object = $this->objectEntityService->setOwnerAndOrg($object);

        // TODO: old DoctrineToGatewayEventSubscriber code: 'Creating object in database.'
        // Write the log.
        $this->logger->info(
            'Creating object in database',
            [
                'object' => $object->getId(),
                'entity' => $object->getEntity()->getId(),
            ]
        );

        // Throw the event.
        $event = new ActionEvent(
            'commongateway.action.event',
            [
                'object' => $object,
                'entity' => [
                    'id'        => $object->getEntity()->getId(),
                    'reference' => $object->getEntity()->getReference(),
                ],
            ],
            'commongateway.object.pre.create'
        );
        $this->eventDispatcher->dispatch($event, 'commongateway.action.event');

    }//end prePersist()

    /**
     * Handles preUpdate Event.
     *
     * @param LifecycleEventArgs $args The lifecycle event arguments for this preUpdate event.
     */
    public function preUpdate(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        if ($object instanceof ObjectEntity === false) {
            return;
        }

        // Set object id and schema id in session
        $this->session->set('object', $object->getId()->toString());
        $this->session->set('schema', $object->getEntity() !== null ? $object->getEntity()->getId()->toString() : null);

        // TODO: old DoctrineToGatewayEventSubscriber code: 'Updating object to database.'
        // Write the log.
        $this->logger->info(
            'Updating object to database',
            [
                'object' => $object->getId(),
                'entity' => $object->getEntity()->getId(),
            ]
        );

        // Throw the event.
        $event = new ActionEvent(
            'commongateway.action.event',
            [
                'object' => $object,
                'entity' => [
                    'id'        => $object->getEntity()->getId(),
                    'reference' => $object->getEntity()->getReference(),
                ],
            ],
            'commongateway.object.pre.update'
        );
        $this->eventDispatcher->dispatch($event, 'commongateway.action.event');

    }//end preUpdate()

    /**
     * Handles preRemove Event.
     *
     * @param LifecycleEventArgs $args The lifecycle event arguments for this preRemove event.
     */
    public function preRemove(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        if ($object instanceof ObjectEntity === false) {
            return;
        }

        // Set object id and schema id in session
        $this->session->set('object', $object->getId()->toString());
        $this->session->set('schema', $object->getEntity() !== null ? $object->getEntity()->getId()->toString() : null);

        // TODO: old AuditTrailSubscriber code:
        if ($object->getEntity() !== null
            && $object->getEntity()->getCreateAuditTrails() === true
            && $this->requestStack->getMainRequest() !== null
            && $this->requestStack->getMainRequest()->getMethod() === 'DELETE'
        ) {
            $config = [
                'action' => 'DELETE',
                'result' => 204,
                'new'    => null,
                'old'    => $object->toArray(),
            ];

            $this->auditTrailService->createAuditTrail($object, $config);
        }

        // TODO: old CacheDatabaseSubscriber code: 'Remove objects from the cache after they are removed from the database.'
        $this->cacheService->removeObject($object);

        // TODO: old DoctrineToGatewayEventSubscriber code: 'Deleting object from database.'
        // Write the log.
        $this->logger->info(
            'Deleting object from database',
            [
                'object' => $object->getId(),
                'entity' => $object->getEntity()->getId(),
            ]
        );

        // Throw the event.
        $event = new ActionEvent(
            'commongateway.action.event',
            [
                'object' => $object,
                'entity' => [
                    'id'        => $object->getEntity()->getId(),
                    'reference' => $object->getEntity()->getReference(),
                ],
            ],
            'commongateway.object.pre.delete'
        );
        $this->eventDispatcher->dispatch($event, 'commongateway.action.event');

    }//end preRemove()

    /**
     * Handles postLoad Event.
     *
     * @param LifecycleEventArgs $args The lifecycle event arguments for this postLoad event.
     */
    public function postLoad(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        if ($object instanceof ObjectEntity === false) {
            return;
        }

        // TODO: old AuditTrailSubscriber code: 'Adds object resources from identifier.'
        if ($object->getEntity() !== null
            && $object->getEntity()->getCreateAuditTrails() === true
            && $this->requestStack->getMainRequest() !== null
            && $this->requestStack->getMainRequest()->getMethod() === 'GET'
        ) {
            $action = 'LIST';
            if ($this->session->get('object') !== null) {
                $action = 'RETRIEVE';
            }

            $config = [
                'action' => $action,
                'result' => 200,
            ];

            $this->auditTrailService->createAuditTrail($object, $config);
        }

        // TODO: old DoctrineToGatewayEventSubscriber code: 'Read object from database.'
        // Write the log
        $this->logger->info(
            'Read object from database',
            [
                'object' => $object->getId(),
            ]
        );

        // Throw the event
        $event = new ActionEvent(
            'commongateway.action.event',
            ['object' => $object],
            'commongateway.object.post.read'
        );
        $this->eventDispatcher->dispatch($event, 'commongateway.action.event');

    }//end postLoad()

    /**
     * Handles postPersist Event.
     *
     * @param LifecycleEventArgs $args The lifecycle event arguments for this prePersist event.
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        // Check if object is an instance of an ObjectEntity.
        if ($object instanceof ObjectEntity === false) {
            return;
        }

        // Set object id and schema id in session
        $this->session->set('object', $object->getId()->toString());
        $this->session->set('schema', $object->getEntity() !== null ? $object->getEntity()->getId()->toString() : null);

        // TODO: old CacheDatabaseSubscriber code: 'Updates the cache whenever an object is put into the database.'
        // $this->messageBus->dispatch(new CacheMessage($object->getId()));
        $this->cacheService->cacheObject($object);

        // TODO: old AuditTrailSubscriber code: 'Passes the result of prePersist to preUpdate.'
        if ($object->getEntity() !== null
            && $object->getEntity()->getCreateAuditTrails() === true
            && $this->requestStack->getMainRequest() !== null
            && $this->requestStack->getMainRequest()->getMethod() === 'POST'
        ) {
            $config = [
                'action' => 'CREATE',
                'result' => 201,
                'new'    => $object->toArray(),
                'old'    => null,
            ];

            $this->auditTrailService->createAuditTrail($object, $config);
        }

        // TODO: old ObjectSyncSubscriber code: 'Passes the result of prePersist to preUpdate.'
        // Check if there is a synchronisation for this object.
        if ($object->getSynchronizations() !== null && $object->getSynchronizations()->first() !== false) {
            $this->logger->info("There is already a synchronisation for this object {$object->getId()->toString()}.");
        } else if (($defaultSource = $object->getEntity()->getDefaultSource()) === null) {
            $this->logger->info("There is no default source set to the entity of this object {$object->getId()->toString()}.");
        } else {
            $data = [
                'object' => $object,
                'schema' => $object->getEntity(),
                'source' => $defaultSource,
            ];

            $this->logger->info('Dispatch event with subtype: \'commongateway.object.sync\'');

            // Dispatch event.
            $this->actionService->dispatchEvent('commongateway.action.event', $data, 'commongateway.object.sync');
        }

        // TODO: old DoctrineToGatewayEventSubscriber code: 'Created object in database.'
        // Write the log.
        $this->logger->info(
            'Created object in database',
            [
                'object' => $object->getId(),
                'entity' => $object->getEntity()->getId(),
            ]
        );

        // Throw the event.
        $event = new ActionEvent(
            'commongateway.action.event',
            [
                'object' => $object,
                'entity' => [
                    'id'        => $object->getEntity()->getId(),
                    'reference' => $object->getEntity()->getReference(),
                ],
            ],
            'commongateway.object.post.create'
        );
        $this->eventDispatcher->dispatch($event, 'commongateway.action.event');

    }//end postPersist()

    /**
     * Handles postUpdate Event.
     *
     * @param LifecycleEventArgs $args The lifecycle event arguments for this postUpdate event.
     */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        if ($object instanceof ObjectEntity === false) {
            return;
        }

        // TODO: old CacheDatabaseSubscriber code: 'Updates the cache whenever an object is put into the database.'
        // $this->messageBus->dispatch(new CacheMessage($object->getId()));
        $this->cacheService->cacheObject($object);

        // TODO: old AuditTrailSubscriber code: 'Adds object resources from identifier.'
        if ($object->getEntity() !== null
            && $object->getEntity()->getCreateAuditTrails() === true
            && $this->requestStack->getMainRequest() !== null
            && in_array($this->requestStack->getMainRequest()->getMethod(), ['UPDATE', 'PATCH']) === true
        ) {
            $new = $object->toArray();
            $old = $this->cacheService->getObject($object->getId());

            if ($new !== $old) {
                $action = 'UPDATE';
                if ($this->requestStack->getMainRequest()->getMethod() === 'PATCH') {
                    $action = 'PARTIAL_UPDATE';
                }

                $config = [
                    'action' => $action,
                    'result' => 200,
                    'new'    => $new,
                    'old'    => $old,
                ];

                $this->auditTrailService->createAuditTrail($object, $config);
            }
        }//end if

        // TODO: old DoctrineToGatewayEventSubscriber code: 'Updated object in database.'
        // Write the log.
        $this->logger->info(
            'Updated object in database',
            [
                'object' => $object->getId(),
                'entity' => $object->getEntity()->getId(),
            ]
        );

        // Throw the event.
        $event = new ActionEvent(
            'commongateway.action.event',
            [
                'object' => $object,
                'entity' => [
                    'id'        => $object->getEntity()->getId(),
                    'reference' => $object->getEntity()->getReference(),
                ],
            ],
            'commongateway.object.post.update'
        );
        $this->eventDispatcher->dispatch($event, 'commongateway.action.event');

    }//end postUpdate()

    /**
     * Handles postRemove Event.
     *
     * @param LifecycleEventArgs $args The lifecycle event arguments for this postRemove event.
     */
    public function postRemove(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        if ($object instanceof ObjectEntity === false) {
            return;
        }

        // TODO: old DoctrineToGatewayEventSubscriber code: 'Deleted object from database.'
        // Write the log.
        $this->logger->info(
            'Deleted object from database',
            []
        );

        // Throw the event.
        $event = new ActionEvent(
            'commongateway.action.event',
            [],
            'commongateway.object.post.delete'
        );
        $this->eventDispatcher->dispatch($event, 'commongateway.action.event');

    }//end postRemove()
}//end class
