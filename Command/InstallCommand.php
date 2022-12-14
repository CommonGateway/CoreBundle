<?php

namespace CommonGateway\CoreBundle\Command;

use CommonGateway\CoreBundle\Service\InstallationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InstallCommand extends Command
{
    protected static $defaultName = 'commongateway:install';
    private $installationService;

    public function __construct(InstallationService $installationService)
    {
        $this->installationService = $installationService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('bundle', InputArgument::REQUIRED, 'The bundle that you want to install')
            ->addArgument('data', InputArgument::OPTIONAL, 'Load (example) data set(s) from the bundle')
            ->addOption('schema', 'sa', InputOption::VALUE_OPTIONAL, 'Load an (example) data set from the bundle', false)
            ->addOption('script', 'sp', InputOption::VALUE_OPTIONAL, 'Load an (example) data set from the bundle', false)
            ->addOption('unsafe', 'u', InputOption::VALUE_OPTIONAL, 'Update existing schema\'s and data sets', false)
            ->setDescription('This command runs the installation service on a commongateway bundle')
            ->setHelp('This command allows you to run further installation an configuration actions afther installing a plugin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->installationService->setStyle(new SymfonyStyle($input, $output));

        $bundle = $input->getArgument('bundle');
        $data = $input->getArgument('data');
        $noSchema = $input->getOption('schema');
        $script = $input->getOption('script');
        $unsafe = $input->getOption('unsafe');

        return $this->installationService->install($bundle, $noSchema);
    }
}
