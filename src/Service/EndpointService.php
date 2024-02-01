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
use ValueError;

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
     * The constructor sets al needed variables.
     *
     * @param EntityManagerInterface   $entityManager   The enitymanger
     * @param SerializerInterface      $serializer      The serializer
     * @param RequestService           $requestService  The request service
     * @param EventDispatcherInterface $eventDispatcher The event dispatcher
     * @param SessionInterface         $session         The current session
     * @param LoggerInterface          $endpointLogger  The endpoint logger.
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
     *
     * @throws Exception
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

        // Logs the request headers.
        $this->logRequestHeaders($request, $endpoint);

        if (json_decode($request->get('payload'), true)) {
            $parameters['payload'] = json_decode($request->get('payload'), true);
        }

        // If we have an proxy we will handle just that.
        if (empty($endpoint->getProxy()) === false) {
            $this->logger->info('Handling proxied endpoint');
            $parameters['response'] = $this->requestService->proxyHandler($parameters, []);
        }

        // If we have shema's let's handle those.
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
     * This function logs the headers of the request and uses the endpoint->getLoggingConfig()['headers'] to unset the headers that don't need to be logged.
     *
     * @return void
     */
    public function logRequestHeaders(Request $request, Endpoint $endpoint): void
    {
        // Get all headers from the request.
        $headers = [];
        foreach ($request->headers->all() as $key => $value) {
            $headers[$key] = $request->headers->get($key);
        }

        if (key_exists('headers', $endpoint->getLoggingConfig()) === true) {
            // Loop through the loggingConfig headers of the current endpoint.
            foreach ($endpoint->getLoggingConfig()['headers'] as $logConfig) {
                // If the header is set on the request headers, then unset the key so we don't log it.
                if (key_exists($logConfig, $headers) === true) {
                    unset($headers[$logConfig]);
                }
            }
        }

        // Log the headers of the request without the headers from the loggingConfig of the endpoint.
        $this->logger->info('The headers from the request for endpoint '.$endpoint->getName(), ['headers' => $headers]);

    }//end logRequestHeaders()

    /**
     * This function return the correct file extension for decode/encode purposes from the accept header.
     *
     * @param string $acceptHeader.
     *
     * @return string|null Accept type.
     */
    private function determineAcceptType(string $acceptHeader): ?string
    {
        // Determine the accept type.
        $this->logger->debug('Determine accept type from accept header');
        switch ($acceptHeader) {
        case 'application/pdf':
            return 'pdf';
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
        case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
            return 'xlsx';
        case 'text/csv':
            return 'csv';
        case 'text/html':
            return 'html';
        case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
            return 'docx';
                break;
        case 'application/json+aggregations':
            return 'aggregations';
        }//end switch

        return null;

    }//end determineAcceptType()

    /**
     * Gets the accept type based on the request.
     *
     * This method breaks complexity rules but since a switch is the most efficent and performent way to do this we made a design decicion to allow it
     *
     * @return string The accept type
     */
    public function getAcceptType(?Request $request = null): string
    {
        if ($request === null) {
            $request = $this->request;
        }

        // Let's first look at the accept header.
        $acceptHeader = $request->headers->get('accept');

        // If the accept header does not provide useful info, check if the endpoint contains a pointer.
        $this->logger->debug('Get Accept header');
        if (($acceptHeader === null || $acceptHeader === '*/*') && $this->endpoint !== null && $this->endpoint->getDefaultContentType() !== null) {
            $acceptHeader = $this->endpoint->getDefaultContentType();
        }//end if

        // Get an accept type when multiple accept values are given.
        if (strpos($acceptHeader, ',') !== false) {
            $acceptHeaders = explode(',', $acceptHeader);
            foreach ($acceptHeaders as $acceptHeader) {
                $determinedAcceptType = $this->determineAcceptType($acceptHeader);
                if ($determinedAcceptType !== null) {
                    return $determinedAcceptType;
                }
            }
        }

        // Get the accept type when a single accept type is given.
        $determinedAcceptType = $this->determineAcceptType($acceptHeader);

        if ($determinedAcceptType !== null) {
            return $determinedAcceptType;
        }

        // As a backup we look at any file extenstion.
        $this->logger->debug('Determine accept type from path extension');
        $path      = $request->getPathInfo();
        $pathparts = explode('.', $path);
        if (count($pathparts) >= 2) {
            $extension = end($pathparts);
            switch ($extension) {
            case 'pdf':
                return 'pdf';
            }//end switch
        }

        // If we endup we cant detirmine what kind of accept we need so let's throw an error.
        $this->logger->error('No proper accept could be determined');

        throw new BadRequestHttpException('No proper accept could be determined');

    }//end getAcceptType()

    /**
     * Decodes the body of the request based upon the content-type header, accept header or endpoint default.
     *
     * @return array
     */
    public function decodeBody(?Request $request = null): ?array
    {
        if ($request === null) {
            $request = $this->request;
        }

        if (empty($request->getContent()) === true) {
            return [];
        }

        // Get the content type.
        $this->logger->info('Decoding body');
        $contentType = $request->getContentType();
        if ($contentType === null) {
            $contentType = $request->headers->get('Accept');
        }//end if

        // Decode the body.
        switch ($contentType) {
        case 'text/xml':
        case 'application/xml':
        case 'xml':
            $xmlEncoder = new XmlEncoder();

            return $xmlEncoder->decode($request->getContent(), 'xml');
        default:
            return json_decode($request->getContent(), true);
        }//end switch

    }//end decodeBody()

    /**
     * Gets the endpoint based on the request.
     *
     * @return Endpoint The found endpoint
     * @throws Exception
     */
    public function getEndpoint(): Endpoint
    {
        $path = $this->request->getPathInfo();

        // The third parameters ensures that /prefix/api/a/api/b will become ['/prefix', 'a/api/b'].
        // See https://www.php.net/manual/en/function.explode.php for more information.
        $path     = explode('/api/', $path, 2)[1];
        $endpoint = $this->entityManager->getRepository('App:Endpoint')->findByMethodRegex($this->request->getMethod(), $path);

        if ($endpoint !== null) {
            return $endpoint;
        }//end if

        throw new NotFoundHttpException('No proper endpoint could be determined');

    }//end getEndpoint()

    /**
     * Parse headers from multipart/form-data requests on put, as PHP does not parse them itself
     *
     * @param string $rawHeaders The raw headers.
     *
     * @return array The parsed headers.
     */
    private function parsePutHeaders(string $rawHeaders): array
    {
        $rawHeaders = explode("\r\n", $rawHeaders);
        $headers    = [];
        foreach ($rawHeaders as $header) {
            list($name, $value)         = explode(':', $header);
            $headers[strtolower($name)] = ltrim($value, ' ');
        }

        return $headers;

    }//end parsePutHeaders()

    /**
     * Retrieves data from input on PUT requests where multipart/form-data is not natively supported by PHP
     *
     * @return array The data from the PUT request parsed into an array
     *
     * @throws \Safe\Exceptions\FilesystemException
     */
    private function getPutData(): array
    {
        // Fetch content and determine boundary.
        $raw_data = \Safe\file_get_contents('php://input');
        $boundary = substr($raw_data, 0, strpos($raw_data, "\r\n"));

        // Fetch each part.
        $parts = array_slice(explode($boundary, $raw_data), 1);
        $data  = [];

        foreach ($parts as $part) {
            // If this is the last part, break.
            if ($part === "--\r\n") {
                break;
            }

            // Separate content from headers.
            $part                    = ltrim($part, "\r\n");
            list($rawHeaders, $body) = explode("\r\n\r\n", $part, 2);

            // Parse the headers list
            $headers = $this->parsePutHeaders($rawHeaders);

            // Parse the Content-Disposition to get the field name, etc.
            if (isset($headers['content-disposition']) === false) {
                continue;
            }

            $filename = null;
            if (preg_match(
                '/^(.+); *name="([^"]+)"(; *filename="([^"]+)")?/',
                $headers['content-disposition'],
                $matches
            ) === false
            ) {
                preg_match(
                    '/^(.+); *name=([-+.\w]+)(; *filename=([-+.\w]+))?/',
                    $headers['content-disposition'],
                    $matches
                );
            }

            list(, $type, $name)             = $matches;
            isset($matches[4]) and $filename = $matches[4];

            // Handle your fields here.
            switch ($name) {
                // This is a file upload.
            case 'userfile':
                file_put_contents($filename, $body);
                break;

                // Default for all other files is to populate $data.
            default:
                $data[$name] = substr($body, 0, (strlen($body) - 2));
                break;
            }
        }//end foreach

        return $data;

    }//end getPutData()

    /**
     * Builds a parameter array from the request.
     *
     * @param ?array $parameters An optional starting array of parameters
     *
     * @return array The parameter array
     */
    private function getParametersFromRequest(?array $parameters = []): array
    {
        // Let's make sure that we always have a path.
        $this->logger->debug('Get the raw path');
        $parameters['pathRaw'] = $this->request->getPathInfo();

        $this->logger->debug('Split the path into an array');

        $path = $this->endpoint->getPath();
        if ($this->endpoint->getProxy() !== null && in_array('{route}', $path) === true) {
            $parameters['path'] = $this->getProxyPath($parameters);
        } else {
            $parameters['path'] = $this->getNormalPath($parameters);
        }

        $this->logger->debug('Get the query string');
        $parameters['querystring'] = $this->request->getQueryString();

        $this->logger->debug('Get general request information');
        $parameters['method'] = $this->request->getMethod();
        $parameters['query']  = $this->request->query->all();

        try {
            $parameters['body'] = $this->request->toArray();
        } catch (Exception $exception) {
            if (in_array(strtoupper($parameters['method']), ['GET', 'DELETE']) === false) {
                $this->logger->warning('The request does not have a body, this might result in undefined behaviour');
            }

            // In a lot of conditions (basically any illegal post) this will return an error. But we want an empty array instead.
        }

        $parameters['crude_body'] = $this->request->getContent();

        // Let's get all the headers.
        $parameters['headers'] = $this->request->headers->all();

        // Let's get all the post variables.
        $parameters['post'] = $this->request->request->all();

        if ($parameters['method'] === 'PUT' && $parameters['post'] === [] && $parameters['body'] === []) {
            $parameters['post'] = $this->getPutData();
        }

        return $parameters;

    }//end getParametersFromRequest()

    /**
     * Gets and returns the correct path array for a normal endpoint.
     *
     * @param array $parameters An array of parameters containing at least the key pathRaw.
     *
     * @return array The path array for a normal endpoint.
     */
    private function getNormalPath(array $parameters): array
    {
        $path    = $this->endpoint->getPath();
        $pathRaw = $this->request->getPathInfo();

        // The third parameters ensures that /prefix/api/a/api/b will become ['/prefix', 'a/api/b'].
        // See https://www.php.net/manual/en/function.explode.php for more information.
        $pathRaw = explode('/api/', $pathRaw, 2)[1];

        try {
            $combinedArray = array_combine($path, explode('/', $pathRaw));
        } catch (ValueError $exception) {
            $this->logger->error('EndpointService->getNormalPath(): $exception');

            // Todo: When an id is not given the last element of the path array should be removed to ensure the arrays are of the same length.
            array_pop($path);
            $combinedArray = array_combine($path, explode('/', $pathRaw));
        }

        if ($combinedArray === false) {
            // Todo: When an id is not given the last element of the path array should be removed to ensure the arrays are of the same length.
            array_pop($path);
            $combinedArray = array_combine($path, explode('/', $pathRaw));
        }

        if ($combinedArray === false) {
            $this->logger->error('EndpointService->getNormalPath(): Failed to construct the parameters path array for the current endpoint.');

            $combinedArray = [];
        }

        return $combinedArray;

    }//end getNormalPath()

    /**
     * Gets and returns the correct path array for a proxy endpoint.
     *
     * @param array $parameters An array of parameters containing at least the key pathRaw.
     *
     * @return array The path array for a proxy endpoint.
     */
    private function getProxyPath(array $parameters): array
    {
        $path    = $this->endpoint->getPath();
        $pathRaw = $parameters['pathRaw'];

        // Use Path to create a regex and get endpoint for the proxy from the pathRaw.
        $regex        = str_replace('{route}', '([^.*]*)', "/\/api\/".implode('\/', $path).'/');
        $matchesCount = preg_match($regex, $pathRaw, $matches);

        if ($matchesCount != 1) {
            $this->logger->warning('EndpointService->getProxyPath(): Failed to find correct proxy endpoint in pathRaw string, trying to get normal endpoint path instead...');

            return $this->getNormalPath($parameters);
        }

        $endpoint = $matches[1];

        // The third parameters ensures that /prefix/api/a/api/b will become ['/prefix', 'a/api/b'].
        // See https://www.php.net/manual/en/function.explode.php for more information.
        $pathRaw         = $pathRaw = explode('/api/', $pathRaw, 2)[1];
        $pathRaw         = str_replace("/$endpoint", '', $pathRaw);
        $explodedPathRaw = explode('/', $pathRaw);

        // Add endpoint for proxy to $explodedPathRaw.
        $explodedPathRaw[] = $endpoint;

        return array_combine($path, $explodedPathRaw);

    }//end getProxyPath()
}//end class
