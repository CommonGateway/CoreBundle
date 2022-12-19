<?php

// src/Subscriber/DatabaseActivitySubscriber.php

namespace CommonGateway\CoreBundle\Subscriber;

use App\Entity\Attribute;
use App\Entity\Entity;
use CommonGateway\CoreBundle\Service\EavService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class ObjectReferenceSubscriber implements EventSubscriberInterface
{
    private EavService $eavService;

    public function __construct(
        EavService $eavService
    ) {
        $this->eavService = $eavService;
    }

    // this method can only return the event names; you cannot define a
    // custom method name to execute when each event triggers
    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
        ];
    }

    /**
     * Checks wheter we should check atributes and entities for connections.
     *
     * @param LifecycleEventArgs $args
     *
     * @return void
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        // Let see if we need to hook an attribute to an entity
        if (
            $object instanceof Attribute // Its an atribute
            && $object->getReference() // It has an reference
            && !$object->getObject() // It isnt currently connected to a schema
        ) {
            //$this->cacheService->cacheObject($object);
            return;
        }
        if (
            $object instanceof Entity // Is it an antity
            && $object->getReference() // Does it have an reference
        ) {

            //$this->cacheService->cacheShema($object);
            return;
        }
    }
}
