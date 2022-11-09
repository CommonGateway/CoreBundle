<?php

namespace CommonGateway\CoreBundle\Service;


use CommonGateway\CoreBundle\Service\ComposerService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Command\Command;


class InstallationService
{
    private ComposerService $composerService;
    private ContainerInterface $container;
    private iterable $installers;

    public function __construct(
        iterable $installers,
        ContainerInterface $container
    ) {
        $this->installers = $installers;
        $this->container = $container;
    }

    public function getComposerService(){
        return $this->container->get('CommonGateway\CoreBundle\Service\ComposerService');
    }

    /**
     * Performs installation actions on a common Gataway bundle
     *
     * @param SymfonyStyle $io
     * @param string $bundle
     * @param string|null $data
     * @param bool $noSchema
     * @return int
     */
    public function install(SymfonyStyle $io, string $bundle, ?string $data, bool $noSchema = false):int{

        $io->writeln([
            '<info>Common Gateway Bundle Installer</info>',
            '============',
            '',
            'Trying to install: <comment> '. $bundle.' </comment>',
            '',
        ]);

        $io->writeln($this->getComposerService()->show());

        return Command::SUCCESS;
    }

    public function update(SymfonyStyle $io, string $bundle, string $data){

        $io->writeln([
            'Common Gateway Bundle Updater',
            '============',
            '',
        ]);

        return Command::SUCCESS;

    }

    public function uninstall(SymfonyStyle $io, string $bundle, string $data){

        $io->writeln([
            'Common Gateway Bundle Uninstaller',
            '============',
            '',
        ]);
        return Command::SUCCESS;
    }



}
