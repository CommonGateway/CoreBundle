<?php

namespace CommonGateway\CoreBundle\Subscriber;

use App\Entity\Coupler;
use App\Entity\Value;
use CommonGateway\CoreBundle\Service\ValueService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Ramsey\Uuid\Uuid;

class ValueSubscriber implements EventSubscriberInterface
{

    /**
     * @var ValueService $valueService;
     */
    private ValueService $valueService;

    /**
     * @param ValueService $valueService
     */
    public function __construct(ValueService $valueService)
    {
        $this->valueService = $valueService;
    }//end __construct()

    /**
     * Defines the events that the subscriber should subscribe to.
     *
     * @return array The subscribed events
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::preUpdate,
            Events::prePersist,
            Events::preRemove,
        ];
    }//end getSubscribedEvents()

    /**
     * Adds object resources from identifier.
     *
     * @param LifecycleEventArgs $value The lifecycle event arguments for this event
     */
    public function preUpdate(LifecycleEventArgs $value): void
    {
        $valueObject = $value->getObject();

        if ($valueObject instanceof Value && $valueObject->getAttribute()->getType() == 'object') {
            if ($valueObject->getArrayValue()) {
                foreach ($valueObject->getArrayValue() as $identifier) {
                    $subobject = $this->valueService->findSubobject($identifier, $valueObject);
                    if ($subobject !== null) {
                        $valueObject->addObject(new Coupler($subobject));
                    }
                }
                $valueObject->setArrayValue([]);
            } elseif ((Uuid::isValid($valueObject->getStringValue()) || filter_var($valueObject->getStringValue(), FILTER_VALIDATE_URL)) && $identifier = $valueObject->getStringValue()) {
                foreach ($valueObject->getObjects() as $object) {
                    $valueObject->removeObject($object);
                }
                $subobject = $this->valueService->findSubobject($identifier, $valueObject);

                if ($subobject !== null) {
                    $valueObject->addObject(new Coupler($subobject));
                }
            }
            $this->valueService->inverseRelation($valueObject);

            $valueObject->getObjectEntity()->setDateModified(new \DateTime());
        }
    }//end preUpdate()

    /**
     * Passes the result of prePersist to preUpdate.
     *
     * @param LifecycleEventArgs $args The lifecycle event arguments for this prePersist
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $this->preUpdate($args);
    }//end prePersist()

    public function preRemove(LifecycleEventArgs $value): void
    {
        $valueObject = $value->getObject();

        if ($valueObject instanceof Value === true
            && $valueObject->getAttribute()->getType() === 'object'
            && $valueObject->getAttribute()->getInversedBy() !== null
        ) {

            foreach ($valueObject->getObjects() as $coupler) {
                $this->valueService->removeInverses($coupler, $valueObject);
            }
        }
    }//end preRemove()
}//end class
