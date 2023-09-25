<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * This service manages data from ObjectEntities.
 * For now (09-2023) mostly revolving around Multi-tenancy.
 *
 * @author Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class ObjectEntityService
{

    /**
     * The entity manager.
     *
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * Security for getting the current user.
     *
     * @var Security
     */
    private Security $security;

    /**
     * The current session.
     *
     * @var SessionInterface
     */
    private SessionInterface $session;

    /**
     * Some values used for creating test data.
     * Note that owner => reference is replaces with an uuid of that User object.
     *
     * @var array|string[]
     */
    private const DEFAULTS = [
        'organization' => 'https://docs.commongateway.nl/organization/default.organization.json',
        'owner'        => 'https://docs.commongateway.nl/user/default.user.json',
    ];

    /**
     * @param EntityManagerInterface $entityManager The entity manager
     * @param Security               $security      Security for getting the current user
     * @param SessionInterface       $session       The current session.
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        Security $security,
        SessionInterface $session
    ) {
        $this->entityManager = $entityManager;
        $this->security      = $security;
        $this->session       = $session;

    }//end __construct()

    public function setOwnerAndOrg(ObjectEntity $object): ObjectEntity
    {
        $user = null;

        // First check if there is a logged-in user we can get the Owner & Organization from.
        if ($this->security->getUser() !== null) {
            $userId = $this->security->getUser()->getUserIdentifier();
            $user   = $this->entityManager->getRepository('App:User')->find($userId);
        }

        // Check if there is a Cronjob or Action user in the session we can get the Owner & Organization from.
        if (($user === null || $user->getOrganization() === null) && $this->session->get('currentCronjobUserId', false) !== false) {
            $userId = $this->session->get('currentCronjobUserId');
            $user   = $this->entityManager->getRepository('App:User')->find($userId);
        }

        if (($user === null || $user->getOrganization() === null) && $this->session->get('currentActionUserId', false) !== false) {
            $userId = $this->session->get('currentActionUserId');
            $user   = $this->entityManager->getRepository('App:User')->find($userId);
        }

        // Find the correct owner to set.
        if ($user !== null) {
            $owner = $user->getUserIdentifier();
        } else {
            // Default to the Default Owner.
            $defaultUser = $this->entityManager->getRepository('App:User')->findOneBy(['reference' => $this::DEFAULTS['owner']]);
            $owner       = $defaultUser ? $defaultUser->getId()->toString() : $defaultUser;
        }

        // Find the correct Organization to set.
        if ($user->getOrganization() !== null) {
            $organization = $user->getOrganization();
        } else {
            // Default to the Default Organization.
            $organization = $this->entityManager->getRepository('App:Organization')->findOneBy(['reference' => $this::DEFAULTS['organization']]);
        }

        $object->setOwner($owner);
        $object->setOrganization($organization);
        $this->entityManager->persist($object);

        return $object;

    }//end setOwnerAndOrg()
}//end class
