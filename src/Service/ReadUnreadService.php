<?php

namespace CommonGateway\CoreBundle\Service;

use Adbar\Dot;
use App\Entity\AuditTrail;
use App\Entity\ObjectEntity;
use App\Entity\Unread;
use Doctrine\ORM\EntityManagerInterface;
use Safe\DateTime;
use Symfony\Component\Security\Core\Security;

/**
 * This service manages reading if an ObjectEntity is read/unread and marking an ObjectEntity as read/unread.
 *
 * @author Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class ReadUnreadService
{

    /**
     * Security for getting the current user.
     *
     * @var Security
     */
    private Security $security;

    /**
     * The entity manager.
     *
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @param Security               $security      Security for getting the current user
     * @param EntityManagerInterface $entityManager The entity manager
     */
    public function __construct(Security $security, EntityManagerInterface $entityManager)
    {
        $this->security      = $security;
        $this->entityManager = $entityManager;

    }//end __construct()

    /**
     * Adds dateRead to a response, specifically the given metadata array, using the given ObjectEntity to determine the correct dateRead.
     *
     * @param array        $metadata     The metadata of the ObjectEntity we are adding dateRead to.
     * @param ObjectEntity $objectEntity The ObjectEntity we are adding the last date read for.
     * @param bool         $getItem      If the call done was a get item call we always want to set dateRead to now.
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
        $userId = 'Anonymous';
        if ($user !== null) {
            $userId = $user->getUserIdentifier();
        }

        // First, check if there is an Unread object for this Object+User. If so, return null.
        $unreads = $this->entityManager->getRepository('App:Unread')->findBy(['object' => $objectEntity, 'userId' => $userId]);
        if (empty($unreads) === false) {
            return null;
        }

        // Use sql to find last get item audit trail of the current user for the given object.
        // But only if this audit trail was created after the object was last modified.
        $auditTrails = $this->entityManager->getRepository('App:AuditTrail')->findDateRead($objectEntity->getId()->toString(), $userId);
        if (empty($auditTrails) === false
            && $auditTrails[0] instanceof AuditTrail
            && $auditTrails[0]->getCreationDate() > $objectEntity->getDateModified()
        ) {
            return $auditTrails[0]->getCreationDate();
        }
        
        return null;

    }//end getDateRead()

    /**
     * Marks the given ObjectEntity for the current user as read. Currently, already/also automatically done in the AuditTrailService after a Get Item call.
     *
     * @param AuditTrailService The Audit Trail service. Do not set this service in the constructor,
     * because this will create a construct loop with AuditTrailService!
     * @param string $identification The identification of the ObjectEntity we are setting / updating the last dateRead for.
     *
     * @return void
     */
    public function setDateRead(AuditTrailService $auditTrailService, string $identification)
    {
        $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $identification]);
        
        $this->removeUnreads($objectEntity);
    
        $auditTrailConfig = [
            'action' => 'READ',
            'result' => 200,
        ];
    
        $auditTrailService->createAuditTrail($objectEntity, $auditTrailConfig);
    }//end setDateRead()

    /**
     * Checks if there exists an unread object for the given ObjectEntity + current UserId. If not, create one.
     *
     * @param ObjectEntity $objectEntity The ObjectEntity we are creating an Unread object for.
     *
     * @return void
     */
    public function setUnread(ObjectEntity $objectEntity)
    {
        // First, check if there is an Unread object for this Object+User. If so, do nothing.
        $user = $this->security->getUser();
        $userId = 'Anonymous';
        if ($user !== null) {
            $userId = $user->getUserIdentifier();
        }
        
        $unreads = $this->entityManager->getRepository('App:Unread')->findBy(['object' => $objectEntity, 'userId' => $userId]);
        if (empty($unreads) === false) {
            return;
        }

        $unread = new Unread();
        $unread->setObject($objectEntity);
        $unread->setUserId($userId);
        $this->entityManager->persist($unread);
        // Do not flush, will always be done after the api-call that triggers this function, if that api-call doesn't throw an exception.

    }//end setUnread()

    /**
     * After a successful get item call we want to remove unread objects for the logged-in user, this function removes all unread objects for the current user + given object.
     *
     * @param ObjectEntity $objectEntity The ObjectEntity we are removing Unread objects for.
     *
     * @return void
     */
    public function removeUnreads(ObjectEntity $objectEntity)
    {
        $user = $this->security->getUser();
        $userId = 'Anonymous';
        if ($user !== null) {
            $userId = $user->getUserIdentifier();
        }

        // Check if there exist Unread objects for this Object+User. If so, delete them.
        $unreads = $this->entityManager->getRepository('App:Unread')->findBy(['object' => $objectEntity, 'userId' => $userId]);
        foreach ($unreads as $unread) {
            $this->entityManager->remove($unread);
        }

    }//end removeUnreads()
}//end class
