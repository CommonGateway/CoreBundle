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

    private UuidInterface $valueId;

    public function __construct(UuidInterface $valueId)
    {
        $this->objectEntityId = $valueId;

    }//end __construct()

    public function getValueId(): UuidInterface
    {
        return $this->objectEntityId;

    }//end getValueId()
}//end class
