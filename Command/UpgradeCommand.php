<?php

namespace CommonGateway\CoreBundle\Command;

use CommonGateway\CoreBundle\Service\InstallationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpgradeCommand extends Command
{
    /**
     * @var string The name of the command (the part after "bin/console").
     */
    protected static $defaultName = 'commongateway:upgrade';

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
        $this
            ->addArgument('bundle', InputArgument::REQUIRED, 'The bundle that you want to upgrade')
            ->addArgument('data', InputArgument::OPTIONAL, 'Load an (example) data set from the bundle')
            ->addOption('--no-schema', null, InputOption::VALUE_NONE, 'Skipp the installation or update of the bundles schema\'s')
            ->setDescription('This command runs the upgrade service on a commongateway bundle')
            ->setHelp('This command allows you to create a OAS files for your EAV entities');
    }

    /**
     * @param InputInterface  $input  Symfony style input.
     * @param OutputInterface $output Symfony style output.
     *
     * @return int Succes (0) or failure (1) of the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);
        $bundle = $input->getArgument('bundle');
        $data = $input->getArgument('data');
        $schema = $input->getOption('--no-schema');

        return $this->installationService->upgrade($style, $bundle, $data, $schema);
    }
}
