<?php

namespace CommonGateway\CoreBundle\Service\Cache;

interface ClientInterface
{
    public function __get(string $databaseName): DatabaseInterface;
}//end interface
