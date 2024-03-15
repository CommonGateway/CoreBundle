<?php

namespace CommonGateway\CoreBundle\MessageHandler;

use App\Entity\ObjectEntity;
use App\Entity\Value;
use App\Repository\ValueRepository;
use CommonGateway\CoreBundle\Message\CacheMessage;
use App\Repository\ActionRepository;
use App\Repository\ObjectEntityRepository;
use CommonGateway\CoreBundle\Message\ValueMessage;
use CommonGateway\CoreBundle\Service\CacheService;
use CommonGateway\CoreBundle\Service\ValueService;
use CommonGateway\CoreBundle\Subscriber\ActionSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

/**
 * Handler for cache messages.
 *
 * @author Robert Zondervan <robert@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 */
class ValueMessageHandler implements MessageHandlerInterface
{

    /**
     * @var SessionInterface The current session.
     */
    private SessionInterface $session;

    /**
     * @var LoggerInterface The logger.
     */
    private LoggerInterface $logger;

    /**
     * Constructor.
     *
     * @param ValueService    $valueService The value service.
     * @param ValueRepository $repository   The value repository.
     * @param LoggerInterface $objectLogger The logger.
     * @param RequestStack    $requestStack
     */
    public function __construct(
        private readonly ValueService $valueService,
        private readonly ValueRepository $repository,
        LoggerInterface $objectLogger,
        RequestStack $requestStack
    ) {
        try {
            $this->session = $requestStack->getSession();
        } catch (SessionNotFoundException $exception) {
            $this->session = new Session();
        }

        $this->logger = $objectLogger;

    }//end __construct()

    /**
     * Handles incoming CacheMessage resources.
     *
     * @param CacheMessage $message The incoming message.
     *
     * @return void
     *
     * @throws Exception
     */
    public function __invoke(ValueMessage $message): void
    {
        $value = $this->repository->find($message->getValueId());

        $this->session->remove('valueMessageUserId');
        if ($message->getUserId() !== null) {
            $this->session->set('valueMessageUserId', $message->getUserId()->toString());
        }

        try {
            if ($value instanceof Value === true) {
                $this->valueService->connectSubObjects($value);
            }

            $this->session->remove('valueMessageUserId');
        } catch (Exception $exception) {
            $this->session->remove('valueMessageUserId');
            $this->logger->error("Error while handling a ValueMessage for Value {$message->getValueId()}: ".$exception->getMessage());

            throw $exception;
        }

    }//end __invoke()
}//end class
