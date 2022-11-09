<?php

namespace CommonGateway\CoreBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpgradeCommand extends Command
{
    protected static $defaultName = 'commongateway:upgrade';
    protected static $defaultDescription = 'This command runs the upgrade service on a commongateway bundle';

    protected function configure(): void
    {
        $this
            ->addArgument('bundle', InputArgument::REQUIRED, 'The bundle that you want to upgrade')
            ->addArgument('data', InputArgument::OPTIONAL, 'Load an (example) data set from the bundle')
            ->addOption('--no-schema', null, InputOption::VALUE_NONE, 'Skipp the installation or update of the bundles schema\'s')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $arg1 = $input->getArgument('arg1');

        if ($arg1) {
            $io->note(sprintf('You passed an argument: %s', $arg1));
        }

        if ($input->getOption('option1')) {
            // ...
        }

        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');

        return Command::SUCCESS;
    }
}
