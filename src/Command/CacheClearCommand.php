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
class CacheClearCommand extends Command
{

    /**
     * @var static $defaultName
     */
    protected static $defaultName = 'commongateway:cache:clear';

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
     * Configures this command.
     *
     * @return void Nothing.
     */
    protected function configure(): void
    {
        $this
            ->setDescription('This command removes all objects from the cache')
            ->setHelp('This command allows you to run further installation an configuration actions afther installing a plugin');

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

        return $this->cacheService->clear();

    }//end execute()
}//end class
