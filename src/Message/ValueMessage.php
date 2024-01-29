<?php
/**
 * A message to run coupling subobjects asynchrounous
 *
 * @author Robert Zondervan (robert@conduction.nl)
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 */
namespace CommonGateway\CoreBundle\Message;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class ValueMessage
{

    /**
     * @var SessionInterface The current session.
     */
    private SessionInterface $session;

    /**
     * @var UuidInterface The id of the value to check.
     */
    private UuidInterface $valueId;

    /**
     * @var UuidInterface|null The id of the active user while this ValueMessage was created. Used to set the owner & organization of any SubObjects created by handling this ValueMessage. This might be the user set for an Action or Cronjob.
     */
    private ?UuidInterface $userId = null;

    /**
     * Constructor.
     *
     * @param UuidInterface $valueId The id of the value to check./
     */
    public function __construct(SessionInterface $session, UuidInterface $valueId)
    {
        $this->session = $session;
        $this->valueId = $valueId;

        if (Uuid::isValid($this->session->get('user', "")) === true) {
            $this->userId = Uuid::fromString($this->session->get('user'));
        }

    }//end __construct()

    /**
     * Get the id of the value.
     *
     * @return UuidInterface The id of the value to check.
     */
    public function getValueId(): UuidInterface
    {
        return $this->valueId;

    }//end getValueId()

    /**
     * Get the id of the active user while this ValueMessage was created.
     *
     * @return UuidInterface The of the active user while this ValueMessage was created.
     */
    public function getUserId(): UuidInterface
    {
        return $this->userId;

    }//end getUserId()
}//end class
