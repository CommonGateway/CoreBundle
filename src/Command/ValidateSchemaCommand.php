<?php

namespace CommonGateway\CoreBundle\Command;

use CommonGateway\CoreBundle\Service\InstallationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @Author Ruben van der Linde <ruben@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Command
 */
class ValidateSchemaCommand extends Command
{

    /**
     * @var static $defaultName
     */
    protected static $defaultName = 'commongateway:validate:schema';

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
     * Configures this command.
     *
     * @return void Nothing.
     */
    protected function configure(): void
    {
        $this
            ->setDescription('This command checks if there are known issues with installed schemas')
            ->setHelp('This command allows you to run further installation an configuration actions afther installing a plugin');

    }//end configure()

    /**
     * Executes this command.
     *
     * @param InputInterface  $input  The input interface.
     * @param OutputInterface $output The output interface.
     *
     * @return int 1 for success, 0 for failure.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->installationService->setStyle(new SymfonyStyle($input, $output));

        return $this->installationService->validateSchemas();

    }//end execute()
}//end class
