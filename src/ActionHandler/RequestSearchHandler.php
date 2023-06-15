<?php

namespace CommonGateway\CoreBundle\ActionHandler;

use CommonGateway\CoreBundle\Service\RequestService;

class RequestSearchHandler implements ActionHandlerInterface
{

    private RequestService $requestService;


    public function __construct(RequestService $requestService)
    {
        $this->requestService = $requestService;

    }//end __construct()


    /**
     *  This function returns the required configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'        => 'https://commongateway.nl/ActionHandler/RequestSearchHandler.ActionHandler.json',
            '$schema'    => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'      => 'SearchRequestHandler',
            'required'   => [],
            'properties' => [
                'searchEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The uuid of the entity you want to search for',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
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
        return $this->requestService->searchRequestHandler($data, $configuration);

    }//end run()


}//end class
