<?php

namespace CommonGateway\CoreBundle\Installer;

interface InstallerInterface
{
    public function install();

    public function update();

    public function uninstall();
}//end interface
