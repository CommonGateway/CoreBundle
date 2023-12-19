<?php

namespace CommonGateway\CoreBundle\Command;

use CommonGateway\CoreBundle\Service\ActionService;
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
class ActionScanCommand extends Command
{

    /**
     * @var static $defaultName
     */
    protected static $defaultName = 'commongateway:actions:scan';

    /**
     * The CacheService
     *
     * @var ActionService $actionService
     */
    private ActionService $actionService;

    /**
     * __construct
     */
    public function __construct(ActionService $actionService)
    {
        $this->actionService = $actionService;
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
            ->setDescription('This command removes actions for which no handler exists')
            ->setHelp('This command allows you to run an scanner which deletes actions for which no code is present in the installation of the common gateway (anymore)/');

    }//end configure()

    /**
     * Executes this commnand.
     *
     * @return int 1 is successfully executed, else 0.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->actionService->setStyle(new SymfonyStyle($input, $output));

        $this->actionService->scanActions();

        return Command::SUCCESS;

    }//end execute()
}//end class
