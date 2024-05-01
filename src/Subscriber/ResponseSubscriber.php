<?php

namespace CommonGateway\CoreBundle\Subscriber;

use App\Entity\Application;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ResponseSubscriber implements EventSubscriberInterface
{

    private LoggerInterface $logger;

    /**
     * @param EntityManagerInterface $entityManager The entity manager
     * @param SessionInterface       $session       The sesion interface
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SessionInterface $session,
        private readonly ParameterBagInterface $parameterBag,
        LoggerInterface $applicationLogger

    )
    {
//        $this->entityManager = $entityManager;
//        $this->session       = $session;
        $this->logger = $applicationLogger;

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

        if($this->session->has('application')) {
            $application = $this->entityManager->getRepository(Application::class)->find($this->session->get('application'));

            $origin = $event->getRequest()->headers->get('origin');

            $allowedOrigins = array_merge($application->getOrigins(), $this->parameterBag->get('cors_origins'));
            if(in_array(needle: $origin, haystack: $allowedOrigins) || $application->getOrigins() === []) {
                if($application->getOrigins() === []) {
                    $this->logger->warning('Deprecated: No origins set for application, in the future, this will result in CORS blocking');
                }
                $response->headers->set('Access-Control-Allow-Origin', $origin);
            }
        }

    }//end request()
}//end class
