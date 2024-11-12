<?php

namespace CommonGateway\CoreBundle\Service\Cache;

class PostgresqlDatabase implements DatabaseInterface
{
    /**
     * @inheritDoc
     */
    public function __get(string $collectionName): CollectionInterface
    {
        // TODO: Implement __get() method.
    }//end __get()
}//end class
