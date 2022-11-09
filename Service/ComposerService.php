<?php

namespace CommonGateway\CoreBundle\Service;

class ComposerService
{


    public function show(array $options = [], ?string $packadge, ?string $version){

        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'composer',
            //(optional) define the value of command arguments
            //'fooArgument' => 'barValue',
            //(optional) pass options to the command
            // '--bar' => 'fooValue',
        ]);

        // You can use NullOutput() if you don't need the output
        $output = new BufferedOutput();
        $application->run($input, $output);

        // return the output, don't use if you used NullOutput()
        $content = $output->fetch();

        return $content;
    }




    public function require(SymfonyStyle $io, string $bundle, ?string $data, bool $noSchema = false){

        $io->writeln([
            '<info>Common Gateway Bundle Installer</info>',
            '============',
            '',
            '<comment>Trying to install</comment> '. $bundle,
            '',
        ]);

        return Command::SUCCESS;
    }

    public function update(SymfonyStyle $io, string $bundle, string $data){

        $io->writeln([
            'Common Gateway Bundle Updater',
            '============',
            '',
        ]);

        return Command::SUCCESS;

    }

    public function uninstall(SymfonyStyle $io, string $bundle, string $data){

        $io->writeln([
            'Common Gateway Bundle Uninstaller',
            '============',
            '',
        ]);
        return Command::SUCCESS;
    }



}
