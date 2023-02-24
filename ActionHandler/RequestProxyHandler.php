<?php

namespace CommonGateway\CoreBundle\ActionHandler;

use CommonGateway\CoreBundle\Service\RequestService;

class RequestProxyHandler implements ActionHandlerInterface
{
    private RequestService $requestService;

    public function __construct(RequestService $requestService)
    {
        $this->requestService = $requestService;
    }

    /**
     *  This function returns the required configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'        => 'https://common-gateway.nl/proxy.actionhandler.json',
            '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
            'title'      => 'SearchRequestHandler',
            'required'   => [],
            'properties' => [
                'source' => [
                    'type'        => 'url',
                    'description' => 'The reference to the source to proxy data to.',
                    'example'     => 'https://common-gateway.nl/testproxy.proxy.json',
                    'nullable'    => true,
                ],
            ],
        ];
    }

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
    }
}
