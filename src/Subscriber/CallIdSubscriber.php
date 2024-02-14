<?php

namespace CommonGateway\CoreBundle\Subscriber;

use ApiPlatform\Symfony\EventListener\EventPriorities;
use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CallIdSubscriber implements EventSubscriberInterface
{

    private SessionInterface $session;

    public function __construct(RequestStack $requestStack)
    {

        try {
            $this->session = $requestStack->getSession();
        } catch (SessionNotFoundException $exception) {
            $this->session = new Session();
        }

    }//end __construct()

    // this method can only return the event names; you cannot define a
    // custom method name to execute when each event triggers
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => [
                'OnFirstEvent',
                EventPriorities::PRE_DESERIALIZE,
            ],
        ];

    }//end getSubscribedEvents()

    public function OnFirstEvent(RequestEvent $event)
    {
        $this->session->set('callId', Uuid::uuid4()->toString());

    }//end OnFirstEvent()
}//end class
