<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Endpoint;
use App\Event\ActionEvent;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * This service handles calls on the ZZ endpoint (or in other words abstract routing).
 *
 * @Author Ruben van der Linde <ruben@conduction.nl>, Robert Zondervan <robert@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class EndpointService
{

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var Request
     */
    private Request $request;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var RequestService
     */
    private RequestService $requestService;

    /**
     * @var EventDispatcherInterface
     */
    private EventDispatcherInterface $eventDispatcher;

    /**
     * @var SessionInterface
     */
    private SessionInterface $session;

    private LoggerInterface $logger;

    /**
     * @var Endpoint|null
     */
    private ?Endpoint $endpoint = null;

    /**
     * @param EntityManagerInterface   $entityManager   The enitymanger
     * @param SerializerInterface      $serializer      The serializer
     * @param RequestService           $requestService  The request service
     * @param EventDispatcherInterface $eventDispatcher The event dispatcher
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        RequestService $requestService,
        EventDispatcherInterface $eventDispatcher,
        SessionInterface $session,
        LoggerInterface $endpointLogger
    ) {
        $this->entityManager   = $entityManager;
        $this->serializer      = $serializer;
        $this->requestService  = $requestService;
        $this->eventDispatcher = $eventDispatcher;
        $this->session         = $session;
        $this->logger          = $endpointLogger;

    }//end __construct()

    /**
     * Handle the request afther it commes in through the ZZ controller.
     *
     * @param Request $request The inbound request
     *
     * @return Response
     */
    public function handleRequest(Request $request): Response
    {
        // Set the request globally.
        $this->request = $request;

        // Get the Endpoint.
        $this->endpoint = $endpoint = $this->getEndpoint();
        $this->session->set('endpoint', $endpoint->getId()->toString());
        $this->logger->info('Handling request to endpoint '.$endpoint->getName());

        // Get the accept type.
        $this->logger->debug('Determine accept type');
        $accept = $this->getAcceptType();

        // Get the parameters.
        $this->logger->debug('Determine parameters for request');
        $parameters             = $this->getParametersFromRequest();
        $parameters['endpoint'] = $endpoint;
        $parameters['accept']   = $accept;
        $parameters['body']     = $this->decodeBody();

        if (json_decode($request->get('payload'), true)) {
            $parameters['payload'] = json_decode($request->get('payload'), true);
        }

        // If we have an proxy we will handle just that.
        if (empty($endpoint->getProxy()) === false) {
            $this->logger->info('Handling proxied endpoint');

            $parameters['response'] = $this->requestService->proxyHandler($parameters, []);
        }

        // If we have shema's lets handle those.
        if (count($endpoint->getEntities()) > 0) {
            $this->logger->info('Handling entity endpoint');

            $parameters['response'] = $this->requestService->requestHandler($parameters, []);
        }

        // Last but not least we check for throw.
        if (count($endpoint->getThrows()) > 0) {
            $this->logger->info('Handling event endpoint');

            if (isset($parameters['response']) === false) {
                $parameters['response'] = new Response('Object is not supported by this endpoint', '501', ['Content-type' => $endpoint->getDefaultContentType()]);
            }

            foreach ($endpoint->getThrows() as $throw) {
                $event = new ActionEvent('commongateway.action.event', $parameters, $throw);
                $this->eventDispatcher->dispatch($event, 'commongateway.action.event');
                $parameters['response'] = $event->getData()['response'];
            }

            $parameters['response'] = $event->getData()['response'];
        }

        if (isset($parameters['response']) === true) {
            return $parameters['response'];
        }

        $this->logger->error('No proxy, schema or events could be established for this endpoint');

        throw new Exception('No proxy, schema or events could be established for this endpoint');

    }//end handleRequest()

    /**
     * Gets the accept type based on the request.
     *
     * This method breaks complexity rules but since a switch is the most efficent and performent way to do this we made a design decicion to allow it
     *
     * @return string The accept type
     */
    public function getAcceptType(): string
    {
        // Lets first look at the accept header.
        $acceptHeader = $this->request->headers->get('accept');

        // If the accept header does not provide useful info, check if the endpoint contains a pointer.
        $this->logger->debug('Get Accept header');
        if (($acceptHeader === null || $acceptHeader === '*/*') && $this->endpoint !== null && $this->endpoint->getDefaultContentType() !== null) {
            $acceptHeader = $this->endpoint->getDefaultContentType();
        }//end if

        // Determine the accept type.
        $this->logger->debug('Determine accept type from accept header');
        switch ($acceptHeader) {
        case 'application/json':
            return 'json';
        case 'application/json+hal':
        case 'application/hal+json':
            return 'jsonhal';
        case 'application/json+ld':
        case 'application/ld+json':
            return 'jsonld';
        case 'application/json+fromio':
        case 'application/formio+json':
            return 'formio';
        case 'application/json+schema':
        case 'application/schema+json':
            return 'schema';
        case 'application/json+graphql':
        case 'application/graphql+json':
            return 'graphql';
        case 'text/xml':
        case 'application/xml':
            return 'xml';
        }//end switch

        // As a backup we look at any file extenstion.
        $this->logger->debug('Determine accept type from path extension');
        $path      = $this->request->getPathInfo();
        $pathparts = explode('.', $path);
        if (count($pathparts) >= 2) {
            $extension = end($pathparts);
            switch ($extension) {
            case 'pdf':
                return 'pdf';
            }//end switch
        }

        // If we endup we cant detirmine what kind of accept we need so lets throw an error.
        $this->logger->error('No proper accept could be determined');

        throw new BadRequestHttpException('No proper accept could be determined');

    }//end getAcceptType()

    /**
     * Decodes the body of the request based upon the content-type header, accept header or endpoint default.
     *
     * @return array
     */
    public function decodeBody(): ?array
    {
        if (empty($this->request->getContent()) === true) {
            return [];
        }

        // Get the content type.
        $this->logger->info('Decoding body');
        $contentType = $this->request->getContentType();
        if ($contentType === null) {
            $contentType = $this->request->headers->get('Accept');
        }//end if

        // Decode the body.
        switch ($contentType) {
        case 'text/xml':
        case 'application/xml':
        case 'xml':
            $xmlEncoder = new XmlEncoder();

            return $xmlEncoder->decode($this->request->getContent(), 'xml');
        default:
            return json_decode($this->request->getContent(), true);
        }//end switch

    }//end decodeBody()

    /**
     * Gets the endpoint based on the request.
     *
     * @throws Exception
     *
     * @return Endpoint The found endpoint
     */
    public function getEndpoint(): Endpoint
    {
        $path     = $this->request->getPathInfo();
        $path     = substr($path, 5);
        $endpoint = $this->entityManager->getRepository('App:Endpoint')->findByMethodRegex($this->request->getMethod(), $path);

        if ($endpoint !== null) {
            return $endpoint;
        }//end if

        throw new NotFoundHttpException('No proper endpoint could be determined');

    }//end getEndpoint()

    /**
     * Builds a parameter array from the request.
     *
     * @param ?array $parameters An optional starting array of parameters
     *
     * @return array The parameter arrau
     */
    private function getParametersFromRequest(?array $parameters = []): array
    {
        // Lets make sure that we always have a path.
        $this->logger->debug('Get the raw path');
        $parameters['pathRaw'] = $this->request->getPathInfo();

        $this->logger->debug('Split the path into an array');

        try {
            $parameters['path'] = array_combine($this->endpoint->getPath(), explode('/', str_replace('/api/', '', $parameters['pathRaw'])));
        } catch (Exception $exception) {
            $path = $this->endpoint->getPath();
            array_pop($path);
            $parameters['path'] = array_combine($path, explode('/', str_replace('/api/', '', $parameters['pathRaw'])));
        }

        $this->logger->debug('Get the query string');
        $parameters['querystring'] = $this->request->getQueryString();

        try {
            $parameters['body'] = $this->request->toArray();
        } catch (Exception $exception) {
            $this->logger->warning('The request does not have a body, this might result in undefined behaviour');
            // In a lot of condtions (basically any illigal post) this will return an error. But we want an empty array instead.
        }

        $parameters['crude_body'] = $this->request->getContent();

        $this->logger->debug('Get general request information');
        $parameters['method'] = $this->request->getMethod();
        $parameters['query']  = $this->request->query->all();

        // Lets get all the headers.
        $parameters['headers'] = $this->request->headers->all();

        // Lets get all the post variables.
        $parameters['post'] = $this->request->request->all();

        return $parameters;

    }//end getParametersFromRequest()
}//end class
