<?php

namespace CommonGateway\CoreBundle\Command;

use CommonGateway\CoreBundle\Service\CacheService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CacheCommand extends Command
{
    protected static $defaultName = 'commongateway:cache:warmup';
    private $cacheService;

    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('This command puts all objects into the cache')
            ->setHelp('This command allows you to run further installation an configuration actions afther installing a plugin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->cacheService->setStyle(new SymfonyStyle($input, $output));

        return $this->cacheService->warmup();
    }
}
