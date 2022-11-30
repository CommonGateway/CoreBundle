<?php

namespace CommonGateway\CoreBundle\Installer;

use Symfony\Component\Console\Style\SymfonyStyle;

interface InstallerInterface
{

    public function install();

    public function update();

    public function uninstall();

}
