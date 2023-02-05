<?php

namespace CommonGateway\CoreBundle\Installer;

interface InstallerInterface
{
    /**
     * @return mixed
     */
    public function install();

    /**
     * @return mixed
     */
    public function update();

    /**
     * @return mixed
     */
    public function uninstall();
}
