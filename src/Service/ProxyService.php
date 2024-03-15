<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Endpoint;
use App\Entity\Gateway as Source;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Twig\Error\LoaderError;

class ProxyService
{

    /**
     * @var Source|null The source that we are current working with.
     */
    private ?Source $source = null;

    /**
     * @param CallService            $callService    The call service.
     * @param MappingService         $mappingService The mapping service.
     * @param EntityManagerInterface $entityManager  The entity manager.
     * @param LoggerInterface        $callLogger     The logger for call related logs.
     */
    public function __construct(
        private readonly CallService $callService,
        private readonly MappingService $mappingService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $callLogger,
        private readonly SerializerInterface $serializer,
        private readonly RequestService $requestService,
    ) {

    }//end __construct()

    /**
     * Handles endpointConfig for a specific endpoint on a source and a specific configuration key like: 'query' or 'headers'.
     * Before we do an api-call.
     *
     * @param array  $config            The configuration for an api-call we might want to change.
     * @param array  $endpointConfigOut The endpointConfig 'out' of a specific endpoint and source.
     * @param string $configKey         The specific configuration key to check if its data needs to be changed and if so, change the data for.
     *
     * @return array The configuration array.
     */
    private function handleEndpointConfigOut(array $config, array $endpointConfigOut, string $configKey): array
    {

        $this->callLogger->info(message: 'Handling outgoing configuration for endpoint');

        if (array_key_exists(key: $configKey, array: $config) === false || array_key_exists(key: $configKey, array: $endpointConfigOut) === false) {
            return $config;
        }

        if (array_key_exists(key: 'mapping', array: $endpointConfigOut[$configKey]) === false) {
            return $config;
        }

        $mapping = $this->entityManager->getRepository(className: Mapping::class)->findOneBy(criteria: ['reference' => $endpointConfigOut[$configKey]['mapping']]);
        if ($mapping === null) {
            $this->callLogger->error(message: "Could not find mapping with reference {$endpointConfigOut[$configKey]['mapping']} while handling $configKey EndpointConfigOut for a Source");

            return $config;
        }//end if

        if (is_string(value: $config[$configKey]) === true) {
            try {
                $body               = $this->mappingService->mapping(mappingObject: $mapping, input: \Safe\json_decode(json: $config[$configKey], assoc: true));
                $config[$configKey] = \Safe\json_encode(value: $body);
            } catch (Exception | LoaderError | SyntaxError $exception) {
                $this->callLogger->error(message: "Could not map with mapping {$endpointConfigOut[$configKey]['mapping']} while handling $configKey EndpointConfigOut for a Source. ".$exception->getMessage());
            }
        }//end if

        if (is_array(value: $config[$configKey]) === true) {
            try {
                $config[$configKey] = $this->mappingService->mapping(mappingObject: $mapping, input: $config[$configKey]);
            } catch (Exception | LoaderError | SyntaxError $exception) {
                $this->callLogger->error(message: "Could not map with mapping {$endpointConfigOut[$configKey]['mapping']} while handling $configKey EndpointConfigOut for a Source. ".$exception->getMessage());
            }
        }//end if

        return $config;

    }//end handleEndpointConfigOut()

    /**
     * Will check if we have to handle EndpointConfigIn on an Exception response.
     *
     * @param array       $endpointConfigIn The endpointConfig 'in' of a specific endpoint and source.
     * @param Exception   $exception        The Exception thrown as response of an api-call that we might want to change.
     * @param string|null $responseContent  The response content of an api-call that threw an Exception that we might want to change.
     *
     * @throws Exception
     *
     * @return Response The Response.
     */
    private function handleEndpointConfigInEx(array $endpointConfigIn, Exception $exception, ?string $responseContent): Response
    {
        $body = \Safe\json_decode(json: $responseContent, assoc: true);

        // Create exception array.
        $exceptionArray = [
            'statusCode' => $exception->getResponse()->getStatusCode(),
            'headers'    => $exception->getResponse()->getHeaders(),
            'body'       => ($body ?? $this->callService->decodeResponse(source: $this->source, response: $exception->getResponse())),
            'message'    => $exception->getMessage(),
        ];

        $headers = $this->handleEndpointConfigIn(responseData: $exception->getResponse()->getHeaders(), endpointConfigIn: $endpointConfigIn, responseProperty: 'headers');
        $error   = $this->handleEndpointConfigIn(responseData: $exceptionArray, endpointConfigIn: $endpointConfigIn, responseProperty: 'error');

        if (array_key_exists(key: 'statusCode', array: $error)) {
            $statusCode = $error['statusCode'];
            unset($error['statusCode']);
        }

        $error = \Safe\json_encode(value: $error);

        return new Response(status: ($statusCode ?? $exception->getCode()), headers: $headers, body: $error, version: $exception->getResponse()->getProtocolVersion());

    }//end handleEndpointConfigInEx()

