<?php

namespace CommonGateway\CoreBundle\Command;

use CommonGateway\CoreBundle\Service\InstallationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ComposerUpdateCommand extends Command
{
    /**
     * @var string The name of the command (the part after "bin/console").
     */
    protected static $defaultName = 'commongateway:composer:update';

    /**
     * @var InstallationService The installation service.
     */
    private InstallationService $installationService;

    /**
     * @param InstallationService $installationService The installation service.
     */
    public function __construct(InstallationService $installationService)
    {
        $this->installationService = $installationService;
        parent::__construct();
    }//end __construct()

    /**
     * @return void This function doesn't return anything.
     */
    protected function configure(): void
    {
        // Todo: these options are never used? Use them or remove them?
        $this
            ->addOption('bundle', 'b', InputOption::VALUE_OPTIONAL, 'The bundle that you want to install')
            ->addOption('data', 'd', InputOption::VALUE_OPTIONAL, 'Load (example) data set(s) from the bundle', false)
            ->addOption('schema', 'sa', InputOption::VALUE_OPTIONAL, 'Load an (example) data set from the bundle', false)
            ->addOption('script', 'sp', InputOption::VALUE_OPTIONAL, 'Load an (example) data set from the bundle', false)
            ->addOption('unsafe', 'u', InputOption::VALUE_OPTIONAL, 'Update existing schema\'s and data sets', false)
            ->setDescription('This command runs the installation service on a commongateway bundle')
            ->setHelp('This command allows you to run further installation an configuration actions afther installing a plugin');
    }

    /**
     * @param InputInterface  $input  Symfony style input.
     * @param OutputInterface $output Symfony style output.
     *
     * @return int Succes (0) or failure (1) of the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->installationService->setStyle(new SymfonyStyle($input, $output));

        return $this->installationService->composerupdate();
    }
}
