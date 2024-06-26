<?php

namespace CommonGateway\CoreBundle\Command;

use CommonGateway\CoreBundle\Service\CacheService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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

    /**
     * @var static $defaultName
     */
    protected static $defaultName = 'commongateway:cache:warmup';

    /**
     * The CacheService
     *
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
            ->addOption('objects', 'o', InputOption::VALUE_OPTIONAL, 'Skip caching objects during cache warmup', false)
            ->addOption('schemas', 's', InputOption::VALUE_OPTIONAL, 'Skip caching schemas during cache warmup', false)
            ->addOption('endpoints', 'p', InputOption::VALUE_OPTIONAL, 'Skip caching endpoints during cache warmup', false)
            ->addOption('removeOnly', 'r', InputOption::VALUE_OPTIONAL, 'Only do the remove data from cache during this command', false)
            ->addOption('cacheBundle', 'b', InputOption::VALUE_OPTIONAL, 'Only cache objects from a specific bundle', false)
            ->setDescription('This command puts all objects into the cache')
            ->setHelp('This command allows you to run further installation an configuration actions after installing a plugin');

    }//end configure()

    /**
     * Executes this command.
     *
     * @param InputInterface  $input  The input interface.
     * @param OutputInterface $output The output interface.
     *
     * @return int 1 is successfully executed, else 0.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->cacheService->setStyle(style: new SymfonyStyle($input, $output));

        $bundleToCache = null;
        $config        = [];
        if ($input->getOption('objects') !== false) {
            $config['objects'] = true;
        }

        if ($input->getOption('schemas') !== false) {
            $config['schemas'] = true;
        }

        if ($input->getOption('endpoints') !== false) {
            $config['endpoints'] = true;
        }

        if ($input->getOption('removeOnly') !== false) {
            $config['removeOnly'] = true;
        }

        if ($input->getOption('cacheBundle') !== null && $input->getOption('cacheBundle') !== false) {
            $bundleToCache = $input->getOption('cacheBundle');
        }

        return $this->cacheService->warmup(config: $config, bundleToCache: $bundleToCache);

    }//end execute()
}//end class
