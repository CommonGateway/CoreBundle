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
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class ValueMessageHandler implements MessageHandlerInterface
{

    private ValueService $valueService;

    private ValueRepository $repository;

    private EntityManagerInterface $entityManager;

    public function __construct(ValueService $valueService, ValueRepository $repository, EntityManagerInterface $entityManager)
    {
        $this->valueService  = $valueService;
        $this->repository    = $repository;
        $this->entityManager = $entityManager;

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

        try {
            if ($value instanceof Value) {
                $this->valueService->connectSubObjects($value);
            }
        } catch (Exception $exception) {
            throw $exception;
        }

    }//end __invoke()
}//end class
