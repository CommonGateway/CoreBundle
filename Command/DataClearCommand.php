<?php

namespace CommonGateway\CoreBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class DataClearCommand extends Command
{
    /**
     * @var string The name of the command (the part after "bin/console").
     */
    protected static $defaultName = 'commongateway:data:clear';

    /**
     * @var EntityManagerInterface The entity manager.
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var ParameterBagInterface The environmental values.
     */
    private ParameterBagInterface $parameterBagInterface;

    /**
     * @param EntityManagerInterface $entityManagerInterface The entity manager.
     * @param ParameterBagInterface  $parameterBagInterface  The environmental values.
     */
    public function __construct(
        EntityManagerInterface $entityManagerInterface,
        ParameterBagInterface $parameterBagInterface
    ) {
        $this->entityManager = $entityManagerInterface;
        $this->parameterBagInterface = $parameterBagInterface;

        parent::__construct();
    }//end __construct()

    /**
     * @return void This function doesn't return anything.
     */
    protected function configure(): void
    {
        $this
            ->setDescription('This command removes all objects from the datbase')
            ->setHelp('use with care, or better don\'t use at all');
    }

    /**
     * @param InputInterface  $input  Symfony style input.
     * @param OutputInterface $output Symfony style output.
     *
     * @return int Succes (0) or failure (1) of the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $env = $this->parameterBagInterface->get('app_env');
        $io = new SymfonyStyle($input, $output);

        $io->writeln([
            '',
            '<info>Common Gateway Data Remover</info>',
            '============',
            '',
            'Trying to remove all data from environment: <comment> '.$env.' </comment>',
            '',
        ]);

        if ($env != 'dev') {
            $io->error('Could not remove the data bescouse the environment is not in dev mode');

            return Command::FAILURE;
        }

        $objects = $this->entityManager->getRepository('App:ObjectEntity')->findAll();

        $io->writeln('Found '.count($objects).' objects');

        // Creates a new progress bar.
        $progressBar = new ProgressBar($output, count($objects));

        // Starts and displays the progress bar.
        $progressBar->start();

        foreach ($objects as $object) {

            // Advances the progress bar 1 unit.
            $progressBar->advance();

            // You can also advance the progress bar by more than 1 unit.
            $this->entityManager->remove($object);
        }

        // Ensures that the progress bar is at 100%.
        $progressBar->finish();
        $this->entityManager->flush();

        $io->writeln('');
        $io->success('All done');

        return Command::SUCCESS;
    }
}
