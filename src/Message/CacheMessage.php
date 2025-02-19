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

class CacheMessage
{

    private UuidInterface $objectEntityId;

    public function __construct(UuidInterface $actionId, private readonly string $application)
    {
        $this->objectEntityId = $actionId;

    }//end __construct()

    public function getObjectEntityId(): UuidInterface
    {
        return $this->objectEntityId;

    }//end getObjectEntityId()
    
    public function getApplication(): ?string
    {
        return $this->application;
    }
}//end class