    /**
     * Handles endpointConfig for a specific endpoint on a source and a specific response property like: 'headers' or 'body'.
     * After we did an api-call.
     * See FileSystemService->handleEndpointConfigIn() for how we handle this on FileSystem sources.
     *
     * @param mixed  $responseData     Some specific data from the response we might want to change. This data should match with the correct $responseProperty.
     * @param array  $endpointConfigIn The endpointConfig 'in' of a specific endpoint and source.
     * @param string $responseProperty The specific response property to check if its data needs to be changed and if so, change the data for.
     *
     * @return array The configuration array.
     */
    private function handleEndpointConfigIn(array|string $responseData, array $endpointConfigIn, string $responseProperty): array|string
    {
        $this->callLogger->info('Handling incoming configuration for endpoint');
        if (empty($responseData) === true || array_key_exists(key: $responseProperty, array: $endpointConfigIn) === false) {
            return $responseData;
        }

        if (array_key_exists(key: 'mapping', array: $endpointConfigIn[$responseProperty]) === false) {
            return $responseData;
        }

        $mapping = $this->entityManager->getRepository(className: Mapping::class)->findOneBy(criteria: ['reference' => $endpointConfigIn[$responseProperty]['mapping']]);
        if ($mapping === null) {
            $this->callLogger->error("Could not find mapping with reference {$endpointConfigIn[$responseProperty]['mapping']} while handling $responseProperty EndpointConfigIn for a Source.");

            return $responseData;
        }

        if (is_array(value: $responseData) === false) {
            $responseData = json_decode(json: $responseData->getContents(), associative: true);
        }

        try {
            $responseData = $this->mappingService->mapping(mappingObject: $mapping, input: $responseData);
        } catch (Exception | LoaderError | SyntaxError $exception) {
            $this->callLogger->error("Could not map with mapping {$endpointConfigIn[$responseProperty]['mapping']} while handling $responseProperty EndpointConfigIn for a Source. ".$exception->getMessage());
        }

        return $responseData;

    }//end handleEndpointConfigIn()

    /**
     * Handles the endpointsConfig of a Source after we did an api-call.
     * See FileSystemService->handleEndpointsConfigIn() for how we handle this on FileSystem sources.
     *
     * @param array          $endpointConfigIn
     * @param Response|null  $response         The response of an api-call we might want to change.
     * @param Exception|null $exception        The Exception thrown as response of an api-call that we might want to change.
     * @param string|null    $responseContent  The response content of an api-call that threw an Exception that we might want to change.
     *
     * @return Response The response.
     */
    private function handleEndpointsConfigIn(array $endpointConfigIn, ?Response $response = null, ?Exception $exception = null, ?string $responseContent = null): Response
    {

        // Let's check if we are dealing with an Exception and not a Response.
        if ($response === null && $exception !== null) {
            return $this->handleEndpointConfigInEx(endpointConfigIn: $endpointConfigIn, exception: $exception, responseContent: $responseContent);
        }

        // Handle endpointConfigIn for a Response.
        if ($response !== null) {
            $headers = $this->handleEndpointConfigIn(responseData: $response->getHeaders(), endpointConfigIn: $endpointConfigIn, responseProperty: 'headers');
            $body    = $this->handleEndpointConfigIn(responseData: $response->getBody(), endpointConfigIn: $endpointConfigIn, responseProperty: 'body');

            // Todo: handle content-type.
            is_array(value: $body) === true && $body = json_encode(value: $body);

            return new Response(status: $response->getStatusCode(), headers: $headers, body: $body, version: $response->getProtocolVersion());
        }

        return $response;

    }//end handleEndpointsConfigIn()

