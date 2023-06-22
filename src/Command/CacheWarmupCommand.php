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
class CacheWarmupCommand extends Command
{

    protected static $defaultName = 'commongateway:cache:warmup';

    private $cacheService;

    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
        parent::__construct();

    }//end __construct()

    protected function configure(): void
    {
        $this
            ->setDescription('This command puts all objects into the cache')
            ->setHelp('This command allows you to run further installation an configuration actions afther installing a plugin');

    }//end configure()

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->cacheService->setStyle(new SymfonyStyle($input, $output));

        return $this->cacheService->warmup();

    }//end execute()
}//end class
