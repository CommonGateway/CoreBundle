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
class UpgradeCommand extends Command
{

    protected static $defaultName = 'commongateway:upgrade';

    private InstallationService $installationService;

    public function __construct(InstallationService $installationService)
    {
        $this->installationService = $installationService;

        parent::__construct();

    }//end __construct()

    protected function configure(): void
    {
        $this
            ->addArgument('bundle', InputArgument::REQUIRED, 'The bundle that you want to upgrade')
            ->addArgument('data', InputArgument::OPTIONAL, 'Load an (example) data set from the bundle')
            ->addOption('--no-schema', null, InputOption::VALUE_NONE, 'Skipp the installation or update of the bundles schema\'s')
            ->setDescription('This command runs the upgrade service on a commongateway bundle')
            ->setHelp('This command allows you to create a OAS files for your EAV entities');

    }//end configure()
    
    /**
     * Executes this command.
     *
     * @param InputInterface $input The input interface.
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
        return $this->installationService->upgrade($io, $bundle, $data, $schema);

    }//end execute()
}//end class
