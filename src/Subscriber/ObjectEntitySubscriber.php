<?php

namespace CommonGateway\CoreBundle\Subscriber;

use App\Entity\ObjectEntity;
use App\Service\ObjectEntityService;
use CommonGateway\CoreBundle\Service\AuditTrailService;
use CommonGateway\CoreBundle\Service\CacheService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Security;

/**
 * This subscriber listens for events related to ObjectEntities.
 * The following old subscribers have been combined in this subscriber:
 * AuditTrailSubscriber, CacheDatabaseSubscriber, DoctrineToGatewayEventSubscriber, ObjectSyncSubscriber.
 * @todo: move old subscriber specific code to their own services, if this hasn't been done yet.
 *
 * @Author Wilco Louwerse <wilco@conduction.nl>, Sarai Misidjan <sarai@conduction.nl>, Barry Brands <barry@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Subscriber
 */
class ObjectEntitySubscriber implements EventSubscriberInterface
{

    /**
     * @var ObjectEntityService
     */
    private ObjectEntityService $objectEntityService;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $pluginLogger;
    
    /**
     * The entity manager.
     *
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;
    
    /**
     * The logger interface.
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    
    /**
     * Security for getting the current user.
     *
     * @var Security
     */
    private Security $security;
    
    /**
     * @var ParameterBagInterface
     */
    private ParameterBagInterface $parameterBag;
    
    /**
     * The request stack.
     *
     * @var RequestStack
     */
    private RequestStack $requestStack;
    
    /**
     * The cache service.
     *
     * @var CacheService
     */
    private CacheService $cacheService;
    
    /**
     * The Audit Trail service.
     *
     * @var AuditTrailService
     */
    private AuditTrailService $auditTrailService;
    
    /**
     * The current session.
     *
     * @var SessionInterface
     */
    private SessionInterface $session;
    
    /**
     * The constructor sets al needed variables.
     *
     * @param ObjectEntityService $objectEntityService The Object Entity Service.
     * @param LoggerInterface $pluginLogger The logger interface for plugins.
     * @param EntityManagerInterface $entityManager The entity manager.
     * @param LoggerInterface $valueSubscriberLogger The logger interface.
     * @param Security $security Security for getting the current user.
     * @param ParameterBagInterface $parameterBag Parameter bag.
     * @param RequestStack $requestStack The request stack.
     * @param CacheService $cacheService The cache service.
     * @param AuditTrailService $auditTrailService The Audit Trail service.
     * @param SessionInterface $session The current session.
     */
    public function __construct(
        ObjectEntityService $objectEntityService,
        LoggerInterface $pluginLogger,
        EntityManagerInterface $entityManager,
        LoggerInterface $valueSubscriberLogger,
        Security $security,
        ParameterBagInterface $parameterBag,
        RequestStack $requestStack,
        CacheService $cacheService,
        AuditTrailService $auditTrailService,
        SessionInterface $session
    ) {
        $this->objectEntityService = $objectEntityService;
        $this->pluginLogger        = $pluginLogger;
        $this->entityManager       = $entityManager;
        $this->logger              = $valueSubscriberLogger;
        $this->security            = $security;
        $this->parameterBag        = $parameterBag;
        $this->requestStack        = $requestStack;
        $this->cacheService        = $cacheService;
        $this->auditTrailService   = $auditTrailService;
        $this->session             = $session;

    }//end __construct()

    /**
     * Defines the events that the subscriber should subscribe to.
     *
     * @return array The subscribed events
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::preRemove,
            Events::postLoad,
            Events::postPersist,
            Events::postUpdate,
        ];

    }//end getSubscribedEvents()
    
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
        if ($object->getSynchronizations() !== null
            && $object->getSynchronizations()->first() !== false
        ) {
            $this->pluginLogger->info('There is already a synchronisation for this object.');

            return;
        }//end if

        // Check if the default source of the entity of the object is null.
        if (($defaultSource = $object->getEntity()->getDefaultSource()) === null) {
            $this->pluginLogger->info('There is no default source set to the entity of this object.');

            return;
        }

        $data = [
            'object' => $object,
            'schema' => $object->getEntity(),
            'source' => $defaultSource,
        ];

        $this->pluginLogger->info('Dispatch event with subtype: \'commongateway.object.sync\'');

        // Dispatch event.
        $this->objectEntityService->dispatchEvent('commongateway.action.event', $data, 'commongateway.object.sync');

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
        
        // TODO: old AuditTrailSubscriber code: 'Adds object resources from identifier.'
        if ($object->getEntity() !== null
            && $object->getEntity()->getCreateAuditTrails() === true
            && $this->requestStack->getMainRequest() !== null
            && in_array($this->requestStack->getMainRequest()->getMethod(), ['UPDATE', 'PATCH']) === true
        ) {
            $new = $object->toArray();
            $old = $this->cacheService->getObject($object->getId());
            
            if ($new === $old) {
                return;
            }
            
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
        
    }//end postUpdate()
}//end class
