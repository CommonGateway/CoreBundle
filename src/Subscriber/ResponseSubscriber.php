<?php

namespace CommonGateway\CoreBundle\Subscriber;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ResponseSubscriber implements EventSubscriberInterface
{
    /**
     * @var SessionInterface
     */
    private SessionInterface $session;

    /**
     * @param RequestStack           $requestStack  The current request stack.
     */
    public function __construct(
        RequestStack $requestStack
    )
    {
        $this->session       = $requestStack->getSession();

    }//end __construct()

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['request'],
        ];

    }//end getSubscribedEvents()

    /**
     * @param ResponseEvent $event The Responce Event
     */
    public function request(ResponseEvent $event)
    {
        $response = $event->getResponse();

        // Set multiple headers simultaneously
        $response->headers->add(
            [
                'Access-Control-Allow-Credentials' => 'true',
                'Process-ID'                       => $this->session->get('process'),
            ]
        );

        $response->headers->remove('Access-Control-Allow-Origin');

    }//end request()
}//end class
