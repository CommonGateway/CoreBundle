<?php

namespace CommonGateway\CoreBundle\Command;

use CommonGateway\CoreBundle\Service\CacheService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
Use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class DataClearCommand extends Command
{
    protected static $defaultName = 'commongateway:data:clear';
    private $cacheService;
    private EntityManagerInterface $entityManagerInterface;
    private ParameterBagInterface $parameterBagInterface;

    public function __construct(
        CacheService $cacheService,
        EntityManagerInterface $entityManagerInterface,
        ParameterBagInterface $parameterBagInterface
    )
    {
        $this->cacheService = $cacheService;
        $this->entitymanager = $entityManagerInterface;
        $this->parameterBagInterface = $parameterBagInterface;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('This command removes all objects from the datbase')
            ->setHelp('use with care, or better don\'t use at all');
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $env = $this->parameterBagInterface->get('app_env');
        $io = new SymfonyStyle($input, $output);

        $io->writeln([
            '',
            '<info>Common Gateway Data Remover</info>',
            '============',
            '',
            'Trying to remove all data from environment: <comment> ' . $env . ' </comment>',
            '',
        ]);

        if($env!='dev'){

            $io->error('Could not remove the data bescouse the environment is not in dev mode');

            return Command::FAILURE;
        }

        $objects = $this->entitymanager->getRepository('App:ObjectEntity')->findAll();

        $io->writeln('Found '.count($objects).' objects');

        // creates a new progress bar (50 units)
        $progressBar = new ProgressBar($output, count($objects));

        // starts and displays the progress bar
        $progressBar->start();

        foreach($objects as $object){

            // advances the progress bar 1 unit
            $progressBar->advance();

            // you can also advance the progress bar by more than 1 unit
            $this->entitymanager->remove($object);
        }

        // ensures that the progress bar is at 100%
        $progressBar->finish();
        $this->entitymanager->flush();
        
        $io->writeln('');
        $io->success('All done');

        return Command::SUCCESS;
    }
}