    /**
     * @param Source $source   The source to send the request to.
     * @param string $endpoint The endpoint on the proxy to send the request to.
     * @param string $method   The method of the request to send.
     * @param array  $config   The configuration to use with the request.
     *
     * @return Response The resulting response.
     *
     * @throws \Exception
     */
    public function callProxy(
        Source $source,
        string $endpoint,
        string $method,
        array $config = [],
        bool $asynchronous = false
    ): Response|Promise {
        $endpointsConfig = $source->getEndpointsConfig();

        if (empty($endpointsConfig) === true
            || (array_key_exists(key: $endpoint, array: $endpointsConfig) === false
            && array_key_exists(key: 'global', array: $endpointsConfig) === false)
        ) {
            return $this->callService->call(source: $source, endpoint: $endpoint, method: $method, config: $config, asynchronous: $asynchronous);
        }

        if (array_key_exists(key: $endpoint, array: $endpointsConfig) === true) {
            $endpointConfig = $endpointsConfig[$endpoint];
        } else if (array_key_exists(key: 'global', array: $endpointsConfig) === true) {
            $endpointConfig = $endpointsConfig['global'];
        }

        if (isset($endpointConfig['out']) === true) {
            $config = $this->handleEndpointConfigOut(config: $config, endpointConfigOut: $endpointConfig['out'], configKey: 'query');
            $config = $this->handleEndpointConfigOut(config: $config, endpointConfigOut: $endpointConfig['out'], configKey: 'headers');
            $config = $this->handleEndpointConfigOut(config: $config, endpointConfigOut: $endpointConfig['out'], configKey: 'body');
        }

        try {
            $response = $this->callService->call(source: $source, endpoint: $endpoint, method: $method, config: $config);
        } catch (ServerException | ClientException | RequestException | GuzzleException | Exception $exception) {
            if (isset($endpointConfig['in']) === true
                && isset($endpointConfig['in']['error']) === true
                && method_exists(object_or_class: get_class(object: $exception), method: 'getResponse') === true
                && $exception->getResponse() !== null
            ) {
                $this->source = $source;
                return $this->handleEndpointsConfigIn(endpointConfigIn: $endpointConfig['in'], exception: $exception);
            }

            throw $exception;
        }

        if (isset($endpointConfig['in']) === true) {
            $response = $this->handleEndpointsConfigIn(endpointConfigIn: $endpointConfig['in'], response: $response);
        }

        return $response;

    }//end callProxy()

    private function proxyConfigBuilder(): array
    {
        if (strpos($this->data['headers']['content-type'][0],  'multipart/form-data') !== false) {
            $post = $this->data['post'];
            array_walk(
                $post,
                function (&$value, $key) {
                    $value = [
                        'name'     => $key,
                        'contents' => $value,
                    ];
                }
            );
            return [
                'query'     => $this->data['query'],
                'headers'   => $this->data['headers'],
                'multipart' => array_values($post),
            ];
        } else if (strpos($this->data['headers']['content-type'][0], 'application/x-www-form-urlencoded') !== false) {
            return [
                'query'     => $this->data['query'],
                'headers'   => $this->data['headers'],
                'form_data' => $this->data['post'],
            ];
        }//end if

        return [
            'query'   => $this->data['query'],
            'headers' => $this->data['headers'],
            'body'    => $this->data['crude_body'],
        ];

    }//end proxyConfigBuilder()

