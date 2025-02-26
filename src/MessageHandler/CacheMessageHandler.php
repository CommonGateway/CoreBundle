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
use CommonGateway\CoreBundle\Message\CacheMessage;
use App\Repository\ActionRepository;
use App\Repository\ObjectEntityRepository;
use CommonGateway\CoreBundle\Service\CacheService;
use CommonGateway\CoreBundle\Subscriber\ActionSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class CacheMessageHandler implements MessageHandlerInterface
{

    private CacheService $cacheService;

    private ObjectEntityRepository $repository;

    private EntityManagerInterface $entityManager;

    public function __construct(CacheService $cacheService, ObjectEntityRepository $repository, EntityManagerInterface $entityManager)
    {
        $this->cacheService  = $cacheService;
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
    public function __invoke(CacheMessage $message): void
    {
        $object = $this->repository->find($message->getObjectEntityId());

        if ($message->getApplication() !== null) {
            $this->session->set('application', $message->getApplication());
        }

        try {
            if ($object instanceof ObjectEntity) {
                $this->cacheService->cacheObject($object);
            }
        } catch (Exception $exception) {
            throw $exception;
        }

    }//end __invoke()
}//end class
