<?php

namespace CommonGateway\CoreBundle\Service\Cache;

/**
 * Interface to unify databases between data stores.
 *
 * @Author Ruben van der Linde <ruben@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>, Robert Zondervan <robert@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category DataStore
 */
interface DatabaseInterface
{
    /**
     * Get a collection for the given name.
     *
     * @param  string $collectionName The name of the collection.
     * @return CollectionInterface The resulting colleciton.
     */
    public function __get(string $collectionName): CollectionInterface;
}//end interface
