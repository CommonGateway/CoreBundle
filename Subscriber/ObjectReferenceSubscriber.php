<?php

// src/Subscriber/DatabaseActivitySubscriber.php

namespace CommonGateway\CoreBundle\Subscriber;

use App\Entity\Attribute;
use App\Entity\Entity;
use CommonGateway\CoreBundle\Service\EavService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class ObjectReferenceSubscriber implements EventSubscriberInterface
{
    private EavService $eavService;
    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        EavService $eavService
    ) {
        $this->entityManager = $entityManager;
        $this->eavService = $eavService;
    }

    // this method can only return the event names; you cannot define a
    // custom method name to execute when each event triggers
    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::preUpdate,
        ];
    }

    /**
     * Checks whether we should check attributes and entities for connections.
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
            $object instanceof Attribute // It's an attribute
            && $object->getSchema() // It has a reference
            && !$object->getObject() // It isn't currently connected to a schema
        ) {
            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $object->getSchema()]);
            if ($entity) {
                $object->setObject($entity);
            }

            return;
        }
        if (
            $object instanceof Entity // Is it an antity
            && $object->getReference() // Does it have a reference
        ) {
            $attributes = $this->entityManager->getRepository('App:Attribute')->findBy(['schema' => $object->getReference()]);
            foreach ($attributes as $attribute) {
                if (!$attribute instanceof Attribute) {
                    continue;
                }
                $attribute->setObject($object);
                if ($attribute->getInversedByPropertyName() && !$attribute->getInversedBy()) {
                    $attribute->setInversedBy($object->getAttributeByName($attribute->getInversedByPropertyName()));
                }
            }

            return;
        }
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $this->prePersist($args);
    }
}
