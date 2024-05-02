<?php

// src/Subscriber/DatabaseActivitySubscriber.php
namespace CommonGateway\CoreBundle\Subscriber;

use App\Entity\Attribute;
use App\Entity\Entity;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * @Author Ruben van der Linde <ruben@conduction.nl>, Sarai Misidjan <sarai@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class ObjectReferenceSubscriber implements EventSubscriberInterface
{

    /**
     * @var EntityManagerInterface $entityManager
     */
    private EntityManagerInterface $entityManager;

    /**
     * __construct
     */
    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;

    }//end __construct()

    /**
     * This method can only return the event names; you cannot define a,
     * custom method name to execute when each event triggers.
     *
     * @todo Should be a static.
     *
     * @return array
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
        ];

    }//end getSubscribedEvents()

    /**
     * Checks whether we should check attributes and entities for connections.
     *
     * @param LifecycleEventArgs $args
     *
     * @return void
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        // Let see if we need to hook an attribute to an entity.
        if ($object instanceof Attribute // It's an attribute.
            && ($object->getSchema() || $object->getReference()) // It has a reference.
            && !$object->getObject() // It isn't currently connected to a schema.
        ) {
            $reference = ($object->getReference() ?? $object->getSchema());
            $entity    = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $reference]);
            if ($entity !== null) {
                $object->setObject($entity);
                if ($object->getInversedByPropertyName() && !$object->getInversedBy()) {
                    $attribute = $entity->getAttributeByName($object->getInversedByPropertyName());
                    if ($attribute !== false) {
                        $object->setInversedBy($attribute);
                    }
                }
            }//end if

            return;
        }//end if

    }//end postPersist()

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->postPersist($args);

    }//end postUpdate()
}//end class
