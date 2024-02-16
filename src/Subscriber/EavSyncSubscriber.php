<?php

namespace CommonGateway\CoreBundle\Subscriber;

use ApiPlatform\Symfony\EventListener\EventPriorities;
use App\Entity\Action;
use App\Entity\Gateway as Source;
use App\Entity\ObjectEntity;
use App\Entity\Synchronization;
use App\Service\SynchronizationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class EavSyncSubscriber implements EventSubscriberInterface
{

    private EntityManagerInterface $entityManager;

    private SynchronizationService $synchronizationService;

    public function __construct(EntityManagerInterface $entityManager, SynchronizationService $synchronizationService)
    {
        $this->entityManager          = $entityManager;
        $this->synchronizationService = $synchronizationService;

    }//end __construct()

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => [
                'eavsync',
                EventPriorities::PRE_DESERIALIZE,
            ],
        ];

    }//end getSubscribedEvents()

    public function eavsync(RequestEvent $event): void
    {
        $route = $event->getRequest()->attributes->get('_route');

        if ($route !== 'api_object_entities_create_sync_collection'
        ) {
            return;
        }

        // Grap the id's
        $objectId = $event->getRequest()->attributes->get('id');
        $sourceId = $event->getRequest()->attributes->get('sourceId');

        // Grap the objects for the ids
        $objectEntity = $this->entityManager->getRepository(ObjectEntity::class)->findOneBy(['id' => $objectId]);
        $source       = $this->entityManager->getRepository(Source::class)->findOneBy(['id' => $sourceId]);

        $sourceId = $event->getRequest()->query->get('externalId', '');
        $endpoint = $event->getRequest()->query->get('endpoint', null);
        $actionId = $event->getRequest()->query->get('action', null);
        // Get a sync objcet
        $status = 202;
        if (!$synchronization = $this->entityManager->getRepository(Synchronization::class)->findOneBy(['object' => $objectEntity->getId(), 'gateway' => $source])) {
            $synchronization = new Synchronization($source);
            $synchronization->setObject($objectEntity);
            $synchronization->setSourceId($sourceId);
            $synchronization->setEndpoint($endpoint);
            if ($actionId) {
                $action = $this->entityManager->getRepository(Action::class)->findOneBy(['id' => $actionId]);
                $synchronization->setAction($action);
            }

            $status = 201;
            // Lets do the practical stuff
            // (isset($event->getRequest()->query->get('endpoint', false))? '': '');
        }

                $synchronization = $this->synchronizationService->handleSync($synchronization);

        $this->entityManager->persist($synchronization);
        $this->entityManager->flush();

        $event->setResponse(
            new Response(
                json_encode(
                    [
                        'id'                => $synchronization->getId(),
                        'sourceLastChanged' => $synchronization->getSourceLastChanged(),
                        'lastChecked'       => $synchronization->getLastChecked(),
                        'lastSynced'        => $synchronization->getLastSynced(),
                        'dateCreated'       => $synchronization->getDateCreated(),
                        'dateModified'      => $synchronization->getDateModified(),
                    ]
                ),
                $status,
            )
        );

    }//end eavsync()
}//end class
