<?php

namespace CommonGateway\CoreBundle\Service;

//use App\Entity\CallLog;
use App\Entity\Gateway as Source;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\YamlEncoder;

/**
 * Service to call external sources.
 *
 * This service provides a guzzle wrapper to work with sources in the common gateway.
 *
 * @Author Ruben van der Linde <ruben@conduction.nl>, Robert Zondervan <robert@conduction.nl>, Sarai Misidjan <sarai@conduction.nl>, Barry Brands <barry@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @TODO add all backend developers here?
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class CallService
{
    private AuthenticationService $authenticationService;
    private Client $client;
    private EntityManagerInterface $entityManager;
    private FileService $fileService;
    private MappingService $mappingService;
    private SessionInterface $session;
    private LoggerInterface $callLogger;

    /**
     * @param AuthenticationService  $authenticationService The authentication service
     * @param EntityManagerInterface $entityManager         The entity manager
     * @param FileService            $fileService           The file service
     * @param MappingService         $mappingService        The mapping service
     * @param SessionInterface       $session               The current session.
     * @param LoggerInterface        $callLogger            The logger for the call channel.
     */
    public function __construct(
        AuthenticationService $authenticationService,
        EntityManagerInterface $entityManager,
        FileService $fileService,
        MappingService $mappingService,
        SessionInterface $session,
        LoggerInterface $callLogger
    ) {
        $this->authenticationService = $authenticationService;
        $this->client = new Client([]);
        $this->entityManager = $entityManager;
        $this->fileService = $fileService;
        $this->mappingService = $mappingService;
        $this->session = $session;
        $this->callLogger = $callLogger;
    }

    /**
     * Writes the certificate and ssl keys to disk, returns the filenames.
     *
     * @param array $config The configuration as stored in the source
     *
     * @return void
     */
    public function getCertificate(array &$config)
    {
        if (isset($config['cert']) === true) {
            if (is_array($config['cert'])) {
                $config['cert'][0] = $this->fileService->writeFile('certificate', $config['cert'][0]);
            } elseif (is_string($config['cert'])) {
                $config['cert'] = $this->fileService->writeFile('certificate', $config['cert']);
            }
        }
        if (isset($config['ssl_key']) === true) {
            if (is_array($config['ssl_key'])) {
                $config['ssl_key'][0] = $this->fileService->writeFile('privateKey', $config['ssl_key'][0]);
            } elseif (is_string($config['ssl_key'])) {
                $config['ssl_key'] = $this->fileService->writeFile('privateKey', $config['ssl_key']);
            }
        }
        if (isset($config['verify']) === true && is_string($config['verify']) === true) {
            $config['verify'] = $this->fileService->writeFile('verify', $config['verify']);
        }
    }

    /**
     * Removes certificates and private keys from disk if they are not necessary anymore.
     *
     * @param array $config The configuration with filenames
     *
     * @return void
     */
    public function removeFiles(array $config): void
    {
        if (isset($config['cert']) === true) {
            $filename = is_array($config['cert']) ? $config['cert'][0] : $config['cert'];
            $this->fileService->removeFile($filename);
        }
        if (isset($config['ssl_key']) === true) {
            $filename = is_array($config['ssl_key']) ? $config['ssl_key'][0] : $config['ssl_key'];
            $this->fileService->removeFile($filename);
        }
        if (isset($config['verify']) === true && is_string($config['verify']) === true) {
            $this->fileService->removeFile($config['verify']);
        }
    }

    /**
     * Removes empty headers and sets array to string values.
     *
     * @param array $headers Http headers
     *
     * @return array|null
     */
    private function removeEmptyHeaders(array $headers): ?array
    {
        foreach ($headers as $key => $header) {
            if (is_array($header) && count($header) < 2) {
                if (!empty($header[0])) {
                    $headers[$key] = $header[0];
                } else {
                    unset($headers[$key]);
                }
            }
        }

        return $headers;
    }

    /**
     * Calls a source according to given configuration.
     *
     * @param Source $source             The source to call.
     * @param string $endpoint           The endpoint on the source to call.
     * @param string $method             The method on which to call the source.
     * @param array  $config             The additional configuration to call the source.
     * @param bool   $asynchronous       Whether or not to call the source asynchronously.
     * @param bool   $createCertificates Whether or not to create certificates for this source.
     *
     * @return Response
     */
    public function call(
        Source $source,
        string $endpoint = '',
        string $method = 'GET',
        array $config = [],
        bool $asynchronous = false,
        bool $createCertificates = true
    ): Response {
        $this->session->set('source', $source->getId()->toString());
        $this->callLogger->info('Calling source '.$source->getName());

        if (!$source->getIsEnabled()) {
            throw new HttpException('409', "This source is not enabled: {$source->getName()}");
        }
        if ($source->getConfiguration()) {
            $config = array_merge_recursive($config, $source->getConfiguration());
        }

//        $log = new CallLog();
//        $log->setSource($source);
//        $log->setEndpoint($source->getLocation().$endpoint);
//        $log->setMethod($method);
//        $log->setConfig($config);
//        $log->setRequestBody($config['body'] ?? null);

        if (empty($source->getLocation())) {
            throw new HttpException('409', "This source has no location: {$source->getName()}");
        }
        if (isset($config['headers']) === false) {
            $config['headers'] = [];
        }

        $parsedUrl = parse_url($source->getLocation());

        // Set authentication if needed
        $config = array_merge_recursive($this->getAuthentication($source), $config);
        $createCertificates && $this->getCertificate($config);
        $config['headers'] = array_merge($source->getHeaders() ?? [], $config['headers']); // Backwards compatible, $source->getHeaders = deprecated
        $config['headers']['host'] = $parsedUrl['host'];
        $config['headers'] = $this->removeEmptyHeaders($config['headers']);
//        $log->setRequestHeaders($config['headers'] ?? null);

        $url = $source->getLocation().$endpoint;
        $this->callLogger->info('Calling url '.$url);

        $config = $this->handleEndpointsConfigOut($source, $endpoint, $config);

        $startTimer = microtime(true);

        $this->callLogger->debug('Call configuration: ', $config);
        // Lets make the call
        try {
            if (!$asynchronous) {
                $response = $this->client->request($method, $url, $config);
            } else {
                $response = $this->client->requestAsync($method, $url, $config);
            }
            $this->callLogger->info("Request to $url succesful");
        } catch (ServerException|ClientException|RequestException|Exception $exception) {
//            $stopTimer = microtime(true);
//            $log->setResponseStatus('');
//            if ($e->getResponse()) {
//                $log->setResponseStatusCode($e->getResponse()->getStatusCode());
//                $log->setResponseBody($e->getResponse()->getBody()->getContents());
//                $log->setResponseHeaders($e->getResponse()->getHeaders());
//            } else {
//                $log->setResponseStatusCode(0);
//                $log->setResponseBody($e->getMessage());
//            }
//            $log->setResponseTime($stopTimer - $startTimer);
//            $this->entityManager->persist($log);
//            $this->entityManager->flush();

            $responseContent = method_exists(get_class($exception), 'getResponse') === true ? $exception->getResponse()->getBody()->getContents() : '';
            $this->callLogger->error('Request failed with error '.$exception->getMessage().' and body '.$responseContent);

            throw $exception;
        } catch (GuzzleException $exception) {
            $this->callLogger->error('Request failed with error '.$exception);

            throw $exception;
        }
//        $stopTimer = microtime(true);
//
//        $responseClone = clone $response;
//
//        $log->setResponseHeaders($responseClone->getHeaders());
//        $log->setResponseStatus('');
//        $log->setResponseStatusCode($responseClone->getStatusCode());
//        // Disabled because you cannot getBody after passing it here
//        // $log->setResponseBody($responseClone->getBody()->getContents());
//        $log->setResponseBody('');
//        $log->setResponseTime($stopTimer - $startTimer);
//        $this->entityManager->persist($log);
//        $this->entityManager->flush();

        $createCertificates && $this->removeFiles($config);

        return $this->handleEndpointsConfigIn($source, $endpoint, $response);
    }

    /**
     * Handles the endpointsConfig of a Source before we do an api-call.
     *
     * @param Source $source   The source.
     * @param string $endpoint The endpoint used to do an api-call on the source.
     * @param array  $config   The configuration for an api-call we might want to change.
     *
     * @return array The configuration array.
     */
    private function handleEndpointsConfigOut(Source $source, string $endpoint, array $config): array
    {
        $this->callLogger->info('Handling outgoing configuration for endpoints');
        $endpointsConfig = $source->getEndpointsConfig();
        if (empty($endpointsConfig)) {
            return $config;
        }

        // Let's check if the endpoint used on this source has "out" configuration in the EndpointsConfig of the source.
        if (array_key_exists($endpoint, $endpointsConfig) === true && array_key_exists('out', $endpointsConfig[$endpoint])) {
            $endpointConfigOut = $endpointsConfig[$endpoint]['out'];
        } elseif (array_key_exists('global', $endpointsConfig) === true && array_key_exists('out', $endpointsConfig['global'])) {
            $endpointConfigOut = $endpointsConfig['global']['out'];
        }

        if (isset($endpointConfigOut) === true) {
            $config = $this->handleEndpointConfigOut($config, $endpointConfigOut, 'query');
            $config = $this->handleEndpointConfigOut($config, $endpointConfigOut, 'headers');
            $config = $this->handleEndpointConfigOut($config, $endpointConfigOut, 'body');
        }

        return $config;
    }//end handleEndpointsConfigOut()

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
        $this->callLogger->info('Handling outgoing configuration for endpoint');
        if (array_key_exists($configKey, $config) === false || array_key_exists($configKey, $endpointConfigOut) === false) {
            return $config;
        }

        if (array_key_exists('mapping', $endpointConfigOut[$configKey])) {
            $mapping = $this->entityManager->getRepository('App:Mapping')->findOneBy(['reference' => $endpointConfigOut[$configKey]['mapping']]);
            if ($mapping === null) {
                $this->callLogger->error("Could not find mapping with reference {$endpointConfigOut[$configKey]['mapping']} while handling $configKey EndpointConfigOut for a Source");

                return $config;
            }

            try {
                $config[$configKey] = $this->mappingService->mapping($mapping, $config[$configKey]);
            } catch (Exception|LoaderError|SyntaxError $exception) {
                $this->callLogger->error("Could not map with mapping {$endpointConfigOut[$configKey]['mapping']} while handling $configKey EndpointConfigOut for a Source. ".$exception->getMessage());
            }
        }

        return $config;
    }//end handleEndpointConfigOut()

    /**
     * Handles the endpointsConfig of a Source after we did an api-call.
     * See FileSystemService->handleEndpointsConfigIn() for how we handle this on FileSystem sources.
     *
     * @param Source   $source   The source.
     * @param string   $endpoint The endpoint used to do an api-call on the source.
     * @param Response $response The response of an api-call we might want to change.
     *
     * @return Response The response.
     */
    private function handleEndpointsConfigIn(Source $source, string $endpoint, Response $response): Response
    {
        $this->callLogger->info('Handling incoming configuration for endpoints');
        $endpointsConfig = $source->getEndpointsConfig();
        if (empty($endpointsConfig)) {
            return $response;
        }

        // Let's check if the endpoint used on this source has "in" configuration in the EndpointsConfig of the source.
        if (array_key_exists($endpoint, $endpointsConfig) === true && array_key_exists('in', $endpointsConfig[$endpoint])) {
            $endpointConfigIn = $endpointsConfig[$endpoint]['in'];
        } elseif (array_key_exists('global', $endpointsConfig) === true && array_key_exists('in', $endpointsConfig['global'])) {
            $endpointConfigIn = $endpointsConfig['global']['in'];
        }

        if (isset($endpointConfigIn) === true) {
            $headers = $this->handleEndpointConfigIn($response->getHeaders(), $endpointConfigIn, 'headers');
            $body = $this->handleEndpointConfigIn($response->getBody(), $endpointConfigIn, 'body');

            is_array($body) && $body = json_encode($body);

            return new Response($response->getStatusCode(), $headers, $body, $response->getProtocolVersion());
        }

        return $response;
    }//end handleEndpointsConfigIn()

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
    private function handleEndpointConfigIn($responseData, array $endpointConfigIn, string $responseProperty): array
    {
        $this->callLogger->info('Handling incoming configuration for endpoint');
        if (empty($responseData) === true || array_key_exists($responseProperty, $endpointConfigIn) === false) {
            return $responseData;
        }

        if (array_key_exists('mapping', $endpointConfigIn[$responseProperty])) {
            $mapping = $this->entityManager->getRepository('App:Mapping')->findOneBy(['reference' => $endpointConfigIn[$responseProperty]['mapping']]);
            if ($mapping === null) {
                $this->callLogger->error("Could not find mapping with reference {$endpointConfigIn[$responseProperty]['mapping']} while handling $responseProperty EndpointConfigIn for a Source.");

                return $responseData;
            }
            $responseData = json_decode($responseData->getContents(), true);

            try {
                $responseData = $this->mappingService->mapping($mapping, $responseData);
            } catch (Exception|LoaderError|SyntaxError $exception) {
                $this->callLogger->error("Could not map with mapping {$endpointConfigIn[$responseProperty]['mapping']} while handling $responseProperty EndpointConfigIn for a Source. ".$exception->getMessage());
            }
        }

        return $responseData;
    }//end handleEndpointConfigIn()

    /**
     * Determine the content type of a response.
     *
     * @param Response $response The response to determine the content type for
     * @param Source   $source   The source that has been called to create the response
     *
     * @return string The (assumed) content type of the response
     */
    private function getContentType(Response $response, Source $source): string
    {
        $this->callLogger->debug('Determine content type of response');

        // switch voor obejct
        $contentType = $response->getHeader('content-type')[0];

        if (!$contentType) {
            $contentType = $source->getAccept();
        }

        return $contentType;
    }

    /**
     * Decodes a response based on the source it belongs to.
     *
     * @param Source   $source   The source that has been called
     * @param Response $response The response to decode
     *
     * @throws \Exception Thrown if the response does not fit any supported content type
     *
     * @return array The decoded response
     */
    public function decodeResponse(
        Source $source,
        Response $response,
        ?string $contentType = 'application/json'
    ): array {
        $this->callLogger->info('Decoding response content');
        // resultaat omzetten

        // als geen content-type header dan content-type header is accept header
        $responseBody = $response->getBody()->getContents();
        if (!$responseBody) {
            return [];
        }
        $this->callLogger->debug('Response content: '.$responseBody);

        $xmlEncoder = new XmlEncoder(['xml_root_node_name' => $this->configuration['apiSource']['location']['xmlRootNodeName'] ?? 'response']);
        $yamlEncoder = new YamlEncoder();
        $contentType = $this->getContentType($response, $source) ?? $contentType;
        switch ($contentType) {
            case 'text/yaml':
            case 'text/x-yaml':
                return $yamlEncoder->decode($responseBody, 'yaml');
            case 'text/xml':
            case 'text/xml; charset=utf-8':
            case 'application/xml':
                return $xmlEncoder->decode($responseBody, 'xml');
            case 'application/json':
            default:
                $result = json_decode($responseBody, true);
        }

        if (isset($result)) {
            return $result;
        }

        // Fallback: if the json_decode didn't work, try to decode XML, if that doesn't work an error is thrown.
        try {
            $result = $xmlEncoder->decode($responseBody, 'xml');

            return $result;
        } catch (\Exception $exception) {
            $this->callLogger->error('Could not decode body, content type could not be determined');

            throw new \Exception('Could not decode body, content type could not be determined');
        }
    }

    /**
     * Determines the authentication procedure based upon a source.
     *
     * @param Source $source The source to base the authentication procedure on
     *
     * @return array The config parameters needed to authenticate on the source
     */
    private function getAuthentication(Source $source): array
    {
        return $this->authenticationService->getAuthentication($source);
    }

    /**
     * Fetches all pages for a source and merges the result arrays to one array.
     *
     * @TODO: This is based on some assumptions
     *
     * @param Source $source   The source to call
     * @param string $endpoint The endpoint on the source to call
     * @param array  $config   The additional configuration to call the source
     *
     * @return array The array of results
     */
    public function getAllResults(Source $source, string $endpoint = '', array $config = []): array
    {
        $this->callLogger->info('Fetch all data from source and combine the results into one array');
        $errorCount = 0;
        $pageCount = 1;
        $results = [];
        $previousResult = [];
        while ($errorCount < 5) {
            try {
                $config['query']['page'] = $pageCount;
                $pageCount++;
                $response = $this->call($source, $endpoint, 'GET', $config);
                $decodedResponse = $this->decodeResponse($source, $response);
                if (
                    $decodedResponse === [] ||
                    isset($decodedResponse['results']) && $decodedResponse['results'] === [] ||
                    isset($decodedResponse['items']) && $decodedResponse['items'] == [] ||
                    isset($decodedResponse['page']) && $decodedResponse['page'] !== $pageCount - 1 ||
                    $decodedResponse == $previousResult
                ) {
                    break;
                }
                $decodedResponses[] = $decodedResponse;
                $previousResult = $decodedResponse;
            } catch (\Exception $exception) {
                $errorCount++;
                $this->callLogger->error($exception->getMessage());
            }
            if (isset($decodedResponse['results'])) {
                $results = array_merge($decodedResponse['results'], $results);
            } elseif (isset($decodedResponse['items'])) {
                $results = array_merge($decodedResponse['items'], $results);
            } elseif (isset($decodedResponse[0])) {
                $results = array_merge($decodedResponse, $results);
            }
        }

        return $results;
    }
}
