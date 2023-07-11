<?php

namespace CommonGateway\CoreBundle\Service;

use Adbar\Dot;
use App\Entity\ObjectEntity;
use App\Entity\Unread;
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

    /**
     * @param Security               $security
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(Security $security, EntityManagerInterface $entityManager)
    {
        $this->security       = $security;
        $this->entityManager  = $entityManager;

    }//end __construct()
    
    /**
     * Adds dateRead to a response, specifically the given metadata array, using the given ObjectEntity to determine the correct dateRead.
     *
     * @param array        $metadata The metadata of the ObjectEntity we are adding dateRead to.
     * @param ObjectEntity $objectEntity The ObjectEntity we are adding the last date read for.
     * @param bool         $getItem If the call done was a get item call we always want to set dateRead to now.
     *
     * @return void
     */
    public function addDateRead(array $metadata, ObjectEntity $objectEntity, bool $getItem = false): array
    {
        // If the api-call is an getItem call show NOW instead!
        if ($getItem === true) {
            $value = new DateTime();
        } else {
            $value = $this->getDateRead($objectEntity);
        }
        
        if ($value !== null) {
            $value = $value->format('c');
        }
        
        $metadata['dateRead'] = $value;
        
        return $metadata;
    }//end addDateRead()
    
    /**
     * Get the last date read for the given ObjectEntity, for the current user. (uses sql to search in audit trails).
     *
     * @param ObjectEntity $objectEntity The ObjectEntity we are checking the last date read for.
     *
     * @return DateTimeInterface|null
     */
    private function getDateRead(ObjectEntity $objectEntity): ?DateTimeInterface
    {
        $user = $this->security->getUser();
        if ($user === null) {
            return null;
        }
        
        // First, check if there is an Unread object for this Object+User. If so, return null.
        $unreads = $this->entityManager->getRepository('App:Unread')->findBy(['object' => $objectEntity, 'userId' => $user->getUserIdentifier()]);
        if (!empty($unreads)) {
            return null;
        }
        
        //todo:
        // Use sql to find last get item audit trail of the current user for the given object.
//        $logs = $this->entityManager->getRepository('App:Log')->findDateRead($objectEntity->getId()->toString(), $user->getUserIdentifier());

//        if (!empty($logs) and $logs[0] instanceof Log) {
//            return $logs[0]->getDateCreated();
//        }
        
        return null;
    }//end getDateRead()
    
    /**
     * Checks if there exists an unread object for the given ObjectEntity + current UserId. If not, creation one.
     *
     * @param ObjectEntity $objectEntity The ObjectEntity we are creating an Unread object for.
     *
     * @return void
     */
    public function setUnread(ObjectEntity $objectEntity)
    {
        // First, check if there is an Unread object for this Object+User. If so, do nothing.
        $user = $this->security->getUser();
        if ($user !== null) {
            $unreads = $this->entityManager->getRepository('App:Unread')->findBy(['object' => $objectEntity, 'userId' => $user->getUserIdentifier()]);
            if (empty($unreads)) {
                $unread = new Unread();
                $unread->setObject($objectEntity);
                $unread->setUserId($user->getUserIdentifier());
                $this->entityManager->persist($unread);
                // Do not flush, will always be done after the api-call that triggers this function, if that api-call doesn't throw an exception.
            }
        }
    }//end setUnread()
}//end class
