<?php

namespace CommonGateway\CoreBundle\Message;

use Ramsey\Uuid\UuidInterface;

/**
 * A message to run coupling subobjects asynchrounous
 *
 * @author Robert Zondervan <robert@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 */
class ValueMessage
{

    /**
     * @var UuidInterface The id of the value to check.
     */
    private UuidInterface $valueId;

    /**
     * @var UuidInterface|null The id of the active user while this ValueMessage was created. Used to set the owner & organization of any SubObjects created by handling this ValueMessage. This might be the user set for an Action or Cronjob.
     */
    private ?UuidInterface $userId;

    /**
     * Constructor.
     *
     * @param UuidInterface $valueId The id of the value to check./
     */
    public function __construct(UuidInterface $valueId, ?UuidInterface $userId, private readonly string $application)
    {
        $this->valueId = $valueId;
        $this->userId  = $userId;

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
    public function getUserId(): ?UuidInterface
    {
        return $this->userId;

    }//end getUserId()
    
    public function getApplication(): string
    {
        $this->application;
    }
}//end class
