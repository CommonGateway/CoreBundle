<?php

namespace CommonGateway\CoreBundle\Command;

use CommonGateway\CoreBundle\Service\CacheService;
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
class CacheCleanupCommand extends Command
{

    /**
     * @var static $defaultName
     */
    protected static $defaultName = 'commongateway:cache:cleanup';

    /**
     * @var CacheService $cacheService
     */
    private CacheService $cacheService;


    /**
     * __construct
     */
    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
        parent::__construct();

    }//end __construct()


    /**
     * Configures this commnand.
     *
     * @return void Nothing.
     */
    protected function configure(): void
    {
        $this
            ->setDescription('This command removes outdated objects from the cache')
            ->setHelp('This command allows you to run further installation an configuration actions afther installing a plugin');

    }//end configure()


    /**
     * Executes this commnand.
     *
     * @return int 1 is successfully executed, else 0.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->cacheService->setStyle(new SymfonyStyle($input, $output));

        $this->cacheService->cleanup();

        return Command::SUCCESS;

    }//end execute()


}//end class
