<?php

namespace CommonGateway\CoreBundle\ActionHandler;

use CommonGateway\CoreBundle\Service\NotificationService;
use Exception;

class NotificationHandler implements ActionHandlerInterface
{

    /**
     * @var NotificationService
     */
    private NotificationService $notificationService;

    /**
     * @param NotificationService $notificationService The RequestService.
     */
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;

    }//end __construct()

    /**
     *  This function returns the required configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @return array a [json-schema](https://json-schema.org/) that this action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'        => 'https://commongateway.nl/ActionHandler/NotificationHandler.ActionHandler.json',
            '$schema'    => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'      => 'NotificationHandler',
            'required'   => [
                'urlLocation',
                'entity',
            ],
            'properties' => [
                'urlLocation' => [
                    'type'        => 'string',
                    'description' => 'The name/location of the field (or query or header) in the notification api-call. That contains the full url, of the object that the notification is for, incl. full location (url) of the source itself.',
                    'example'     => 'body.resourceUrl',
                    'required'    => true,
                ],
                'entity'      => [
                    'type'        => 'string',
                    'description' => 'The reference of an Entity/Schema these notifications will be for. Used for creating a synchronization and synchronizing.',
                    'example'     => 'https://vng.opencatalogi.nl/schemas/zrc.zaak.schema.json',
                    'required'    => true,
                ],
            ],
        ];

    }//end getConfiguration()

    /**
     * This function runs the notification service.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array
     * @throws Exception
     */
    public function run(array $data, array $configuration): array
    {
        return $this->notificationService->notificationHandler($data, $configuration);

    }//end run()
}//end class
