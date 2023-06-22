<?php

namespace CommonGateway\CoreBundle\Installer;

interface InstallerInterface
{
    /**
     * Install
     *
     * @return void Nothing.
     */
    public function install(): void;

    /**
     * Update
     *
     * @return void Nothing.
     */
    public function update(): void;

    /**
     * Uninstall
     *
     * @return void Nothing.
     */
    public function uninstall(): void;
}//end interface
