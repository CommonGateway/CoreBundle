<?php

namespace CommonGateway\CoreBundle\Subscriber;

use App\Entity\Action;
use App\Entity\User;
use App\Event\ActionEvent;
use App\Exception\AsynchronousException;
use App\Kernel;
use App\Message\ActionMessage;
use App\Service\ObjectEntityService as GatewayObjectEntityService;
use CommonGateway\CoreBundle\Service\ActionService;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use JWadhams\JsonLogic;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

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
