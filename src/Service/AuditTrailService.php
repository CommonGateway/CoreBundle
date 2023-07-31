<?php

namespace CommonGateway\CoreBundle\Service;

use Adbar\Dot;
use App\Entity\AuditTrail;
use App\Entity\ObjectEntity;
use App\Entity\Unread;
use Doctrine\ORM\EntityManagerInterface;
use Safe\DateTime;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * This service manages the creation of Audit Trails.
 *
 * @author Wilco Louwerse <wilco@conduction.nl>, Robert Zondervan <robert@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class AuditTrailService
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
     * The Read Unread service.
     *
     * @var ReadUnreadService
     */
    private ReadUnreadService $readUnreadService;

    /**
     * @param Security               $security          Security for getting the current user
     * @param EntityManagerInterface $entityManager     The entity manager
     * @param ReadUnreadService      $readUnreadService The Read Unread service
     */
    public function __construct(Security $security, EntityManagerInterface $entityManager, ReadUnreadService $readUnreadService)
    {
        $this->security          = $security;
        $this->entityManager     = $entityManager;
        $this->readUnreadService = $readUnreadService;

    }//end __construct()

    /**
     * Creates an Audit Trail for the given Object and the current user.
     *
     * @param ObjectEntity $object An ObjectEntity to create an Audit Trail for.
     * @param array        $config Extra configuration that should contain an 'action' (LIST, RETRIEVE, CREATE, UPDATE, PARTIAL_UPDATE, DELETE), a 'result' (HTTP status code) and if needed a 'new' and 'old' body.
     *
     * @return AuditTrail|null The created Audit Trail
     */
    public function createAuditTrail(ObjectEntity $object, array $config): ?AuditTrail
    {
        if ($object->getEntity() === null || $object->getEntity()->getCreateAuditTrails() === false) {
            return null;
        }

        $auditTrail = new AuditTrail();
        if ($object->getEntity() !== null
            && $object->getEntity()->getCollections()->first() !== false
        ) {
            $auditTrail->setSource($object->getEntity()->getCollections()->first()->getPrefix());
        }

        $auditTrail = $this->setAuditTrailUser($auditTrail);

        $auditTrail->setAction($config['action']);
        $auditTrail->setActionView($config['action']);
        $auditTrail->setResult($config['result']);
        $auditTrail->setResource($object->getId()->toString());
        $auditTrail->setResourceUrl($object->getUri());
        $auditTrail->setResourceView($object->getName());

        if (isset($config['new'], $config['old']) === true) {
            $auditTrail->setAmendments(['new' => $config['new'], 'old' => $config['old']]);
        }

        if ($config['action'] === 'RETRIEVE' && $config['result'] === 200) {
            $this->readUnreadService->removeUnreads($object);
        }

        $this->entityManager->persist($auditTrail);
        $this->entityManager->flush();

        return $auditTrail;

    }//end createAuditTrail()

    /**
     * Adds some user related information to an Audit Trail.
     *
     * @param AuditTrail $auditTrail The Audit Trial to update.
     *
     * @return AuditTrail The updated Audit Trail.
     */
    private function setAuditTrailUser(AuditTrail $auditTrail): AuditTrail
    {
        $userId = null;
        $user   = null;

        if ($this->security->getUser() !== null) {
            $userId = $this->security->getUser()->getUserIdentifier();
            $user   = $this->entityManager->getRepository('App:User')->find($userId);
        }

        $auditTrail->setApplicationId('Anonymous');
        $auditTrail->setApplicationView('Anonymous');
        $auditTrail->setUserId('Anonymous');
        $auditTrail->setUserView('Anonymous');

        if ($user !== null) {
            $auditTrail->setApplicationId($user->getApplications()->first()->getId()->toString());
            $auditTrail->setApplicationView($user->getApplications()->first()->getName());
            $auditTrail->setUserId($userId);
            $auditTrail->setUserView($user->getName());
        }

        return $auditTrail;

    }//end setAuditTrailUser()
}//end class
