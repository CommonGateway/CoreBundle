<?php

namespace CommonGateway\CoreBundle\ActionHandler;

use CommonGateway\CoreBundle\Service\ObjectSyncService;

class ObjectSyncHandler implements ActionHandlerInterface
{

    private ObjectSyncService $service;

    public function __construct(ObjectSyncService $service)
    {
        $this->service = $service;

    }//end __construct()

    /**
     *  This function returns the required configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'         => 'https://commongateway.nl/schemas/ObjectSyncHandler/ActionHandler.schema.json',
            '$schema'     => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'       => 'ObjectSyncHandler',
            'description' => 'This action handlers synchronise an object to the default source. Note: This actionHandler will only fire when the listens of the action is set to \'commongateway.object.sync\'',
            'required'    => [],
            'properties'  => [],
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
        return $this->service->objectSyncHandler($data, $configuration);

    }//end run()
}//end class
