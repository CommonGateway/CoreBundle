<?php

namespace CommonGateway\CoreBundle\Command;

use CommonGateway\CoreBundle\Service\InstallationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ValidateSchemaCommand extends Command
{
    protected static $defaultName = 'commongateway:validate:schema';
    private $installationService;

    public function __construct(InstallationService $installationService)
    {
        $this->installationService = $installationService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('This command checks if there are known issues with installed schemas')
            ->setHelp('This command allows you to run further installation an configuration actions afther installing a plugin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->installationService->setStyle(new SymfonyStyle($input, $output));

        return $this->installationService->validateSchemas();
    }
}
