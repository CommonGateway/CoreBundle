<?php

namespace CommonGateway\CoreBundle\Command;

use CommonGateway\CoreBundle\Service\InstallationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ValidateObjectCommand extends Command
{
    /**
     * @var string The name of the command (the part after "bin/console").
     */
    protected static $defaultName = 'commongateway:validate:object';

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
            ->setDescription('This command checks if there are known issues with created objects')
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

        return $this->installationService->validateObjects();
    }
}
