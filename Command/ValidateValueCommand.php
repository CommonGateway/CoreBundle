<?php

namespace CommonGateway\CoreBundle\Command;

use CommonGateway\CoreBundle\Service\InstallationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ValidateValueCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'commongateway:validate:value';

    /**
     * @var InstallationService
     */
    private $installationService;

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
            ->setDescription('This command checks if there are known issues with created objects')
            ->setHelp('This command allows you to run further installation an configuration actions afther installing a plugin');
    }


    /**
     * @param InputInterface $input Symfony style
     * @param OutputInterface $output Symfony style
     * @return int Succes or failure of the command
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->installationService->setStyle(new SymfonyStyle($input, $output));

        return $this->installationService->validateValues();
    }
}
