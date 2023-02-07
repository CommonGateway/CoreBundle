<?php

namespace CommonGateway\CoreBundle\Service;

use App\Event\ActionEvent;
use App\Service\RequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Responce;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * This service handles calls on the ZZ endpoint (or in other words abstract routing).
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
     * @param EntityManagerInterface $entityManager  The enitymanger
     * @param Request                $request        The request
     * @param SerializerInterface    $serializer     The serializer
     * @param RequestService         $requestService The request service
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        Request $request,
        SerializerInterface $serializer,
        RequestService $requestService
    ) {
        $this->entityManager = $entityManager;
        $this->request = $request;
        $this->serializer = $serializer;
        $this->requestService = $requestService;
    }//end __construct()

    /**
     * Handle the request afther it commes in trough the ZZ controller.
     *
     * @return Responce
     */
    public function handleRequest(): Responce
    {

        // Get the  and path parts.
        $path = $this->request->getPathInfo();
        $pathParts = explode('/', $path);

        // Get the accept type.
        $accept = $this->getAcceptType();

        // Get the Endpoint.
        $endpoint = $this->getEndpoint();

        // Get the parameters.
        $parameters = $this->getParametersFromRequest();
        $parameters['endpoint'] = $endpoint;
        $parameters['accept'] = $accept;

        // If we have an proxy we will handle just that.
        if ($endpoint->getProxy() === true) {
            return $this->requestService->proxyHandler($parameters, []);
        }

        // If we have shema's lets handle those.
        if (count($endpoint->getEntities()) > 0) {
            return $this->requestService->requestHandler($parameters, []);
        }

        // Last but not least we check for throw.
        if (count($endpoint->getThrows()) > 0) {
            $parameters['response'] = new Response('Object is not supported by this endpoint', '200');
            foreach ($endpoint->getThrows() as $throw) {
                $event = new ActionEvent('commongateway.action.event', $parameters, $throw);
                $this->eventDispatcher->dispatchdispatch($event, 'commongateway.action.event');
            }

            return $parameters['response'];
        }

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

        switch ($acceptHeader) {
            case 'application/json':
                return 'json';
            case 'application/json+hal':
            case 'application/hal+sjon':
                return 'jsonhal';
            case 'application/json+ls':
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
        }//end switch

        // As a backup we look at any file extenstion.
        $path = $this->request->getPathInfo();
        $pathparts = explode('.', $path);
        if (count($pathparts) >= 2) {
            $extension = end($pathparts);
            switch ($extension) {
                case 'pdf':
                    return 'pdf';
            }//end switch
        }

        // If we endup we cant detirmine what kind of accept we need so lets throw an error.
        throw new Exception('No proper accept could be detirmend');
    }//end getAcceptType()

    /**
     * Gets the endpoint based on the request.
     *
     * @return Endpoint The found endpoint
     */
    public function getEndpoint(): Endpoint
    {
        $endpoint = $this->getDoctrine()->getRepository('App:Endpoint')->findByMethodRegex($this->request->getMethod(), $this->request->getPathInfo());
        if ($endpoint === true) {
            return $endpoint;
        }

        throw new Exception('No proper endpoint could be detirmend');
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
        if (isset($parameters['path']) === false) {
            $parameters['path'] = $this->request->getPathInfo();
        }

        $parameters['querystring'] = $this->request->getQueryString();

        try {
            $parameters['body'] = $this->request->toArray();
        } catch (\Exception $exception) {
            // In a lot of condtions (basically any illigal post) this will return an error. But we want an empty array instead.
        }

        $parameters['crude_body'] = $this->request->getContent();

        $parameters['method'] = $this->request->getMethod();
        $parameters['query'] = $this->request->query->all();

        // Lets get all the headers.
        $parameters['headers'] = $this->request->headers->all();

        // Lets get all the post variables.
        $parameters['post'] = $this->request->request->all();

        return $parameters;
    }//end getParametersFromRequest()
}//end class
