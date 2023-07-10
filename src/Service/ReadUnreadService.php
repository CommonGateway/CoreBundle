<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Read;
use Adbar\Dot;
use Doctrine\ORM\EntityManagerInterface;
use Safe\DateTime;
use Symfony\Component\Security\Core\Security;

/**
 * This service manages the setting of read or unread for a resource, internal or external.
 *
 * @author Ruben van der Linde <ruben@conduction.nl>, Robert Zondervan <robert@conduction.nl>
 */
class ReadUnreadService
{

    /**
     * @var Security
     */
    private Security $security;

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    private MappingService $mappingService;

    public function __construct(Security $security, EntityManagerInterface $entityManager, $mappingService)
    {
        $this->security       = $security;
        $this->entityManager  = $entityManager;
        $this->mappingService = $mappingService;

    }//end __construct()
}//end class

public function getIdentifier(array $data): string
{
    $endpoint = $data['endpoint'];
    $path     = $data['path'];

    if ($endpoint->getProxy() !== null) {
        return rtrim($endpoint->getProxy()->getLocation(), '/').'/'.implode('/', $path);
    } else {
        return end($path);
    }

}//end getIdentifier()

public function readHandler(array $data, array $config): array
{
    $identifier = $this->getIdentifier($data);
    $userId     = $this->security->getUser()->getUserId();

    if ($this->entityManager->getRepository('App:Read')->findOneBy(['userId' => $userId, 'objectId' => $identifier]) !== null) {
        return $data;
    }

    $readObject = new Read();
    $readObject->setObjectId($identifier);
    $readObject->setUserId($userId);
    $readObject->setDateRead(new DateTime());

    $this->entityManager->persist($readObject);
    $this->entityManager->flush();

    return $data;

}//end readHandler()

public function checkReadHandler(array $data, array $config): array
{
    $identifier = $this->getIdentifier($data);
    $userId     = $this->security->getUser()->getUserId();
    $mapping    = '';

    if (in_array($identifier, $config['collection_endpoints'])) {
        // TODO: partial match between objectId and the id in the read should suffice here.
        $reads = $this->entityManager->getRepository('App:Read')->findBy(['userId' => $userId, 'objectId' => "$identifier%"]);

        $responseEncoded = $data['response']->getContents();
        $response        = new Dot(\Safe\json_decode($responseEncoded, true));

        foreach ($response->get($config['objectsPath'])->toArray() as $key => $object) {
            $object['reads'] = $reads;
            $this->mappingService->mapping($mapping, $object);
        }
    } else {
        $reads = $this->entityManager->getRepository('App:Read')->findBy(['userId' => $userId, 'objectId' => $identifier]);
    }

}//end checkReadHandler()
}
