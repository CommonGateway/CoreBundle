<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Gateway as Source;
use App\Entity\Mapping;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RequestStack;
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
 *
 * This service belongs to the open connector framework.
 */
class CallService
{

    /**
     * @var Client $client
     */
    private Client $client;

    /**
     * @var Session $session
     */
    private Session $session;

    /**
     * The source currently used for doing calls.
     *
     * @var Source
     */
    private Source $source;

    /**
     * The constructor sets al needed variables.
     *
     * @param AuthenticationService  $authenticationService The authentication service
     * @param EntityManagerInterface $entityManager         The entity manager
     * @param FileService            $fileService           The file service
     * @param RequestStack           $requestStack          The request stack.
     * @param LoggerInterface        $callLogger            The logger for the call channel.
     */
    public function __construct(
        private readonly AuthenticationService $authenticationService,
        private readonly EntityManagerInterface $entityManager,
        private readonly FileService $fileService,
        RequestStack $requestStack,
        private readonly LoggerInterface $callLogger
    ) {
        $this->client = new Client([]);
        try {
            $this->session = $requestStack->getSession();
        } catch (SessionNotFoundException $exception) {
            $this->session = new Session();
        }

    }//end __construct()

    /**
     * Writes the certificate and ssl keys to disk, returns the filenames.
     *
     * @param array $config The configuration as stored in the source
     *
     * @return void
     */
    public function setCertificate(array &$configuration): void
    {
        if (isset($config['cert']) === true) {
            if (is_array(value: $config['cert']) === true) {
                $config['cert'][0] = $this->fileService->writeFile(baseFileName: 'certificate', contents: $config['cert'][0]);
            } else if (is_string(value: $config['cert'])) {
                $config['cert'] = $this->fileService->writeFile(baseFileName: 'certificate', contents: $config['cert']);
            }
        }

        if (isset($config['ssl_key']) === true) {
            if (is_array(value: $config['ssl_key']) === true) {
                $config['ssl_key'][0] = $this->fileService->writeFile(baseFileName: 'privateKey', contents: $config['ssl_key'][0]);
            } else if (is_string(value: $config['ssl_key']) === true) {
                $config['ssl_key'] = $this->fileService->writeFile(baseFileName: 'privateKey', contents: $config['ssl_key']);
            }
        }

        if (isset($config['verify']) === true && is_string(value: $config['verify']) === true) {
            $config['verify'] = $this->fileService->writeFile(baseFilename: 'verify', contents: $config['verify']);
        }

    }//end setCertificate()

