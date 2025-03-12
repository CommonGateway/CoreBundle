<?php

namespace CommonGateway\CoreBundle\ActionHandler;

use CommonGateway\CoreBundle\Service\RequestService;

class RequestProxyHandler implements ActionHandlerInterface
{

    /**
     * @var RequestService
     */
    private RequestService $requestService;

    /**
     * @param RequestService $requestService The RequestService.
     */
    public function __construct(RequestService $requestService)
    {
        $this->requestService = $requestService;

    }//end __construct()

    /**
     *  This function returns the required configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @return array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'        => 'https://commongateway.nl/ActionHandler/RequestProxyHandler.ActionHandler.json',
            '$schema'    => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'      => 'SearchRequestHandler',
            'required'   => [],
            'properties' => [
                'source' => [
                    'type'        => 'url',
                    'description' => 'The reference to the source to proxy data to.',
                    'example'     => 'https://common-gateway.nl/testproxy.proxy.json',
                    'nullable'    => true,
                ],                'endpoint' => [
                    'type'        => 'string',
                    'description' => 'The reference to the source to proxy data to.',
                    'example'     => '/v1/api/zaken',
                    'nullable'    => true,
                ],
                'useContentType' => [
                    'type'        => 'boolean',
                    'description' => 'The reference to the source to proxy data to.',
                    'example'     => false,
                    'nullable'    => true,
                ],
            ],
        ];

    }//end getConfiguration()

    /**
     * This function runs the search request service plugin.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->requestService->proxyRequestHandler($data, $configuration);

    }//end run()
}//end class
