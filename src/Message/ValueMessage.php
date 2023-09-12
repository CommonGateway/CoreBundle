<?php
/**
 * A message to run caches asynchrounous
 *
 * @author Robert Zondervan (robert@conduction.nl)
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 */
namespace CommonGateway\CoreBundle\Message;

use Ramsey\Uuid\UuidInterface;

class ValueMessage
{

    /**
     * @var UuidInterface The id of the value to check.
     */
    private UuidInterface $valueId;

    /**
     * Constructor.
     *
     * @param UuidInterface $valueId The id of the value to check./
     */
    public function __construct(UuidInterface $valueId)
    {
        $this->valueId = $valueId;

    }//end __construct()

    /**
     * Get the id of the value.
     *
     * @return UuidInterface The id of the value to check.
     */
    public function getValueId(): UuidInterface
    {
        return $this->objectEntityId;

    }//end getObjectEntityId()
}//end class
