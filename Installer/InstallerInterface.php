<?php

namespace CommonGateway\CoreBundle\Installer;

use Symfony\Component\Console\Style\SymfonyStyle;

interface InstallerInterface
{

    public function install(SymfonyStyle $io);

    public function update(SymfonyStyle $io);

    public function uninstall(SymfonyStyle $io);

}
