<?php

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
        $this->cacheService = $cacheService;
        $this->repository = $repository;
        $this->entityManager = $entityManager;
    }

    public function __invoke(CacheMessage $message): void
    {
        $object = $this->repository->find($message->getObjectEntityId());

        try {
            if ($object instanceof ObjectEntity) {
                $this->cacheService->cacheObject($object);
            }
            $this->entityManager->clear();
        } catch (Exception $exception) {
            $this->entityManager->clear();

            throw $exception;
        }
    }
}
