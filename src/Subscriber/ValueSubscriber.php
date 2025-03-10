<?php

namespace CommonGateway\CoreBundle\Subscriber;

use App\Entity\ObjectEntity;
use App\Entity\Synchronization;
use App\Entity\Value;
use CommonGateway\CoreBundle\Service\ValueService;
use CommonGateway\CoreBundle\Message\ValueMessage;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class ValueSubscriber implements EventSubscriberInterface
{

    /**
     * @var MessageBusInterface The message bus.
     */
    private MessageBusInterface $messageBus;

    /**
     * @var SessionInterface $session The current session.
     */
    private SessionInterface $session;

    /**
     * @var LoggerInterface The logger.
     */
    private LoggerInterface $logger;

    /**
     * @var ValueService The ValueService.
     */
    private ValueService $valueService;

    /**
     * @param MessageBusInterface $messageBus   The message bus.
     * @param SessionInterface    $session      The current session.
     * @param LoggerInterface     $objectLogger The logger.
     * @param ValueService        $valueService The ValueService.
     */
    public function __construct(
        MessageBusInterface $messageBus,
        SessionInterface $session,
        LoggerInterface $objectLogger,
        ValueService $valueService
    ) {
        $this->messageBus   = $messageBus;
        $this->session      = $session;
        $this->logger       = $objectLogger;
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

        if ($valueObject instanceof Value === true
            && $valueObject->getAttribute()->getType() === 'object'
            && ($valueObject->getArrayValue() !== []
            || $valueObject->getStringValue() !== null && Uuid::isValid($valueObject->getStringValue()) === true
            || $valueObject->getStringValue() !== null && filter_var($valueObject->getStringValue(), FILTER_VALIDATE_URL) !== false)
        ) {
            try {
                $userId = null;
                if (Uuid::isValid($this->session->get('user', "")) === true) {
                    $userId = Uuid::fromString($this->session->get('user'));
                }

                $this->messageBus->dispatch(new ValueMessage($value->getObject()->getId(), $userId, $this->session->get('application')));
            } catch (\Exception $exception) {
                $this->logger->error("Error when trying to create a ValueMessage for Value {$value->getObject()->getId()}: ".$exception->getMessage());
            }
        }//end if

    }//end postUpdate()

    /**
     * Passes the result of prePersist to preUpdate.
     *
     * @param LifecycleEventArgs $args The lifecycle event arguments for this prePersist
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->postUpdate($args);

    }//end postPersist()

    public function preRemove(LifecycleEventArgs $args): void
    {
        $valueObject = $args->getObject();

        if ($valueObject instanceof Value === true) {
            $this->valueService->cascadeDeleteSubObjects($valueObject);
        }

    }//end preRemove()
}//end class
