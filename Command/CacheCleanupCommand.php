<?php

namespace CommonGateway\CoreBundle\Command;

use CommonGateway\CoreBundle\Service\CacheService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


class CacheCleanupCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'commongateway:cache:cleanup';

    /**
     * @var CacheService
     */
    private $cacheService;

    /**
     * @param CacheService $cacheService The cache serice
     */
    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
        parent::__construct();
    }//end __construct()


    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('This command removes outdated objects from the cache')
            ->setHelp('This command allows you to run further installation an configuration actions afther installing a plugin');
    }


    /**
     * @param InputInterface $input Symfony style
     * @param OutputInterface $output Symfony style
     *
     *
     * @return int Succes or failure of the command
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->cacheService->setStyle(new SymfonyStyle($input, $output));

        return $this->cacheService->cleanup();
    }
}