    /**
     * Removes certificates and private keys from disk if they are not necessary anymore.
     *
     * @param array $configuration The configuration with filenames
     *
     * @return void
     */
    public function removeFiles(array $configuration): void
    {
        if (isset($configuration['cert']) === true) {
            $filename = is_array(value: $configuration['cert']) === true ? $configuration['cert'][0] : $configuration['cert'];
            $this->fileService->removeFile(filename: $filename);
        }

        if (isset($configuration['ssl_key']) === true) {
            $filename = is_array(value: $configuration['ssl_key']) === true ? $configuration['ssl_key'][0] : $configuration['ssl_key'];
            $this->fileService->removeFile(filename: $filename);
        }

        if (isset($configuration['verify']) === true && is_string(value: $configuration['verify']) === true) {
            $this->fileService->removeFile(filename: $configuration['verify']);
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
            if (is_array(value: $header) === true && count(value: $header) < 2) {
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
     * Uses input parameters to create array with data used for creating a log after any call to a Source.
     * If the source->loggingConfig allows logging.
     *
     * @param array $requestInfo The info of the current request call done on a source, can contain: 'method' of the call, 'url' of the call & 'response' that the call returned.
     * @param array $config      The additional configuration used to call the source.
     *
     * @return array The array with data to use for creating a log.
     */
    private function sourceCallLogData(array $requestInfo, array $config): array
    {
        $loggingConfig  = $this->source->getLoggingConfig();
        $sourceCallData = [];

        if (empty($loggingConfig['callMethod']) === false) {
            $sourceCallData['callMethod'] = $requestInfo['method'];
        }

        if (empty($loggingConfig['callUrl']) === false) {
            $sourceCallData['callUrl'] = $requestInfo['url'];
        }

        if (empty($loggingConfig['callQuery']) === false) {
            $sourceCallData['callQuery'] = ($config['query'] ?? '');
        }

        if (empty($loggingConfig['callContentType']) === false) {
            $sourceCallData['callContentType'] = ($config['headers']['Content-Type'] ?? $config['headers']['content-type'] ?? '');
        }

        if (empty($loggingConfig['callBody']) === false) {
            $sourceCallData['callBody'] = ($config['body'] ?? '');
        }

        if (empty($loggingConfig['responseStatusCode']) === false) {
            $sourceCallData['responseStatusCode'] = $requestInfo['response'] !== null ? $requestInfo['response']->getStatusCode() : '';
        }

        if (empty($loggingConfig['responseContentType']) === false) {
            $sourceCallData['responseContentType'] = $requestInfo['response'] !== null && method_exists(object_or_class: $requestInfo['response'], method: 'getContentType') === true ? $requestInfo['response']->getContentType() : '';
        }

        if (empty($loggingConfig['responseBody']) === false) {
            $sourceCallData['responseBody'] = '';
            if ($requestInfo['response'] !== null && $requestInfo['response']->getBody() !== null) {
                $sourceCallData['responseBody'] = $requestInfo['response']->getBody()->getContents();

                // Make sure we can use ->getBody()->getContent() again after this^.
                $requestInfo['response']->getBody()->rewind();
            }
        }

        $sourceCallData['maxCharCountBody']      = ($loggingConfig['maxCharCountBody'] ?? 500);
        $sourceCallData['maxCharCountErrorBody'] = ($loggingConfig['maxCharCountErrorBody'] ?? 2000);

        return $sourceCallData;

    }//end sourceCallLogData()

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
        $this->source = $source;
        $this->session->set(name: 'source', value: $this->source->getId()->toString());
        $this->callLogger->info(message: 'Calling source '.$this->source->getName());

        if ($this->source->getIsEnabled() === null || $this->source->getIsEnabled() === false) {
            throw new HttpException(statusCode: '409', message: "This source is not enabled: {$this->source->getName()}");
        }

        if (empty($this->source->getLocation()) === true) {
            throw new HttpException(statusCode: '409', message:"This source has no location: {$this->source->getName()}");
        }

        if (isset($config['headers']['Content-Type']) === true) {
            $overwriteContentType = $config['headers']['Content-Type'];
        }

        if (empty($this->source->getConfiguration()) === false) {
            $config = array_merge_recursive(array1: $config, array2: $this->source->getConfiguration());
        }

        if (isset($config['headers']) === false) {
            $config['headers'] = [];
        }

        $url = $this->source->getLocation().$endpoint;

        // Set authentication if needed.
        $createCertificates && $this->setCertificate(configuration: $config);
        $requestInfo = [
            'url'    => $url,
            'method' => $method,
        ];
        $config      = array_merge_recursive(array1: $this->getAuthentication(config: $config, requestInfo: $requestInfo), array2: $config);

        // Backwards compatible, $this->source->getHeaders = deprecated.
        $config['headers'] = array_merge(array1: ($this->source->getHeaders() ?? []), array2: $config['headers']);
        if (isset($overwriteContentType) === true) {
            $config['headers']['Content-Type'] = $overwriteContentType;
        }

        // Make sure we do not have an array of accept headers
        if (isset($config['headers']['accept']) === true && is_array(value: $config['headers']['accept']) === true) {
            $config['headers']['accept'] = $config['headers']['accept'][0];
        }

        $parsedUrl                 = parse_url(url: $this->source->getLocation());
        $config['headers']['host'] = $parsedUrl['host'];
        $config['headers']         = $this->removeEmptyHeaders(headers: $config['headers']);

        // Guzzle sets the Content-Type self when using multipart.
        if (isset($config['multipart']) === true && isset($config['headers']['Content-Type']) === true) {
            unset($config['headers']['Content-Type']);
        }

        // Guzzle sets the Content-Type self when using multipart.
        if (isset($config['multipart']) === true && isset($config['headers']['content-type']) === true) {
            unset($config['headers']['content-type']);
        }

        $this->callLogger->info(message: 'Calling url '.$url);
        $this->callLogger->debug(message: 'Call configuration: ', context: $config);

        // Let's make the call.
        $this->source->setLastCall(lastCall: new \DateTime());
        // The $this->source here gets persisted but the flush needs be executed in a Service where this ->call() function has been executed.
        // Because we don't want to flush/update the Source each time this ->call() function gets executed for performance reasons.
        $this->entityManager->persist(object: $this->source);
        try {
            if ($asynchronous === false) {
                $response = $this->client->request(method: $method, uri: $url, options: $config);
            } else {
                $response = $this->client->requestAsync(method: $method, uri: $url, options: $config);
            }

            $this->source->setStatus(status: $response->getStatusCode());
            $this->entityManager->persist($this->source);

            $this->callLogger->info(message: "Request to $url successful");

            $this->callLogger->notice(
                message: "$method Request to $url returned {$response->getStatusCode()}",
                context: [
                    'sourceCall' => $this->sourceCallLogData(requestInfo: ['method' => $method, 'url' => $url, 'response' => $response], config: $config),
                ]
            );
        } catch (ServerException | ClientException | RequestException | GuzzleException | Exception $exception) {
            $this->callLogger->error(
                message: 'Request failed with error '.$exception,
                context: [
                    'sourceCall' => $this->sourceCallLogData(requestInfo: ['method' => $method, 'url' => $url, 'response' => ($response ?? null)], config: $config),
                ]
            );

            if (empty($response) === false) {
                $this->source->setStatus(status: $response->getStatusCode());
            }

            if (empty($response) === true) {
                $this->source->setStatus(status: 500);
            }

            $this->entityManager->persist(object: $this->source);

            throw $exception;
        }//end try

        $createCertificates && $this->removeFiles(configuration: $config);

        return $response;

    }//end call()

    /**
     * Determine the content type of response.
     *
     * @param Response $response The response to determine the content type for
     *
     * @return string The (assumed) content type of the response
     */
    private function getContentType(Response $response): string
    {
        $this->callLogger->debug(message: 'Determine content type of response');

        // Switch voor obejct.
        if (isset($response->getHeader(header: 'content-type')[0]) === true) {
            $contentType = $response->getHeader(header: 'content-type')[0];
        }

        if (isset($contentType) === false || empty($contentType) === true) {
            $contentType = $this->source->getAccept();

            if ($contentType === null) {
                $this->callLogger->warning(message: 'Accept of the Source '.$this->source->getReference().' === null');
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
        $this->source = $source;

        $this->callLogger->info(message: 'Decoding response content');
        // resultaat omzetten.
        // als geen content-type header dan content-type header is accept header.
        $responseBody = $response->getBody()->getContents();
        if (isset($responseBody) === false || empty($responseBody) === true) {
            if (in_array(needle: $response->getStatusCode(), haystack: [200, 201]) === true) {
                $this->callLogger->warning(message: 'Cannot decode an empty response body');
                return [];
            } else if ($response->getStatusCode() === 204) {
                return [];
            }

            $this->callLogger->error('Cannot decode an empty response body');
            return [];
        }

        // This if is statement prevents binary code from being used a string.
        if (in_array(
            needle: $contentType,
            haystack: [
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
            $this->callLogger->debug(message: 'Response content: '.$responseBody);
        }

        $xmlEncoder  = new XmlEncoder(defaultContext: ['xml_root_node_name' => ($this->configuration['apiSource']['location']['xmlRootNodeName'] ?? 'response')]);
        $yamlEncoder = new YamlEncoder();

        // This if statement is so that any given $contentType other than json doesn't get overwritten here.
        if ($contentType === 'application/json') {
            $contentType = ($this->getContentType(response: $response) ?? $contentType);
        }

        switch ($contentType) {
        case 'text/plain':
            return $responseBody;
        case 'text/yaml':
        case 'text/x-yaml':
        case 'text/yaml; charset=utf-8':
            return $yamlEncoder->decode(data: $responseBody, format: 'yaml');
        case 'text/xml':
        case 'text/xml; charset=utf-8':
        case 'application/pdf':
        case 'application/pdf; charset=utf-8':
        case 'application/msword':
        case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
        case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document; charset=utf-8':
        case 'image/jpeg':
        case 'image/png':
            $this->callLogger->debug(message: 'Response content: binary code..');
            return base64_encode(string: $responseBody);
        case 'application/xml':
        case 'application/xml; charset=utf-8':
            return $xmlEncoder->decode(data: $responseBody, format: 'xml');
        case 'application/json':
        case 'application/json; charset=utf-8':
        default:
            $result = json_decode(json: $responseBody, associative: true);
        }//end switch

        if (isset($result) === true) {
            return $result;
        }

        // Fallback: if the json_decode didn't work, try to decode XML, if that doesn't work an error is thrown.
        try {
            $result = $xmlEncoder->decode(data: $responseBody, format: 'xml');

            return $result;
        } catch (Exception $exception) {
            $this->callLogger->error(message: 'Could not decode body, content type could not be determined');

            throw new Exception(message: 'Could not decode body, content type could not be determined');
        }//end try

    }//end decodeResponse()

    /**
     * Determines the authentication procedure based upon a source.
     *
     * @param array|null $config      The optional, updated Source configuration array.
     * @param array|null $requestInfo The optional, given request info.
     *
     * @return array The config parameters needed to authenticate on the source
     */
    private function getAuthentication(?array $config = null, ?array $requestInfo = []): array
    {
        return $this->authenticationService->getAuthentication(source: $this->source, config: $config, requestInfo: $requestInfo);

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
        $this->source = $source;

        $this->callLogger->info(message: 'Fetch all data from source and combine the results into one array');
        $errorCount     = 0;
        $pageCount      = 1;
        $results        = [];
        $previousResult = [];
        while ($errorCount < 5) {
            try {
                $config['query']['page'] = $pageCount;
                $pageCount++;
                $response = $this->call(source: $source, endpoint: $endpoint, method: 'GET', config: $config);

                $decodedResponse = $this->decodeResponse(source: $source, response: $response);
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
                $this->callLogger->error(message: $exception->getMessage());
            }//end try

            if (isset($decodedResponse['results']) === true) {
                $results = array_merge(array1: $decodedResponse['results'], array2: $results);
            } else if (isset($decodedResponse['items']) === true) {
                $results = array_merge(array1: $decodedResponse['items'], array2: $results);
            } else if (isset($decodedResponse['data']) === true) {
                $results = array_merge(array1: $decodedResponse['data'], array2: $results);
            } else if (isset($decodedResponse['result']['instance']['rows']) === true) {
                $results = array_merge(array1: $decodedResponse['result']['instance']['rows'], array2: $results);
            } else if (isset($decodedResponse[0]) === true) {
                $results = array_merge(array1: $decodedResponse, array2: $results);
            }
        }//end while

        return $results;

    }//end getAllResults()
}//end class
