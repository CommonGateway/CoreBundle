<?php
/**
 * Handler for cache messages.
 *
 * @author Robert Zondervan (robert@conduction.nl)
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 */
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
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class ValueMessageHandler implements MessageHandlerInterface
{

    /**
     * @var ValueService The value service.
     */
    private ValueService $valueService;

    /**
     * @var ValueRepository The value repository.
     */
    private ValueRepository $repository;

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
     * @param ValueService     $valueService The value service.
     * @param ValueRepository  $repository   The value repository.
     * @param SessionInterface $session      The current session.
     * @param LoggerInterface  $objectLogger The logger.
     */
    public function __construct(
        ValueService $valueService,
        ValueRepository $repository,
        SessionInterface $session,
        LoggerInterface $objectLogger
    ) {
        $this->valueService = $valueService;
        $this->repository   = $repository;
        $this->session      = $session;
        $this->logger       = $objectLogger;

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

        if (empty($message->getUserId()) === false) {
            $this->session->set('valueMessageUser', $message->getUserId()->toString());
        }

        try {
            if ($value instanceof Value === true) {
                $this->valueService->connectSubObjects($value);
            }
            $this->session->remove('valueMessageUser');
        } catch (Exception $exception) {
            $this->session->remove('valueMessageUser');
            $this->logger->error("Error while handling a ValueMessage for Value {$message->getValueId()}: ".$exception->getMessage());
            
            throw $exception;
        }

    }//end __invoke()
}//end class
