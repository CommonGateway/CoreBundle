<?php

namespace CommonGateway\CoreBundle\Installer;

interface InstallerInterface
{


    /**
     * Install
     */
    public function install();


    /**
     * Update
     */
    public function update();


    /**
     * Uninstall
     */
    public function uninstall();


}//end interface
