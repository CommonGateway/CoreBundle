<?php

namespace CommonGateway\CoreBundle\Command;

use CommonGateway\CoreBundle\Service\CacheService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CacheClearCommand extends Command
{
    /**
     * @var string The name of the command (the part after "bin/console").
     */
    protected static $defaultName = 'commongateway:cache:clear';

    /**
     * @var CacheService The cache service.
     */
    private CacheService $cacheService;

    /**
     * @param CacheService $cacheService The cache service.
     */
    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
        parent::__construct();
    }//end __construct()

    /**
     * @return void Nothing.
     */
    protected function configure(): void
    {
        $this
            ->setDescription('This command removes all objects from the cache')
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
        $this->cacheService->setStyle(new SymfonyStyle($input, $output));

        return $this->cacheService->clear();
    }
}
