<?php

namespace CommonGateway\CoreBundle\Subscriber;

use App\Event\ActionEvent;
use CommonGateway\CoreBundle\Service\ActionService;


class ActionSubscriber implements EventSubscriberInterface
{
    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            'commongateway.handler.pre'     => 'handleEvent',
            'commongateway.handler.post'    => 'handleEvent',
            'commongateway.response.pre'    => 'handleEvent',
            'commongateway.cronjob.trigger' => 'handleEvent',
            'commongateway.object.create'   => 'handleEvent',
            'commongateway.object.read'     => 'handleEvent',
            'commongateway.object.update'   => 'handleEvent',
            'commongateway.object.delete'   => 'handleEvent',
            'commongateway.action.event'    => 'handleEvent',

        ];

    }//end getSubscribedEvents()

    public function __construct(
        private ActionService $actionService
    ) {

    }//end __construct()

    /**
     * Handles an action event.
     *
     * @param ActionEvent $event The received event.
     *
     * @return ActionEvent The updated event.
     */
    public function handleEvent(ActionEvent $event): ActionEvent
    {
        return $this->actionService->handleEvent($event);

    }//end handleEvent()
}//end class
