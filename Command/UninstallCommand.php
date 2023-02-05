<?php

namespace CommonGateway\CoreBundle\Command;

use CommonGateway\CoreBundle\Service\InstallationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UninstallCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'commongateway:uninstall';

    /**
     * @var InstallationService
     */
    private InstallationService $installationService;

    /**
     * @param InstallationService $installationService The installation service
     */
    public function __construct(InstallationService $installationService)
    {
        $this->installationService = $installationService;

        parent::__construct();
    }//end __construct()

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->addArgument('bundle', InputArgument::REQUIRED, 'The bundle that you want to uninstall')
            ->addOption('--schema', null, InputOption::VALUE_NONE, 'Also remove orphaned schema\'s')
            ->addOption('--data', null, InputOption::VALUE_NONE, 'Also remove orphaned data')
            ->setDescription('This command runs the uninstall service on a commongateway bundle')
            ->setHelp('This command allows you to create a OAS files for your EAV entities');
    }

    /**
     * @param InputInterface $input Symfony style
     * @param OutputInterface $output Symfony style
     *
     *
     * @return int Succes or failure of the command
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $bundle = $input->getArgument('bundle');
        $data = $input->getArgument('data');
        $schema = $input->getOption('--no-schema');

        return $this->installationService->uninstall($io, $bundle, $data, $schema);

        return Command::SUCCESS;
    }
}
