<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Gateway as Source;
use GuzzleHttp\Psr7\Response;

class ProxyService
{
    public function __construct(
        private readonly CallService $callService,
        private readonly MappingService $mappingService,
        private readonly EntityManagerInterface $entityManager,
        private readonly Logger $callLogger
    ){

    }

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
            $mapping = $this->entityManager->getRepository(Mapping::class)->findOneBy(['reference' => $endpointConfigOut[$configKey]['mapping']]);
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
            $mapping = $this->entityManager->getRepository(Mapping::class)->findOneBy(['reference' => $endpointConfigIn[$responseProperty]['mapping']]);
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
     * Handles the endpointsConfig of a Source after we did an api-call.
     * See FileSystemService->handleEndpointsConfigIn() for how we handle this on FileSystem sources.
     *
     * @param string         $endpoint        The endpoint used to do an api-call on the source.
     * @param Response|null  $response        The response of an api-call we might want to change.
     * @param Exception|null $exception       The Exception thrown as response of an api-call that we might want to change.
     * @param string|null    $responseContent The response content of an api-call that threw an Exception that we might want to change.
     *
     * @throws Exception
     *
     * @return Response The response.
     */
    private function handleEndpointsConfigIn(string $endpoint, ?Response $response, ?Exception $exception = null, ?string $responseContent = null): Response
    {
        $this->callLogger->info('Handling incoming configuration for endpoints');
        $endpointsConfig = $this->source->getEndpointsConfig();
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

            // Todo: handle content-type.
            is_array($body) === true && $body = json_encode($body);

            return new Response($response->getStatusCode(), $headers, $body, $response->getProtocolVersion());
        }

        return $response;

    }//end handleEndpointsConfigIn()


    public function callProxy (
        Source $source,
        string $endpoint,
        string $method,
        array $config = [],
    ): Response
    {
        $endpointsConfig = $source->getEndpointsConfig();

        if (empty($endpointsConfig)=== true
            || (array_key_exists($endpoint, $endpointsConfig) === false
                && array_key_exists('global', $endpointsConfig) === false
            )
        ) {
            return $this->callService->call(source: $source, endpoint: $endpoint, method: $method, config: $config);;
        }

        if(array_key_exists($endpoint, $endpointsConfig) === true) {
            $endpointConfig = $endpointsConfig[$endpoint];
        } else if (array_key_exists('global', $endpointsConfig) === true) {
            $endpointConfig = $endpointsConfig['global'];
        }

        if(isset($endpointConfig['out']) === true) {
            // TODO: dit reduceren tot één functiecall
            $config = $this->handleEndpointConfigOut(config: $config, endpointConfigOut: $endpointConfig['out'], configKey: 'query');
            $config = $this->handleEndpointConfigOut(config: $config, endpointConfigOut: $endpointConfig['out'], configKey: 'headers');
            $config = $this->handleEndpointConfigOut(config: $config, endpointConfigOut: $endpointConfig['out'], configKey: 'body');
        }

        try {
            $response = $this->callService->call(source: $source, endpoint: $endpoint, method: $method, config: $config);
        } catch(ServerException | ClientException | RequestException | Exception $exception) {
            // TODO
        }

        if(isset($endpointConfig['in']) === true) {
            $response = $this->handleEndpointsConfigIn(endpoint: $endpoint, response: $response);
        }

        return $response;
    }
}