    /**
     * Handles a proxy Endpoint.
     * todo: we want to merge proxyHandler() and requestHandler() code at some point.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration from the call
     *
     * @return SymfonyResponse The data as returned bij the original source
     */
    public function proxyHandler(array $data, array $configuration, ?Source $proxy = null): SymfonyResponse
    {
        $this->data          = $data;
        $this->configuration = $configuration;

        // If we already have a proxy, we can skip these checks.
        if ($proxy instanceof Source === false) {
            $proxy = $data['endpoint']->getProxy();
            // We only do proxying if the endpoint forces it, and we do not have a proxy.
            if ($data['endpoint'] instanceof Endpoint === false || $proxy === null) {
                $message = !$data['endpoint'] instanceof Endpoint ? "No Endpoint in data['endpoint']" : "This Endpoint has no Proxy: {$data['endpoint']->getName()}";

                return new SymfonyResponse(
                    $this->requestService->serializeData(['message' => $message], $contentType),
                    SymfonyResponse::HTTP_NOT_FOUND,
                    ['Content-type' => $contentType]
                );
            }//end if

            if ($proxy instanceof Source && ($proxy->getIsEnabled() === null || $proxy->getIsEnabled() === false)) {
                return new SymfonyResponse(
                    $this->requestService->serializeData(['message' => "This Source is not enabled: {$proxy->getName()}"], $contentType),
                    SymfonyResponse::HTTP_OK,
                    // This should be ok, so we can disable Sources without creating error responses?
                    ['Content-type' => $contentType]
                );
            }
        }//end if

        $securityResponse = $this->requestService->checkUserScopes([$proxy->getReference()], 'sources');
        if ($securityResponse instanceof SymfonyResponse === true) {
            return $securityResponse;
        }

        // Work around the _ with a custom function for getting clean query parameters from a request
        $this->data['query'] = $this->requestService->realRequestQueryAll();
        if (isset($this->data['query']['extend']) === true) {
            $extend = $this->data['query']['extend'];
            // Make sure we do not send this gateway specific query param to the proxy / Source.
            unset($this->data['query']['extend']);
        }

        // Make sure we set object to null in the session, for detecting the correct AuditTrails to create. Also used for DateRead to work correctly!
        $this->session->set('object', null);

        if (isset($data['path']['{route}']) === true && empty($data['path']['{route}']) === false) {
            $this->data['path'] = '/'.$data['path']['{route}'];
        } else {
            $this->data['path'] = '';
        }

        if (count($data['endpoint']->getFederationProxies()) > 1) {
            return $this->federationProxyHandler($data['endpoint']->getFederationProxies(), $this->data['path'], $this->proxyConfigBuilder());
        }

        // Don't pass gateway authorization to the source.
        unset($this->data['headers']['authorization']);

        $url = \Safe\parse_url($proxy->getLocation());

        // Make a guzzle call to the source based on the incoming call.
        try {
            // Check if we are dealing with http, https or something else like a ftp (fileSystem).
            if (($url['scheme'] === 'http' || $url['scheme'] === 'https')) {
                $result = $this->callService->call(
                    $proxy,
                    $this->data['path'],
                    $this->data['method'],
                    $this->proxyConfigBuilder()
                );
            } else {
                $result = $this->fileSystemService->call($proxy, $this->data['path']);
                $result = new Response(200, [], $this->serializer->serialize($result, 'json'));
            }//end if

            $contentType = 'application/json';
            if (isset($result->getHeaders()['content-type'][0]) === true) {
                $contentType = $result->getHeaders()['content-type'][0];
            }

            $resultContent = $this->requestService->unserializeData($result->getBody()->getContents(), $contentType);

            // Handle _self metadata, includes adding dateRead
            if (isset($extend) === true) {
                $this->data['query']['extend'] = $extend;
            }

            $this->requestService->handleMetadataSelf($resultContent, $proxy);

            $headers = $result->getHeaders();

            if (isset($headers['content-length']) === true) {
                unset($headers['content-length']);
            }

            if (isset($headers['Content-Length']) === true) {
                unset($headers['Content-Length']);
            }

            // Let create a response from the guzzle call.
            $response = new SymfonyResponse(
                $this->requestService->serializeData($resultContent, $contentType),
                $result->getStatusCode(),
                $headers
            );
        } catch (Exception $exception) {
            $statusCode = 500;
            if (array_key_exists($exception->getCode(), SymfonyResponse::$statusTexts) === true) {
                $statusCode = $exception->getCode();
            }

            if (method_exists(get_class($exception), 'getResponse') === true && $exception->getResponse() !== null) {
                $body       = $exception->getResponse()->getBody()->getContents();
                $statusCode = $exception->getResponse()->getStatusCode();
                $headers    = $exception->getResponse()->getHeaders();
            }

            // Catch weird statuscodes (like 0).
            if (array_key_exists($statusCode, SymfonyResponse::$statusTexts) === false) {
                $statusCode = 502;
            }

            $content  = $this->requestService->serializeData(
                [
                    'message' => $exception->getMessage(),
                    'body'    => ($body ?? "Can\'t get a response & body for this type of Exception: ").get_class($exception),
                ],
                $contentType
            );
            $response = new SymfonyResponse($content, $statusCode, ($headers ?? ['Content-Type' => $contentType]));
        }//end try

        // And don so let's return what we have.
        return $response;

    }//end proxyHandler()

