<?php

namespace CommonGateway\CoreBundle\Subscriber;

use App\Entity\ObjectEntity;
use App\Entity\Synchronization;
use App\Entity\Value;
use App\Service\SynchronizationService;
use CommonGateway\CoreBundle\Message\ValueMessage;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class ValueSubscriber implements EventSubscriberInterface
{

    /**
     * @var MessageBusInterface The message bus
     */
    private MessageBusInterface $messageBus;


    /**
     * @param MessageBusInterface $messageBus
     */
    public function __construct(
        MessageBusInterface $messageBus)
    {
        $this->messageBus = $messageBus;

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
        ];

    }//end getSubscribedEvents()

    /**
     * Adds object resources from identifier.
     *
     * @param LifecycleEventArgs $value The lifecycle event arguments for this event
     */
    public function postUpdate(LifecycleEventArgs $value): void
    {
        $valueObject = $value->getObject();

        if ($valueObject instanceof Value
            && $valueObject->getAttribute()->getType() == 'object'
            && ($valueObject->getArrayValue()
            || Uuid::isValid($valueObject->getStringValue())
            || filter_var($valueObject->getStringValue(), FILTER_VALIDATE_URL))
        ) {
            $this->messageBus->dispatch(new ValueMessage($value->getObject()->getId()));
        }//end if
    }//end preUpdate()

    /**
     * Passes the result of prePersist to preUpdate.
     *
     * @param LifecycleEventArgs $args The lifecycle event arguments for this prePersist
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->postUpdate($args);

    }//end prePersist()

    public function preRemove(LifecycleEventArgs $args): void
    {

    }//end preRemove()
}//end class
