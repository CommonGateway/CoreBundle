<?php

namespace CommonGateway\CoreBundle\Subscriber;

use App\Entity\Action;
use App\Entity\AuditTrail;
use App\Entity\ObjectEntity;
use App\Message\ActionMessage;
use App\Service\ObjectEntityService;
use App\Service\SynchronizationService;
use CommonGateway\CoreBundle\Service\CacheService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;

class ObjectSyncSubscriber implements EventSubscriberInterface
{
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var SynchronizationService
     */
    private SynchronizationService $syncService;

    /**
     * @var GatewayResourceService
     */
    private GatewayResourceService $resourceService;

    /**
     * @var ObjectEntityService
     */
    private ObjectEntityService $objectEntityService;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $pluginLogger;

    /**
     * @param EntityManagerInterface $entityManager
     * @param SynchronizationService $syncService
     * @param  GatewayResourceService $resourceService
     * @param ObjectEntityService $objectEntityService
     * @param LoggerInterface        $pluginLogger
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        SynchronizationService $syncService,
        GatewayResourceService $resourceService,
        ObjectEntityService $objectEntityService,
        LoggerInterface $pluginLogger
    ) {
        $this->entityManager = $entityManager;
        $this->syncService = $syncService;
        $this->resourceService = $resourceService;
        $this->objectEntityService = $objectEntityService;
        $this->pluginLogger = $pluginLogger;
    }//end __construct()

    /**
     * Defines the events that the subscriber should subscribe to.
     *
     * @return array The subscribed events
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist
        ];
    }//end getSubscribedEvents()

    /**
     * Passes the result of prePersist to preUpdate.
     *
     * @param LifecycleEventArgs $args The lifecycle event arguments for this prePersist
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        // Check if object is an instance of an ObjectEntity.
        if ($object instanceof ObjectEntity === false) {
            return;
        }

        // Check if there is a synchronisation for this object.
        if ($object->getSynchronizations() !== null
            && $object->getSynchronizations()->first() !== false
        ) {
            $this->pluginLogger->info('There is already a synchronisation for this object.');
            return;
        }

        // Check if the default source of the entity of the object is null.
        if (($defaultSource = $object->getEntity()->getDefaultSource()) === null) {
            $this->pluginLogger->info('There is no default source set to the entity of this object.');
            return;
        }
        
        $data = [
            'object' => $object,
            'schema' => $object->getEntity(),
            'source' => $defaultSource
        ];

        $this->pluginLogger->info('Dispatch event with subtype: \'commongateway.object.sync\'');
        
        // dispatch event
        $this->objectEntityService->dispatchEvent('commongateway.action.event', $data, 'commongateway.object.sync');
    }//end prePersist()
}//end class
