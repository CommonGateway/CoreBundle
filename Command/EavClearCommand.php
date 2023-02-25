<?php

namespace CommonGateway\CoreBundle\Command;

use CommonGateway\CoreBundle\Service\EavService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class EavClearCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'commongateway:eav:clear';

    /**
     * @var EavService
     */
    private EavService $eavService;

    /**
     * @param EavService $eavService The eav Service
     */
    public function __construct(EavService $eavService)
    {
        $this->eavService = $eavService;
        parent::__construct();
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('This command removes all objects from the database')
            ->setHelp('Removes ALL EAV objects from the database and should not be used on production. It will however leave common gateway objects (suchs as schemes untuched)');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int The result
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->eavService->deleteAllObjects();
    }
}
