<?php

namespace CommonGateway\CoreBundle\ActionHandler;

use CommonGateway\CoreBundle\Service\RequestService;

/**
 * Handles the ITEM requests on an endpoint.
 */
class RequestItemHandler implements ActionHandlerInterface
{
    /**
     * @var RequestService $requestService The request service.
     */
    private RequestService $requestService;

    /**
     * @param RequestService $requestService The request service.
     */
    public function __construct(RequestService $requestService)
    {
        $this->requestService = $requestService;
    }//end __construct()

    /**
     * This function returns the required configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @return array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'        => 'https://example.com/person.schema.json',
            '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
            'title'      => 'CollectionRequestHandler',
            'required'   => [],
            'properties' => [
                'serviceDNS' => [
                    'type'        => 'string',
                    'description' => 'The DNS of the mail provider, see https://symfony.com/doc/6.2/mailer.html for details',
                    'example'     => 'native://default',
                    'required'    => true,
                ],
            ],
        ];
    }

    /**
     * This function runs the search item service plugin.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->requestService->itemRequestHandler($data, $configuration);
    }
}
