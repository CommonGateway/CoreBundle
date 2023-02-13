<?php

namespace CommonGateway\CoreBundle\Installer;

interface InstallerInterface
{
    /**
     * @return mixed An installation function.
     */
    public function install();

    /**
     * @return mixed An update function.
     */
    public function update();

    /**
     * @return mixed An uninstall function.
     */
    public function uninstall();
}
