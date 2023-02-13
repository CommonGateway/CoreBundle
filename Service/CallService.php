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
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\YamlEncoder;

/**
 * Service to call external sources.
 *
 * This service provides a guzzle wrapper to work with sources in the common gateway.
 *
 * @Author Robert Zondervan <robert@conduction.nl>, Ruben van der Linde <ruben@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class CallService
{
    /**
     * @var AuthenticationService The authentication service.
     */
    private AuthenticationService $authenticationService;

    /**
     * @var Client The MongoDB client.
     */
    private Client $client;

    /**
     * @var EntityManagerInterface The entity manager.
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var FileService The file service.
     */
    private FileService $fileService;

    /**
     * @var LoggerInterface The logger interface.
     */
    private LoggerInterface $logger;

    /**
     * @param AuthenticationService  $authenticationService The authentication service.
     * @param EntityManagerInterface $entityManager         The entity manager.
     * @param FileService            $fileService           The file service.
     * @param LoggerInterface        $callLogger            The logger interface.
     */
    public function __construct(
        AuthenticationService $authenticationService,
        EntityManagerInterface $entityManager,
        FileService $fileService,
        LoggerInterface $callLogger
    ) {
        $this->authenticationService = $authenticationService;
        $this->client = new Client([]);
        $this->entityManager = $entityManager;
        $this->fileService = $fileService;
    }//end __construct()

    /**
     * Writes the certificate and ssl keys to disk, returns the filenames.
     *
     * @param array $config The configuration as stored in the source.
     *
     * @return array The overrides on the configuration with filenames instead of certificate contents.
     */
    public function getCertificate(array $config): array
    {
        $configs = [];
        if (isset($config['cert'])) {
            $configs['cert'] = $this->fileService->writeFile('certificate', $config['cert']);
        }
        if (isset($config['ssl_key'])) {
            $configs['ssl_key'] = $this->fileService->writeFile('privateKey', $config['ssl_key']);
        }
        if (isset($config['verify']) && is_string($config['verify'])) {
            $configs['verify'] = $this->fileService->writeFile('verify', $config['ssl_key']);
        }

        return $configs;
    }

    /**
     * Removes certificates and private keys from disk if they are not necessary anymore.
     *
     * @param array $config The configuration with filenames.
     *
     * @return void Nothing.
     */
    public function removeFiles(array $config): void
    {
        if (isset($config['cert'])) {
            $this->fileService->removeFile($config['cert']);
        }
        if (isset($config['ssl_key'])) {
            $this->fileService->removeFile($config['ssl_key']);
        }
        if (isset($config['verify']) && is_string($config['verify'])) {
            $this->fileService->removeFile($config['verify']);
        }
    }

    /**
     * Removes empty headers and sets array to string values.
     *
     * @param array $headers Http headers.
     *
     * @return array|null Headers array.
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
     * @param bool   $asynchronous       Whether to call the source asynchronously.
     * @param bool   $createCertificates If we should create Certificates.
     *
     * @return Response The response of the call from the given source.
     */
    public function call(
        Source $source,
        string $endpoint = '',
        string $method = 'GET',
        array $config = [],
        bool $asynchronous = false,
        bool $createCertificates = true
    ): Response {
        if (!$source->getIsEnabled()) {
            throw new HttpException('409', "This source is not enabled: {$source->getName()}");
        }
        if ($source->getConfiguration()) {
            $config = array_merge_recursive($config, $source->getConfiguration());
        }

        // Set authenticion if needed
        $parsedUrl = parse_url($source->getLocation());

        $config = array_merge_recursive($this->getAuthentication($source), $config);
        $createCertificates && $config = array_merge($config, $this->getCertificate($config));
        $config['headers']['host'] = $parsedUrl['host'];
        $config['headers'] = $this->removeEmptyHeaders($config['headers']);

        $url = $source->getLocation().$endpoint;

        $startTimer = microtime(true);
        // Lets make the call
        try {
            if (!$asynchronous) {
                $response = $this->client->request($method, $url, $config);
            } else {
                $response = $this->client->requestAsync($method, $url, $config);
            }
        } catch (ServerException|ClientException|RequestException|Exception $e) {
            $this->logger->error($e->getMessage());
            // Todo: log something more? like response time, status code and response headers/body ?
            // Todo: because we wont reach the info log below...

            throw $e;
        } catch (GuzzleException $e) {
            $this->logger->error($e->getMessage());

            // Todo: do we want to continue at this point?
        }
        $stopTimer = microtime(true);

        $responseClone = clone $response;

        $this->logger->info('Made external call', [
            'source'             => $source->getId()->toString(),
            'endpoint'           => $source->getLocation().$endpoint,
            'method'             => $method,
            'config'             => $config,
            'requestBody'        => $config['body'] ?? null,
            'requestHeaders'     => $config['headers'] ?? null,
            'responseBody'       => $config['body'] ?? null,
            'responseHeaders'    => $responseClone->getHeaders(),
            'responseStatus'     => $responseClone->getStatus(),
            'responseStatusCode' => $responseClone->getStatusCode(),
            'responseTime'       => $stopTimer - $startTimer,
        ]);

        $createCertificates && $this->removeFiles($config);

        return $response;
    }

    /**
     * Determine the content type of response.
     *
     * @param Response $response The response to determine the content type for.
     * @param Source   $source   The source that has been called to create the response.
     *
     * @return string The (assumed) content type of the response.
     */
    private function getContentType(Response $response, Source $source): string
    {

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
     * @param Source   $source   The source that has been called.
     * @param Response $response The response to decode.
     *
     * @throws Exception Thrown if the response does not fit any supported content type.
     *
     * @return array The decoded response.
     */
    public function decodeResponse(
        Source $source,
        Response $response,
        ?string $contentType = 'application/json'
    ): array {
        // resultaat omzetten

        // als geen content-type header dan content-type header is accept header
        $responseBody = $response->getBody()->getContents();
        if (!$responseBody) {
            return [];
        }

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
        } catch (Exception $exception) {
            throw new Exception('Could not decode body, content type could not be determined');
        }
    }

    /**
     * Determines the authentication procedure based upon a source.
     *
     * @param Source $source The source to base the authentication procedure on.
     *
     * @return array The config parameters needed to authenticate on the source.
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
     * @param Source $source   The source to call.
     * @param string $endpoint The endpoint on the source to call.
     * @param array  $config   The additional configuration to call the source.
     *
     * @return array The array of results.
     */
    public function getAllResults(Source $source, string $endpoint = '', array $config = []): array
    {
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
                $previousResult = $decodedResponse;
            } catch (Exception $exception) {
                $errorCount++;
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
