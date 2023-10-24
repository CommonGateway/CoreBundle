<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Gateway as Source;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
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
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class CallService
{

    /**
     * @var AuthenticationService $authenticationService
     */
    private AuthenticationService $authenticationService;

    /**
     * @var Client $client
     */
    private Client $client;

    /**
     * @var EntityManagerInterface $entityManager
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var FileService $fileService
     */
    private FileService $fileService;

    /**
     * @var MappingService $mappingService
     */
    private MappingService $mappingService;

    /**
     * @var SessionInterface $session
     */
    private SessionInterface $session;

    /**
     * @var LoggerInterface $callLogger
     */
    private LoggerInterface $callLogger;

    /**
     * The constructor sets al needed variables.
     *
     * @param AuthenticationService    $authenticationService The authentication service
     * @param EntityManagerInterface   $entityManager         The entity manager
     * @param FileService              $fileService           The file service
     * @param MappingService           $mappingService        The mapping service
     * @param SessionInterface         $session               The current session.
     * @param LoggerInterface          $callLogger            The logger for the call channel.
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
        $this->client                = new Client([]);
        $this->entityManager         = $entityManager;
        $this->fileService           = $fileService;
        $this->mappingService        = $mappingService;
        $this->session               = $session;
        $this->callLogger            = $callLogger;

    }//end __construct()

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
            if (is_array($config['cert']) === true) {
                $config['cert'][0] = $this->fileService->writeFile('certificate', $config['cert'][0]);
            } else if (is_string($config['cert'])) {
                $config['cert'] = $this->fileService->writeFile('certificate', $config['cert']);
            }
        }

        if (isset($config['ssl_key']) === true) {
            if (is_array($config['ssl_key']) === true) {
                $config['ssl_key'][0] = $this->fileService->writeFile('privateKey', $config['ssl_key'][0]);
            } else if (is_string($config['ssl_key']) === true) {
                $config['ssl_key'] = $this->fileService->writeFile('privateKey', $config['ssl_key']);
            }
        }

        if (isset($config['verify']) === true && is_string($config['verify']) === true) {
            $config['verify'] = $this->fileService->writeFile('verify', $config['verify']);
        }

    }//end getCertificate()

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
            $filename = is_array($config['cert']) === true ? $config['cert'][0] : $config['cert'];
            $this->fileService->removeFile($filename);
        }

        if (isset($config['ssl_key']) === true) {
            $filename = is_array($config['ssl_key']) === true ? $config['ssl_key'][0] : $config['ssl_key'];
            $this->fileService->removeFile($filename);
        }

        if (isset($config['verify']) === true && is_string($config['verify']) === true) {
            $this->fileService->removeFile($config['verify']);
        }

    }//end removeFiles()

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
            if (is_array($header) === true && count($header) < 2) {
                if (empty($header[0]) === false) {
                    $headers[$key] = $header[0];
                    continue;
                }

                unset($headers[$key]);
            }
        }//end foreach

        return $headers;

    }//end removeEmptyHeaders()

    /**
     * Handles the exception if the call triggered one.
     *
     * @param ServerException|ClientException|RequestException|Exception $exception
     * @param Source                                                     $source
     * @param string                                                     $endpoint
     *
     * @throws Exception
     *
     * @return Response $this->handleEndpointsConfigIn()
     */
    private function handleCallException($exception, Source $source, string $endpoint): Response
    {
        if (method_exists(get_class($exception), 'getResponse') === true
            && $exception->getResponse() !== null
        ) {
            $responseContent = $exception->getResponse()->getBody()->getContents();
        }

        $this->callLogger->error('Request failed with error '.$exception->getMessage().' and body '.($responseContent ?? null));

        return $this->handleEndpointsConfigIn($source, $endpoint, null, $exception, $responseContent ?? null);

    }//end handleCallException()

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
     * @throws Exception
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

        if ($source->getIsEnabled() === null || $source->getIsEnabled() === false) {
            throw new HttpException('409', "This source is not enabled: {$source->getName()}");
        }

        if (empty($source->getLocation()) === true) {
            throw new HttpException('409', "This source has no location: {$source->getName()}");
        }

        if (isset($config['headers']['Content-Type']) === true) {
            $overwriteContentType = $config['headers']['Content-Type'];
        }

        if (empty($source->getConfiguration()) === false) {
            $config = array_merge_recursive($config, $source->getConfiguration());
        }

        if (isset($config['headers']) === false) {
            $config['headers'] = [];
        }

        $url = $source->getLocation().$endpoint;

        // Set authentication if needed.
        $createCertificates && $this->getCertificate($config);
        $requestInfo = [
            'url'    => $url,
            'method' => $method,
        ];
        $config      = array_merge_recursive($this->getAuthentication($source, $config, $requestInfo), $config);

        // Backwards compatible, $source->getHeaders = deprecated.
        $config['headers'] = array_merge(($source->getHeaders() ?? []), $config['headers']);
        if (isset($overwriteContentType) === true) {
            $config['headers']['Content-Type'] = $overwriteContentType;
        }

        // Make sure we do not have an array of accept headers
        if (isset($config['headers']['accept']) === true && is_array($config['headers']['accept']) === true) {
            $config['headers']['accept'] = $config['headers']['accept'][0];
        }

        $parsedUrl                 = parse_url($source->getLocation());
        $config['headers']['host'] = $parsedUrl['host'];
        $config['headers']         = $this->removeEmptyHeaders($config['headers']);

        $config = $this->handleEndpointsConfigOut($source, $endpoint, $config);

        // Guzzle sets the Content-Type self when using multipart.
        if (isset($config['multipart']) === true && isset($config['headers']['Content-Type']) === true) {
            unset($config['headers']['Content-Type']);
        }

        // Guzzle sets the Content-Type self when using multipart.
        if (isset($config['multipart']) === true && isset($config['headers']['content-type']) === true) {
            unset($config['headers']['content-type']);
        }

        $this->callLogger->info('Calling url '.$url);
        $this->callLogger->debug('Call configuration: ', $config);

        // Let's make the call.
        // The $source here gets persisted but the flush needs be executed in a Service where this call function has been executed.
        // Because we don't want to flush/update the Source each time this ->call function gets executed for performance reasons.
        $source->setLastCall(new \DateTime());
        $this->entityManager->persist($source);
        try {
            if ($asynchronous === false) {
                $response = $this->client->request($method, $url, $config);
            } else {
                $response = $this->client->requestAsync($method, $url, $config);
            }

            $this->callLogger->info("Request to $url succesful");

            $this->callLogger->notice("$method Request to $url returned {$response->getStatusCode()}");

            $source->setStatus($response->getStatusCode());
            $this->entityManager->persist($source);
        } catch (ServerException | ClientException | RequestException | Exception $exception) {
            $this->callLogger->error('Request failed with error '.$exception);

            $response = $this->handleCallException($exception, $source, $endpoint);

            $source->setStatus($response->getStatusCode());
            $this->entityManager->persist($source);

            return $response;
        } catch (GuzzleException $exception) {
            $this->callLogger->error('Request failed with error '.$exception);

            $response = $this->handleEndpointsConfigIn($source, $endpoint, null, $exception, null);

            $source->setStatus($response->getStatusCode());
            $this->entityManager->persist($source);

            return $response;
        }//end try

        $createCertificates && $this->removeFiles($config);

        return $this->handleEndpointsConfigIn($source, $endpoint, $response, null, null);

    }//end call()

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
        if (empty($endpointsConfig) === true) {
            return $config;
        }

        // Let's check if the endpoint used on this source has "out" configuration in the EndpointsConfig of the source.
        if (array_key_exists($endpoint, $endpointsConfig) === true && array_key_exists('out', $endpointsConfig[$endpoint]) === true) {
            $endpointConfigOut = $endpointsConfig[$endpoint]['out'];
        } else if (array_key_exists('global', $endpointsConfig) === true && array_key_exists('out', $endpointsConfig['global']) === true) {
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
            }//end if

            if (is_string($config[$configKey]) === true) {
                try {
                    $body               = $this->mappingService->mapping($mapping, \Safe\json_decode($config[$configKey], true));
                    $config[$configKey] = \Safe\json_encode($body);
                } catch (Exception | LoaderError | SyntaxError $exception) {
                    $this->callLogger->error("Could not map with mapping {$endpointConfigOut[$configKey]['mapping']} while handling $configKey EndpointConfigOut for a Source. ".$exception->getMessage());
                }
            }//end if

            if (is_array($config[$configKey]) === true) {
                try {
                    $config[$configKey] = $this->mappingService->mapping($mapping, $config[$configKey]);
                } catch (Exception | LoaderError | SyntaxError $exception) {
                    $this->callLogger->error("Could not map with mapping {$endpointConfigOut[$configKey]['mapping']} while handling $configKey EndpointConfigOut for a Source. ".$exception->getMessage());
                }
            }//end if
        }//end if

        return $config;

    }//end handleEndpointConfigOut()

    /**
     * Handles the endpointsConfig of a Source after we did an api-call.
     * See FileSystemService->handleEndpointsConfigIn() for how we handle this on FileSystem sources.
     *
     * @param Source         $source          The source.
     * @param string         $endpoint        The endpoint used to do an api-call on the source.
     * @param Response|null  $response        The response of an api-call we might want to change.
     * @param Exception|null $exception       The Exception thrown as response of an api-call that we might want to change.
     * @param string|null    $responseContent The response content of an api-call that threw an Exception that we might want to change.
     *
     * @throws Exception
     *
     * @return Response The response.
     */
    private function handleEndpointsConfigIn(Source $source, string $endpoint, ?Response $response, ?Exception $exception = null, ?string $responseContent = null): Response
    {
        $this->callLogger->info('Handling incoming configuration for endpoints');
        $endpointsConfig = $source->getEndpointsConfig();
        if (empty($endpointsConfig)) {
            if ($response !== null) {
                return $response;
            }

            if ($exception !== null) {
                throw $exception;
            }
        }

        if (array_key_exists($endpoint, $endpointsConfig) === true
            && array_key_exists('in', $endpointsConfig[$endpoint]) === false
            || array_key_exists('global', $endpointsConfig) === true
            && array_key_exists('in', $endpointsConfig['global']) === false
        ) {
            if ($response !== null) {
                return $response;
            }

            if ($exception !== null) {
                throw $exception;
            }
        }//end if

        // Let's check if the endpoint used on this source has "in" configuration in the EndpointsConfig of the source.
        if (array_key_exists($endpoint, $endpointsConfig) === true && array_key_exists('in', $endpointsConfig[$endpoint])) {
            $endpointConfigIn = $endpointsConfig[$endpoint]['in'];
        } else if (array_key_exists('global', $endpointsConfig) === true && array_key_exists('in', $endpointsConfig['global'])) {
            $endpointConfigIn = $endpointsConfig['global']['in'];
        }

        // Let's check if we are dealing with an Exception and not a Response.
        if (isset($endpointConfigIn) === true && $response === null && $exception !== null) {
            return $this->handleEndpointConfigInEx($endpointConfigIn, $exception, $responseContent);
        }

        // Handle endpointConfigIn for a Response.
        if (isset($endpointConfigIn) === true && $response !== null) {
            $headers = $this->handleEndpointConfigIn($response->getHeaders(), $endpointConfigIn, 'headers');
            $body    = $this->handleEndpointConfigIn($response->getBody(), $endpointConfigIn, 'body');

            is_array($body) === true && $body = json_encode($body);

            return new Response($response->getStatusCode(), $headers, $body, $response->getProtocolVersion());
        }

        return $response;

    }//end handleEndpointsConfigIn()

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
        // Check if error is set and the exception has a getResponse() otherwise just throw the exception.
        if (array_key_exists('error', $endpointConfigIn) === false
            || method_exists(get_class($exception), 'getResponse') === false
            || $exception->getResponse() === null
        ) {
            throw $exception;
        }

        $body = json_decode($responseContent, true);

        // Create exception array.
        $exceptionArray = [
            'statusCode' => $exception->getResponse()->getStatusCode(),
            'headers'    => $exception->getResponse()->getHeaders(),
            'body'       => ($body ?? $exception->getResponse()->getBody()->getContents()),
            'message'    => $exception->getMessage(),
        ];

        $headers = $this->handleEndpointConfigIn($exception->getResponse()->getHeaders(), $endpointConfigIn, 'headers');
        $error   = $this->handleEndpointConfigIn($exceptionArray, $endpointConfigIn, 'error');

        if (array_key_exists('statusCode', $error)) {
            $statusCode = $error['statusCode'];
            unset($error['statusCode']);
        }

        $error = json_encode($error);

        return new Response(($statusCode ?? $exception->getCode()), $headers, $error, $exception->getResponse()->getProtocolVersion());

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
    private function handleEndpointConfigIn($responseData, array $endpointConfigIn, string $responseProperty): array
    {
        $this->callLogger->info('Handling incoming configuration for endpoint');
        if (empty($responseData) === true || array_key_exists($responseProperty, $endpointConfigIn) === false) {
            return $responseData;
        }

        if (array_key_exists('mapping', $endpointConfigIn[$responseProperty]) === true) {
            $mapping = $this->entityManager->getRepository('App:Mapping')->findOneBy(['reference' => $endpointConfigIn[$responseProperty]['mapping']]);
            if ($mapping === null) {
                $this->callLogger->error("Could not find mapping with reference {$endpointConfigIn[$responseProperty]['mapping']} while handling $responseProperty EndpointConfigIn for a Source.");

                return $responseData;
            }

            if (is_array($responseData) === false) {
                $responseData = json_decode($responseData->getContents(), true);
            }

            try {
                $responseData = $this->mappingService->mapping($mapping, $responseData);
            } catch (Exception | LoaderError | SyntaxError $exception) {
                $this->callLogger->error("Could not map with mapping {$endpointConfigIn[$responseProperty]['mapping']} while handling $responseProperty EndpointConfigIn for a Source. ".$exception->getMessage());
            }
        }//end if

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

        // Switch voor obejct.
        if (isset($response->getHeader('content-type')[0]) === true) {
            $contentType = $response->getHeader('content-type')[0];
        }

        if (isset($contentType) === false || empty($contentType) === true) {
            $contentType = $source->getAccept();

            if ($contentType === null) {
                $this->callLogger->warning('Accept of the Source '.$source->getReference().' === null');
                return 'application/json';
            }
        }

        return $contentType;

    }//end getContentType()

    /**
     * Decodes a response based on the source it belongs to.
     *
     * @param Source   $source   The source that has been called
     * @param Response $response The response to decode
     *
     * @throws Exception Thrown if the response does not fit any supported content type
     *
     * @return array|string The decoded response
     */
    public function decodeResponse(
        Source $source,
        Response $response,
        ?string $contentType = 'application/json'
    ) {
        $this->callLogger->info('Decoding response content');
        // resultaat omzetten.
        // als geen content-type header dan content-type header is accept header.
        $responseBody = $response->getBody()->getContents();
        if (isset($responseBody) === false || empty($responseBody) === true) {
            $this->callLogger->error('Cannot decode an empty response body');
            return [];
        }

        // This if is statement prevents binary code from being used a string.
        if (in_array(
            $contentType,
            [
                'application/pdf',
                'application/pdf; charset=utf-8',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document; charset=utf-8',
                'application/msword',
                'image/jpeg',
                'image/png',
            ]
        ) === false
        ) {
            $this->callLogger->debug('Response content: '.$responseBody);
        }

        $xmlEncoder  = new XmlEncoder(['xml_root_node_name' => ($this->configuration['apiSource']['location']['xmlRootNodeName'] ?? 'response')]);
        $yamlEncoder = new YamlEncoder();

        // This if statement is so that any given $contentType other than json doesn't get overwritten here.
        if ($contentType === 'application/json') {
            $contentType = ($this->getContentType($response, $source) ?? $contentType);
        }

        switch ($contentType) {
        case 'text/plain':
            return $responseBody;
        case 'text/yaml':
        case 'text/x-yaml':
        case 'text/yaml; charset=utf-8':
            return $yamlEncoder->decode($responseBody, 'yaml');
        case 'text/xml':
        case 'text/xml; charset=utf-8':
        case 'application/pdf':
        case 'application/pdf; charset=utf-8':
        case 'application/msword':
        case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
        case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document; charset=utf-8':
        case 'image/jpeg':
        case 'image/png':
            $this->callLogger->debug('Response content: binary code..');
            return base64_encode($responseBody);
        case 'application/xml':
        case 'application/xml; charset=utf-8':
            return $xmlEncoder->decode($responseBody, 'xml');
        case 'application/json':
        case 'application/json; charset=utf-8':
        default:
            $result = json_decode($responseBody, true);
        }//end switch

        if (isset($result) === true) {
            return $result;
        }

        // Fallback: if the json_decode didn't work, try to decode XML, if that doesn't work an error is thrown.
        try {
            $result = $xmlEncoder->decode($responseBody, 'xml');

            return $result;
        } catch (Exception $exception) {
            $this->callLogger->error('Could not decode body, content type could not be determined');

            throw new Exception('Could not decode body, content type could not be determined');
        }//end try

    }//end decodeResponse()

    /**
     * Determines the authentication procedure based upon a source.
     *
     * @param Source     $source      The source to base the authentication procedure on
     * @param array|null $config      The optional, updated Source configuration array.
     * @param array|null $requestInfo The optional, given request info.
     *
     * @return array The config parameters needed to authenticate on the source
     */
    private function getAuthentication(Source $source, ?array $config = null, ?array $requestInfo = []): array
    {
        return $this->authenticationService->getAuthentication($source, $config, $requestInfo);

    }//end getAuthentication()

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
        $errorCount     = 0;
        $pageCount      = 1;
        $results        = [];
        $previousResult = [];
        while ($errorCount < 5) {
            try {
                $config['query']['page'] = $pageCount;
                $pageCount++;
                $response = $this->call($source, $endpoint, 'GET', $config);

                $decodedResponse = $this->decodeResponse($source, $response);
                if ($decodedResponse === []
                    || isset($decodedResponse['data']) === true                       && $decodedResponse['data'] === []
                    || isset($decodedResponse['results']) === true                    && $decodedResponse['results'] === []
                    || isset($decodedResponse['items']) === true                      && $decodedResponse['items'] === []
                    || isset($decodedResponse['result']['instance']['rows']) === true && $decodedResponse['result']['instance']['rows'] === []
                    || isset($decodedResponse['page']) === true                       && $decodedResponse['page'] !== ($pageCount - 1)
                    || $decodedResponse == $previousResult
                ) {
                    break;
                }

                $previousResult = $decodedResponse;
            } catch (Exception $exception) {
                $errorCount++;
                $this->callLogger->error($exception->getMessage());
            }//end try

            if (isset($decodedResponse['results']) === true) {
                $results = array_merge($decodedResponse['results'], $results);
            } else if (isset($decodedResponse['items']) === true) {
                $results = array_merge($decodedResponse['items'], $results);
            } else if (isset($decodedResponse['data']) === true) {
                $results = array_merge($decodedResponse['data'], $results);
            } else if (isset($decodedResponse['result']['instance']['rows']) === true) {
                $results = array_merge($decodedResponse['result']['instance']['rows'], $results);
            } else if (isset($decodedResponse[0]) === true) {
                $results = array_merge($decodedResponse, $results);
            }
        }//end while

        return $results;

    }//end getAllResults()
}//end class
