<?php

namespace CommonGateway\CoreBundle\Command;

use CommonGateway\CoreBundle\Service\InstallationService;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @Author Ruben van der Linde <ruben@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Command
 */
class UninstallCommand extends Command
{

    /**
     * @var static $defaultName
     */
    protected static $defaultName = 'commongateway:uninstall';

    /**
     * The InstallationService.
     *
     * @var InstallationService $installationService
     */
    private InstallationService $installationService;

    /**
     * __construct
     */
    public function __construct(InstallationService $installationService)
    {
        $this->installationService = $installationService;

        parent::__construct();

    }//end __construct()

    /**
     * Configures this commmand.
     *
     * @return void Nothing.
     */
    protected function configure(): void
    {
        $this
            ->addArgument('bundle', InputArgument::REQUIRED, 'The bundle that you want to uninstall')
            ->addOption('--schema', null, InputOption::VALUE_NONE, 'Also remove orphaned schema\'s')
            ->addOption('--data', null, InputOption::VALUE_NONE, 'Also remove orphaned data')
            ->setDescription('This command runs the uninstall service on a commongateway bundle')
            ->setHelp('This command allows you to create a OAS files for your EAV entities');

    }//end configure()

    /**
     * Executes this command.
     *
     * @param InputInterface  $input  The input interface.
     * @param OutputInterface $output The output interface.
     *
     * @return int 1 is successfully executed, else 0.
     *
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $bundle = $input->getArgument('bundle');
        $data   = $input->getArgument('data');
        $schema = $input->getOption('--no-schema');

        $this->installationService->setStyle(new SymfonyStyle($input, $output));
        return $this->installationService->uninstall($io, $bundle, $data, $schema);

        return Command::SUCCESS;

    }//end execute()
}//end class
