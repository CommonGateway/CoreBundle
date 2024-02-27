<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Gateway as Source;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Twig\Error\LoaderError;

class ProxyService
{
    /**
     * @var Source|null The source that we are current working with.
     */
    private ?Source $source = null;

    /**
     * @param CallService $callService The call service.
     * @param MappingService $mappingService The mapping service.
     * @param EntityManagerInterface $entityManager The entity manager.
     * @param LoggerInterface $callLogger The logger for call related logs.
     */
    public function __construct(
        private readonly CallService $callService,
        private readonly MappingService $mappingService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $callLogger
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

        if(array_key_exists(key: 'mapping', array: $endpointConfigOut[$configKey]) === false) {
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
     * @param array $endpointConfigIn
     * @param Response|null $response The response of an api-call we might want to change.
     * @param Exception|null $exception The Exception thrown as response of an api-call that we might want to change.
     * @param string|null $responseContent The response content of an api-call that threw an Exception that we might want to change.
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
     * @param Source $source    The source to send the request to.
     * @param string $endpoint  The endpoint on the proxy to send the request to.
     * @param string $method    The method of the request to send.
     * @param array $config     The configuration to use with the request.
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
    ): Response {
        $endpointsConfig = $source->getEndpointsConfig();

        if (empty($endpointsConfig) === true
            || (array_key_exists(key: $endpoint, array: $endpointsConfig) === false
                && array_key_exists(key: 'global', array: $endpointsConfig) === false)
        ) {
            return $this->callService->call(source: $source, endpoint: $endpoint, method: $method, config: $config);
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
}//end class
