<?php

namespace CommonGateway\CoreBundle\Installer;

interface InstallerInterface
{
    /**
     * Install
     *
     * @return void Nothing.
     */
    public function install();

    /**
     * Update
     *
     * @return void Nothing.
     */
    public function update();

    /**
     * Uninstall
     *
     * @return void Nothing.
     */
    public function uninstall();
}//end interface
