<?php

namespace CommonGateway\CoreBundle\Command;

use CommonGateway\CoreBundle\Service\CacheService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @Author Ruben van der Linde <ruben@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Command
 */
class DataClearCommand extends Command
{

    /**
     * @var static
     */
    protected static $defaultName = 'commongateway:data:clear';

    /**
     * @var CacheService
     */
    private CacheService $cacheService;

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var ParameterBagInterface
     */
    private ParameterBagInterface $parameterBagInterface;


    /**
     * __construct
     */
    public function __construct(
        CacheService $cacheService,
        EntityManagerInterface $entityManager,
        ParameterBagInterface $parameterBagInterface
    ) {
        $this->cacheService          = $cacheService;
        $this->entityManager         = $entityManager;
        $this->parameterBagInterface = $parameterBagInterface;

        parent::__construct();

    }//end __construct()


    /**
     * Configures this command.
     * 
     * @return void Nothng.
     */
    protected function configure(): void
    {
        $this
            ->setDescription('This command removes all objects from the datbase')
            ->setHelp('use with care, or better don\'t use at all');

    }//end configure()


    /**
     * Executes this command.
     * 
     * @return int 1 if succsesfull 0 if not.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $env          = $this->parameterBagInterface->get('app_env');
        $symfonyStyle = new SymfonyStyle($input, $output);

        $symfonyStyle->writeln(
            [
                '',
                '<info>Common Gateway Data Remover</info>',
                '============',
                '',
                'Trying to remove all data from environment: <comment> '.$env.' </comment>',
                '',
            ]
        );

        if ($env != 'dev') {
            $symfonyStyle->error('Could not remove the data bescouse the environment is not in dev mode');

            return Command::FAILURE;
        }

        $objects = $this->entityManager->getRepository('App:ObjectEntity')->findAll();

        $symfonyStyle->writeln('Found '.count($objects).' objects');

        // creates a new progress bar (50 units).
        $progressBar = new ProgressBar($output, count($objects));

        // starts and displays the progress bar.
        $progressBar->start();

        foreach ($objects as $object) {
            // advances the progress bar 1 unit.
            $progressBar->advance();

            // you can also advance the progress bar by more than 1 unit.
            $this->entityManager->remove($object);
        }

        // ensures that the progress bar is at 100%.
        $progressBar->finish();
        $this->entityManager->flush();

        $symfonyStyle->writeln('');
        $symfonyStyle->success('All done');

        return Command::SUCCESS;

    }//end execute()


}//end class
