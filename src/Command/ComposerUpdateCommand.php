<?php

namespace CommonGateway\CoreBundle\Command;

use CommonGateway\CoreBundle\Service\InstallationService;
use Symfony\Component\Console\Command\Command;
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
class ComposerUpdateCommand extends Command
{

    protected static $defaultName = 'commongateway:composer:update';

    private $installationService;

    public function __construct(InstallationService $installationService)
    {
        $this->installationService = $installationService;
        parent::__construct();

    }//end __construct()

    protected function configure(): void
    {
        $this
            ->addOption('bundle', 'b', InputOption::VALUE_OPTIONAL, 'The bundle that you want to install')
            ->addOption('data', 'd', InputOption::VALUE_OPTIONAL, 'Load (example) data set(s) from the bundle', false)
            ->addOption('schema', 'sa', InputOption::VALUE_OPTIONAL, 'Load an (example) data set from the bundle', false)
            ->addOption('script', 'sp', InputOption::VALUE_OPTIONAL, 'Load an (example) data set from the bundle', false)
            ->addOption('unsafe', 'u', InputOption::VALUE_OPTIONAL, 'Update existing schema\'s and data sets', false)
            ->setDescription('This command runs the installation service on a commongateway bundle')
            ->setHelp('This command allows you to run further installation an configuration actions afther installing a plugin');

    }//end configure()

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->installationService->setStyle(new SymfonyStyle($input, $output));

        $bundle   = $input->getOption('bundle');
        $data     = $input->getOption('data');
        $noSchema = $input->getOption('schema');
        $script   = $input->getOption('script');
        $unsafe   = $input->getOption('unsafe');

        return $this->installationService->composerupdate();

    }//end execute()
}//end class
