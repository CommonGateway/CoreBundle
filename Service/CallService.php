<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Gateway as Source;
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class CallService
{
    
    public function call(
        Source $source,
        string $endpoint = '',
        string $method ='GET',
        array $config = [],
        bool $asynchronous = false
    ): Response
    {
        // Set authenticion if needed
        $config = array_merge($config, $this->getAuthentication($source));

        // Lets start up a default client
        $client = new Client($config);

        $url = $source->getLocation().$endpoint

        // Lets make the call
        try {
            if (!$asynchronous) {
            $response = $this->client->request('GET', $url, $config);
            }
            else {
                $response = $this->client->requestAsync('GET', $url, $config);
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


    public function handleCall(
        Source $source,
        string $endpoint = '',
        string $method ='GET',
        array $config = [],
        bool $asynchronus = false
    ): array
    {
        $response = $this->call($source, $endpoint, $method, $config, $asynchronus);

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

        try{
            $result = $xmlEncoder->decode($responseBody, 'xml');
            return $result;
        } catch (\Exception $exception) {
            throw new \Exception('Could not decode body, content type could not be determined');
        }

        // Als accept ook leeg is, probeer json decode, probeer xml decode, sterf
    }

    private function getAuthentication(Source $source): array
    {

        return [];
    }
}
