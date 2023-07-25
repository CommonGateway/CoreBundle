<?php

namespace CommonGateway\CoreBundle\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\Gateway as Source;
use CommonGateway\CoreBundle\Service\CallService;
use CommonGateway\CoreBundle\Service\RequestService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use Safe\Exceptions\UrlException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;

class ProxySubscriber implements EventSubscriberInterface
{

    /**
     * @var EntityManagerInterface The entity manager
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var CallService The call service
     */
    private CallService $callService;

    /**
     * @var RequestService The request service
     */
    private RequestService $requestService;

    /**
     * @var SerializerInterface The serializer
     */
    private SerializerInterface $serializer;

    public const PROXY_ROUTES = [
        'api_gateways_get_proxy_item',
        'api_gateways_get_proxy_endpoint_item',
        'api_gateways_post_proxy_collection',
        'api_gateways_post_proxy_endpoint_collection',
        'api_gateways_put_proxy_single_item',
        'api_gateways_delete_proxy_single_item',
    ];

    /**
     * The constructor of this subscriber class.
     *
     * @param EntityManagerInterface $entityManager  The entity manager
     * @param CallService            $callService    The call service
     * @param RequestService         $requestService The request service
     * @param SerializerInterface    $serializer     The serializer
     */
    public function __construct(EntityManagerInterface $entityManager, CallService $callService, RequestService $requestService, SerializerInterface $serializer)
    {
        $this->entityManager  = $entityManager;
        $this->callService    = $callService;
        $this->requestService = $requestService;
        $this->serializer     = $serializer;

    }//end __construct()

    /**
     * Get Subscribed Events.
     *
     * @return array[] Subscribed Events
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => [
                'proxy',
                EventPriorities::PRE_DESERIALIZE,
            ],
        ];

    }//end getSubscribedEvents()

    /**
     * Handle Proxy.
     *
     * @param RequestEvent $event The Event
     *
     * @throws GuzzleException|UrlException
     *
     * @return void
     */
    public function proxy(RequestEvent $event): void
    {
        $route = $event->getRequest()->attributes->get('_route');

        if (!in_array($route, self::PROXY_ROUTES)) {
            return;
        }

        $source = $this->entityManager->getRepository('App:Gateway')->find($event->getRequest()->attributes->get('id'));
        if (!$source instanceof Source) {
            return;
        }

        $headers = array_merge_recursive($source->getHeaders(), $event->getRequest()->headers->all());

        $endpoint = '';
        if (isset($headers['x-endpoint'][0]) === true) {
            $endpoint = trim($headers['x-endpoint'][0], '/');
        }

        $data['path']['{route}'] = $endpoint;

        $data['method'] = ($headers['x-method'][0] ?? $event->getRequest()->getMethod());
        unset($headers['x-endpoint']);
        unset($headers['x-method']);

        $data['headers']     = $headers;
        $data['querystring'] = $event->getRequest()->getQueryString();
        $data['crude_body']  = $event->getRequest()->getContent();

        $event->setResponse($this->requestService->proxyHandler($data, [], $source));

    }//end proxy()
}//end class