    /**
     * Checks if the query parameter to relay rating is set and if so, return the value while unsetting the query parameter.
     *
     * @param  array $config The call configuration.
     * @return bool
     */
    public function useRelayRating(array &$config): bool
    {
        $returnValue = true;
        if (isset($config['query']['_federalization_relay_rating']) === true) {
            $returnValue = $config['query']['_federalization_relay_rating'];

            unset($config['query']['_federalization_relay_rating']);
        }

        return $returnValue;

    }//end useRelayRating()

    /**
     * Takes the config array and includes or excludes sources for federated requests based upon query parameters.
     *
     * @param array      $config  The call configuration.
     * @param Collection $proxies The full list of proxies configured for the endpoint.
     *
     * @return Collection The list of proxies that remains after including or excluding sources.
     *
     * @throws Exception Thrown when both include and exclude query parameters are given.
     */
    public function getFederationSources(array &$config, Collection $proxies): Collection
    {
        if (isset($config['query']['_federalization_use_sources']) === true && isset($config['query']['_federalization_exclude_sources']) === true) {
            $this->logger->error(message: 'Use of sources and exclusion of sources cannot be done in the same request');
            throw new Exception(message: 'Use of sources and exclusion of sources cannot be done in the same request');
        }

        $usedSourceIds     = [];
        $excludedSourceIds = [];

        // Returns all proxies when neither uses or excludes are given, this can be done by not setting the query parameters, but also by setting uses to * or excludes to null
        if ((isset($config['query']['_federalization_use_sources']) === true && $config['query']['_federalization_use_sources'] === '*')
            || (isset($config['query']['_federalization_exclude_sources']) === true && $config['query']['_federalization_exclude_sources'] === 'null')
            || (isset($config['query']['_federalization_use_sources']) === false && isset($config['query']['_federalization_exclude_sources']) === false)
        ) {
            unset($config['query']['_federalization_exclude_sources'], $config['query']['_federalization_use_sources']);
            return $proxies;
        } else if (isset($config['query']['_federalization_use_sources']) === true && $config['query']['_federalization_use_sources'] !== '*') {
            $usedSourceIds = explode(separator:',', string: $config['query']['_federalization_use_sources']);
        } else if (isset($config['query']['_federalization_exclude_sources']) === true && $config['query']['_federalization_exclude_sources'] !== null) {
            $excludedSourceIds = explode(separator: ',', string: $config['query']['_federalization_exclude_sources']);
        }

        foreach ($proxies as $key => $proxy) {
            if (($usedSourceIds !== [] && in_array(needle: $proxy->getId()->toString(), haystack: $usedSourceIds) === false)
                || ($excludedSourceIds !== [] && in_array(needle: $proxy->getId()->toString(), haystack: $excludedSourceIds) === true)
            ) {
                $proxies->remove(key: $key);
            }
        }

        unset($config['query']['_federalization_exclude_sources'], $config['query']['_federalization_use_sources']);

        return $proxies;

    }//end getFederationSources()

