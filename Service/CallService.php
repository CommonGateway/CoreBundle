<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Gateway as Source;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class CallService
{
    private AuthenticationService $authenticationService;
    private Client $client;

    public function __construct(AuthenticationService $authenticationService) {
        $this->authenticationService = $authenticationService;
        $this->client = new Client([]);

    }

    public function call(
        Source $source,
        string $endpoint = '',
        string $method ='GET',
        array $config = [],
        bool $asynchronous = false
    ): Response
    {
        // Set authenticion if needed
        $config = array_merge_recursive($config, $this->getAuthentication($source));

        // Lets start up a default client
        $client = new Client($config);

        $url = $source->getLocation().$endpoint;

        // Lets make the call
        try {
            if (!$asynchronous) {
                $response = $this->client->request($method, $url, $config);
            }
            else {
                $response = $this->client->requestAsync($method, $url, $config);
            }
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            throw $e;
        }

        return $response;
    }

    private function getContentType(Response $response, Source $source): string
    {

        // switch voor obejct
        $contentType = $response->getHeader('content-type')[0];

        if(!$contentType) {
            $contentType = $source->getAccept();
        }

        return $contentType;
    }


    public function decodeResponse(
        Source $source,
        Response $response
    ): array
    {
        // resultaat omzetten

        // als geen content-type header dan content-type header is accept header
        $responseBody= $response->getBody()->getContents();
        if (!$responseBody) {
            return [];
        }

        $xmlEncoder = new XmlEncoder(['xml_root_node_name' => $this->configuration['apiSource']['location']['xmlRootNodeName'] ?? 'response']);
        $contentType = $this->getContentType($response, $source);
        switch ($contentType) {
            case 'text/xml':
            case 'text/xml; charset=utf-8':
            case 'application/xml':
                $result = $xmlEncoder->decode($responseBody, 'xml');
            case 'application/json':
            default:
                $result = json_decode($responseBody, true);
        }

        if($result) {
            return $result;
        }

        // Fallback: if the json_decode didn't work, try to decode XML, if that doesn't work an error is thrown.
        try{
            $result = $xmlEncoder->decode($responseBody, 'xml');
            return $result;
        } catch (\Exception $exception) {
            throw new \Exception('Could not decode body, content type could not be determined');
        }

    }

    private function getAuthentication(Source $source): array
    {
        return $this->authenticationService->getAuthentication($source);
    }
}
