<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\ObjectEntity;
use App\Entity\Organization;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
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
 *
 * This service belongs to the open registers framework.
 */
class ObjectEntityService
{

    /**
     * The current session.
     *
     * @var SessionInterface
     */
    private SessionInterface $session;

    /**
     * The default references of some Core Gateway objects.
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
     * @param SessionInterface       $requestStack  The current session.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        RequestStack $requestStack
    ) {

        try {
            $this->session = $requestStack->getSession();
        } catch (SessionNotFoundException $exception) {
            $this->session = new Session();
        }

    }//end __construct()

    /**
     * Finds the current / active User. First checks for a logged-in user, else checks if we have a user in the session.
     *
     * @return User|null The User found or null if not.
     */
    public function findCurrentUser(): ?User
    {
        $user = null;

        // First check if there is a logged-in user we can get the Owner & Organization from.
        if ($this->security->getUser() !== null) {
            $user = $this->entityManager->getRepository(User::class)->find($this->security->getUser()->getUserIdentifier());
        }

        // Check if there is a Cronjob user in the session we can get the Owner & Organization from.
        if (($user === null || $user->getOrganization() === null) && Uuid::isValid($this->session->get('currentCronjobUserId', "")) === true) {
            $user = $this->entityManager->getRepository('App:User')->find($this->session->get('currentCronjobUserId'));
        }

        // Check if there is an Action user in the session we can get the Owner & Organization from.
        // todo: Maybe add config options to Entity of ObjectEntity, in order to always use Action User if possible, even if there is a logged in user?
        if (($user === null || $user->getOrganization() === null) && Uuid::isValid($this->session->get('currentActionUserId', "")) === true) {
            $user = $this->entityManager->getRepository('App:User')->find($this->session->get('currentActionUserId'));
        }

        // Check if there is an ValueMessage user in the session we can get the Owner & Organization from.
        if (($user === null || $user->getOrganization() === null) && Uuid::isValid($this->session->get('valueMessageUserId', "")) === true) {
            $user = $this->entityManager->getRepository('App:User')->find($this->session->get('valueMessageUserId'));
        }

        if ($user !== null) {
            // Set organization id and user id in session
            $this->session->set('user', $user->getId()->toString());
            $this->session->set('organization', $user->getOrganization() !== null ? $user->getOrganization()->getId()->toString() : null);
        }

        return $user;

    }//end findCurrentUser()

    /**
     * Sets the owner and Organization for an ObjectEntity.
     * Will use info of the logged-in user, or a user from session for this.
     * If no user (or Organization) can be found it defaults to the default User and default Organization.
     * todo: maybe add application to this as well, remove setting application form Gateway->SynchronizationService!
     *
     * @param ObjectEntity $object The ObjectEntity to update.
     *
     * @return ObjectEntity The updated ObjectEntity.
     */
    public function setOwnerAndOrg(ObjectEntity $object): ObjectEntity
    {
        $user = $this->findCurrentUser();

        $object = $this->setOwner($object, $user);
        return $this->setOrganization($object, $user);
        // Do not persist here, because this triggers the subscriber that calls this setOwnerAndOrg() function.

    }//end setOwnerAndOrg()

    /**
     * Sets the owner for an ObjectEntity. Will use given user for this.
     * If no user has been given it defaults to the default User.
     *
     * @param ObjectEntity $object The ObjectEntity to update.
     * @param User|null    $user   The user to set as owner for the given ObjectEntity (or null).
     *
     * @return ObjectEntity The updated ObjectEntity.
     */
    private function setOwner(ObjectEntity $object, ?User $user): ObjectEntity
    {
        if ($object->getOwner() !== null) {
            return $object;
        }

        // Find the correct owner to set.
        if ($user !== null) {
            $owner = $user->getId()->toString();
        } else {
            // Default to the Default Owner.
            $defaultUser = $this->entityManager->getRepository(User::class)->findOneBy(['reference' => $this::DEFAULTS['owner']]);
            $owner       = $defaultUser ? $defaultUser->getId()->toString() : $defaultUser;
        }

        $object->setOwner($owner);

        return $object;

    }//end setOwner()

    /**
     * Sets the Organization for an ObjectEntity. Will use given user->organization for this if possible.
     * If no user has been given or if it has no Organization it defaults to the default Organization.
     *
     * @param ObjectEntity $object The ObjectEntity to update.
     * @param User|null    $user   The user to get the Organization from (or null).
     *
     * @return ObjectEntity The updated ObjectEntity.
     */
    private function setOrganization(ObjectEntity $object, ?User $user): ObjectEntity
    {
        if ($object->getOrganization() !== null) {
            return $object;
        }

        // Find the correct Organization to set.
        if ($user !== null && $user->getOrganization() !== null) {
            $organization = $user->getOrganization();
        } else {
            // Default to the Default Organization.
            $organization = $this->entityManager->getRepository(Organization::class)->findOneBy(['reference' => $this::DEFAULTS['organization']]);
        }

        $object->setOrganization($organization);

        return $object;

    }//end setOrganization()
}//end class
