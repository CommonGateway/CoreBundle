<?php

// src/Subscriber/EntitySyncSubscriber.php

namespace CommonGateway\CoreBundle\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\CollectionEntity;
use App\Entity\ObjectEntity;
use App\Entity\Entity;
use App\Entity\Endpoint;
use App\Entity\Synchronization;
use CommonGateway\CoreBundle\Service\CacheService;
use App\Service\SynchronizationService;

use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use EasyRdf\Http\Response;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Creates a new sync object for an source
 */
class EntitySyncSubscriber implements EventSubscriberInterface
{
    private SynchronizationService $synchronizationService;

    public function __construct(
        SynchronizationService $synchronizationService
    ) {
        $this->synchronizationService = $synchronizationService;
    }

    // this method can only return the event names; you cannot define a
    // custom method name to execute when each event triggers
    public function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => ['request', EventPriorities::PRE_WRITE],
        ];
    }

    /**
     * @param ViewEvent $event
     */
    public function request(ViewEvent $event)
    {
        if (
            $event->getRequest()->attributes->get('_route') !== 'create_sync'
        ) {
            return;
        }

        $objectId = $event->getRequest()->attributes->get('id');
        $sourceId = $event->getRequest()->attributes->get('sourceId');

        $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id'=>$objectEntity]);
        $source = $this->entityManager->getRepository('App:Gateway')->findOneBy(['id'=>$sourceId]);

        $synchronization = new Synchronization();
        $synchronization->setObject($objectEntity);
        $synchronization->setSource($source);
        $synchronization->setEntity($objectEntity->getEntity());

        $synchronization = $this->synchronizationService->handleSync();

        $this->entityManager->persist($synchronization);
        $this->entityManager->flush();

        return New Response(200);
    }

}
