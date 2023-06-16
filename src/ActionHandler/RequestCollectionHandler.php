<?php

namespace CommonGateway\CoreBundle\ActionHandler;

use CommonGateway\CoreBundle\Service\RequestService;

class RequestCollectionHandler implements ActionHandlerInterface
{

    /**
     * @var RequestService $requestService
     */
    private RequestService $requestService;


    /**
     * __construct
     */
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
            '$id'        => 'https://commongateway.nl/ActionHandler/RequestCollectionHandler.ActionHandler.json',
            '$schema'    => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
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

    }//end getConfiguration()


    /**
     * This function runs the collectionRequest service plugin.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->requestService->collectionRequestHandler($data, $configuration);

    }//end run()


}//end class
