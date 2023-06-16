<?php

// CommonGateway/CoreBundle/CoreBundle.php
/*
 * This file is part of the Conduction Common Ground Bundle
 *
 * @author Conduction <info@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @category Bundle
 */

namespace CommonGateway\CoreBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class CoreBundle extends Bundle
{


    /**
     * Returns the path the bundle is in.
     *
     * @return string
     */
    public function getPath(): string
    {
        return \dirname(__DIR__);

    }//end getPath()


}//end class
