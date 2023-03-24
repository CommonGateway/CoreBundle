<?php

// src/Subscriber/DatabaseActivitySubscriber.php
namespace CommonGateway\CoreBundle\Subscriber;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class ObjectUriSubscriber implements EventSubscriberInterface
{

    private ParameterBagInterface $parameterBag;

    private SessionInterface $session;


    public function __construct(
        ParameterBagInterface $parameterBag,
        SessionInterface $session
    ) {
        $this->parameterBag = $parameterBag;
        $this->session      = $session;

    }//end __construct()


    // this method can only return the event names; you cannot define a
    // custom method name to execute when each event triggers
    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
        ];

    }//end getSubscribedEvents()


    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->postPersist($args);

    }//end postUpdate()


    /**
     * Updates the chache whenever an object is put into the database.
     *
     * @param LifecycleEventArgs $args
     *
     * @return void
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        // if this subscriber only applies to certain entity types,
        if ($object instanceof ObjectEntity) {
            if ($object->getUri() === null || str_contains($object->getUri(), $object->getSelf()) === false) {
                $object->setUri(rtrim($this->parameterBag->get('app_url'), '/').$object->getSelf());
            }

            return;
        }

    }//end postPersist()


}//end class