    /**
     * Update configuration from federation query parameters, sets timeout and http_errors, unsets the query parameters.
     *
     * @param array $config The original call configuration including the federation query parameters.
     *
     * @return array The updated call configuration.
     */
    public function getFederationConfig(array $config): array
    {
        $config['timeout']     = 3;
        $config['http_errors'] = true;

        if (isset($config['query']['_federalization_timeout']) === true) {
            $config['timeout'] = ($config['query']['_federalization_timeout'] / 1000);
            unset($config['query']['_federalization_timeout']);
        }

        if (isset($config['query']['_federalization_ignore_error']) === true) {
            $config['http_errors'] = $config['query']['_federalization_ignore_error'] === "false" ? true : false;
            unset($config['query']['_federalization_ignore_error']);
        }

        return $config;

    }//end getFederationConfig()

    /**
     * Runs a federated request to a multitude of proxies and aggregrates the results.
     *
     * @param Collection $proxies The proxies to send the request to.
     * @param string     $path    The path to send the request to.
     * @param array      $config  The call configuration.
     *
     * @return Response The resulting response.
     *
     * @throws Exception
     */
    public function federationProxyHandler(Collection $proxies, string $path, array $config): SymfonyResponse
    {
        $this->requestTimes = [];

        try {
            $proxies = $this->getFederationSources(config: $config, proxies: $proxies);
        } catch (Exception $exception) {
            return new SymfonyResponse(content: \Safe\json_encode(value: ['message' => $exception->getMessage()]), status: 400, headers: ['content-type' => 'application/json']);
        }

        $config = $this->getFederationConfig(config: $config);

        $promises = [];
        foreach ($proxies as $id => $proxy) {
            $config['on_stats'] = function (TransferStats $stats) use ($id) {
                $this->requestTimes[$id] = $stats->getTransferTime();
            };

            $promises[$id] = $this->callProxy(source: $proxy, endpoint: $path, method: 'GET', config: $config, asynchronous: true);
        }

        $responses = Utils::settle(promises: $promises)->wait();

        $results['_sources'] = [];
        $results['results']  = new ArrayCollection();
        foreach ($responses as $id => $response) {
            if ($response['state'] === 'rejected' && ($response['reason'] instanceof ConnectException || $config['http_errors'] === false)) {
                continue;
            } else if ($response['state'] === 'rejected' && ($response['reason'] instanceof ServerException || $response['reason'] instanceof ClientException)) {
                $this->logger->error(message: $response['reason']->getMessage());
                return new SymfonyResponse(content: \Safe\json_encode(value: ['message' => $response['reason']->getMessage()]), status: 523, headers: ['content-type' => 'application/json']);
            }

            $decoded            = $this->callService->decodeResponse(source: $proxies[$id], response: $response['value']);
            $decoded['results'] = array_map(
                callback:
                function (array $value) use ($proxies, $id) {
                    $value['_source'] = $proxies[$id]->getId()->toString();
                    return $value;
                },
                array: $decoded['results']
            );

            // This if statement is here for the comfort of programmers so IDEs recognise value as Response, the value can never be anything else than value.
            if ($response['value'] instanceof Response === false) {
                continue;
            }

            $results['_sources'][] = [
                'id'               => $proxies[$id]->getId()->toString(),
                'name'             => $proxies[$id]->getName(),
                'reference'        => $proxies[$id]->getReference(),
                'status_code'      => $response['value']->getStatusCode(),
                'response_time'    => (int) ($this->requestTimes[$id] * 1000),
                'objects_returned' => count(value: $decoded['results']),
            ];

            $results['results'] = new ArrayCollection(elements: array_merge(array1: $results['results']->toArray(), array2: $decoded['results']));
        }//end foreach

        $content = $this->serializer->serialize(data: $results, format: 'json');

        return new SymfonyResponse(content: $content, status: 200, headers: ['Content-Type' => 'application/json']);

    }//end federationProxyHandler()
}//end class
