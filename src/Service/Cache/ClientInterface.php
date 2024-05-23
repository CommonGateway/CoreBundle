<?php

namespace CommonGateway\CoreBundle\Service\Cache;
/**
 * Interface to unify datastore clients
 *
 * @Author Ruben van der Linde <ruben@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>, Robert Zondervan <robert@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category DataStore
 */
interface ClientInterface
{
    /**
     * Get a database for given name.
     *
     * @param  string $databaseName
     * @return DatabaseInterface
     */
    public function __get(string $databaseName): DatabaseInterface;
}//end interface
