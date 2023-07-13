<?php

namespace CommonGateway\CoreBundle\Subscriber;

use App\Entity\AuditTrail;
use App\Entity\ObjectEntity;
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

class AuditTrailSubscriber implements EventSubscriberInterface
{

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
     * @param EntityManagerInterface $entityManager         The entity manager
     * @param LoggerInterface        $valueSubscriberLogger The logger interface
     * @param Security               $security              Security for getting the current user
     * @param ParameterBagInterface  $parameterBag          Parameter bag
     * @param RequestStack           $requestStack          The request stack
     * @param CacheService           $cacheService          The cache service
     * @param AuditTrailService      $auditTrailService     The Audit Trail service
     * @param SessionInterface       $session               The current session
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $valueSubscriberLogger,
        Security $security,
        ParameterBagInterface $parameterBag,
        RequestStack $requestStack,
        CacheService $cacheService,
        AuditTrailService $auditTrailService,
        SessionInterface $session
    ) {
        $this->entityManager     = $entityManager;
        $this->logger            = $valueSubscriberLogger;
        $this->security          = $security;
        $this->parameterBag      = $parameterBag;
        $this->requestStack      = $requestStack;
        $this->cacheService      = $cacheService;
        $this->auditTrailService = $auditTrailService;
        $this->session           = $session;

    }//end __construct()

    /**
     * Defines the events that the subscriber should subscribe to.
     *
     * @return array The subscribed events
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::postUpdate,
            Events::postPersist,
            Events::preRemove,
            Events::postLoad,
        ];

    }//end getSubscribedEvents()

    /**
     * Adds object resources from identifier.
     *
     * @param LifecycleEventArgs $args The lifecycle event arguments for this event
     */
    public function postLoad(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        if ($object instanceof ObjectEntity === false
            || $object->getEntity() === null
            || $object->getEntity()->getCreateAuditTrails() === false
            || $this->requestStack->getMainRequest() === null
            || $this->requestStack->getMainRequest()->getMethod() !== 'GET'
        ) {
            return;
        }

        $action = 'LIST';
        if ($this->session->get('object') !== null) {
            $action = 'RETRIEVE';
        }

        $config = [
            'action' => $action,
            'result' => 200,
        ];

        $this->auditTrailService->createAuditTrail($object, $config);

    }//end postLoad()

    /**
     * Adds object resources from identifier.
     *
     * @param LifecycleEventArgs $args The lifecycle event arguments for this event
     */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        if ($object instanceof ObjectEntity === false
            || $object->getEntity() === null
            || $object->getEntity()->getCreateAuditTrails() === false
            || $this->requestStack->getMainRequest() === null
            || in_array($this->requestStack->getMainRequest()->getMethod(), ['UPDATE', 'PATCH']) === false
        ) {
            return;
        }

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

    }//end postUpdate()

    /**
     * Passes the result of prePersist to preUpdate.
     *
     * @param LifecycleEventArgs $args The lifecycle event arguments for this prePersist
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        if ($object instanceof ObjectEntity === false
            || $object->getEntity() === null
            || $object->getEntity()->getCreateAuditTrails() === false
            || $this->requestStack->getMainRequest() === null
            || $this->requestStack->getMainRequest()->getMethod() !== 'POST'
        ) {
            return;
        }

        $config = [
            'action' => 'CREATE',
            'result' => 201,
            'new'    => $object->toArray(),
            'old'    => null,
        ];

        $this->auditTrailService->createAuditTrail($object, $config);

    }//end postPersist()

    public function preRemove(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        if ($object instanceof ObjectEntity === false
            || $object->getEntity() === null
            || $object->getEntity()->getCreateAuditTrails() === false
            || $this->requestStack->getMainRequest() === null
            || $this->requestStack->getMainRequest()->getMethod() !== 'DELETE'
        ) {
            return;
        }

        $config = [
            'action' => 'DELETE',
            'result' => 204,
            'new'    => null,
            'old'    => $object->toArray(),
        ];

        $this->auditTrailService->createAuditTrail($object, $config);

    }//end preRemove()
}//end class
