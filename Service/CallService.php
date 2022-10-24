<?php

namespace CommonGateway\CoreBundle\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Responce;
use App\Entity\Gateway as Source

class CallService
{
    
    public function call(
        Source $source,
        string $endpoint = '',
        string $method ='GET',
        array $config = [],
        bool $asynchronus = false
    ): Responce
    {
        // Set authenticion if needed
        $config = array_merge($config, $this->getAuthentication($source));

        // Lets start up a default client
        $client = new Client($config);

        $url = $source->getLocation().$endpoint

        // Lets make the call
        try {
            if (!$async) {
            $response = $this->client->request('GET', $url, $config);
            }
            else {
                $response = $this->client->requestAsync('GET', $url, $config);
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            //                var_dump($e->getResponse()->getBody()->getContents()); //Log::error($e->getResponse()->getBody()->getContents());
            throw $e;
        }

        return $response;
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
        $responseBody= $response->getBody()->getContents()
        if (!$responseBody) {
            return [];
        }

        // switch voor obejct
        $contentType = $response->getHeader('content-type')[0];
        switch ($contentType) {
            case 'text/xml':
            case 'text/xml; charset=utf-8':
            case 'application/xml':
                $xmlEncoder = new XmlEncoder(['xml_root_node_name' => $this->configuration['apiSource']['location']['xmlRootNodeName'] ?? 'response']);

                return $xmlEncoder->decode($responseBody, 'xml');
            case 'application/json':
            default:
                return json_decode($responseBody, true);
        }

        // Als accept ook leeg is, probeer json decode, probeer xml decode, sterf
    }

    private getAuthentication($source): array
    {

        return [];
    }
}
