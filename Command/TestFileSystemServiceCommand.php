<?php

// src/Command/ConfigureClustersCommand.php

namespace CommonGateway\CoreBundle\Command;

use App\Entity\Synchronization;
use App\Service\SynchronizationService;
use CommonGateway\CoreBundle\Service\FileSystemService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @Author Robert Zondervan <robert@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Command
 */
class TestFileSystemServiceCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private SynchronizationService $synchronizationService;

    public function __construct(EntityManagerInterface $entityManager, SynchronizationService $synchronizationService, string $name = null)
    {
        $this->entityManager = $entityManager;
        $this->synchronizationService = $synchronizationService;
        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('commongateway:file:fetch')
            // the short description shown while running "php bin/console list"
            ->setDescription('Resets cache for anonymous scopes')
            ->addArgument('source', InputArgument::REQUIRED)
            ->addArgument('location', InputArgument::REQUIRED)
            ->setHelp('This command will remove all anonymous scopes from the cache, useful if these scopes get changed.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->info('Fetching object from '.$input->getArgument('source').'/'.$input->getArgument('location'));

        $source = $this->entityManager->getRepository('App:Gateway')->findOneBy(['reference' => $input->getArgument('source')]);
        $location = $input->getArgument('location');
        if($source === null) {
            $source = $this->entityManager->getRepository('App:Gateway')->find($input->getArgument('source'));
        }

        $synchronization = new Synchronization();
        $synchronization->setSource($source);
        $synchronization->setSourceId($location);
        $synchronization->setEndpoint($location);

        $this->entityManager->persist($synchronization);

        $result = $this->synchronizationService->getSingleFromSource($synchronization);

        $io->info('result');
        var_Dump($result);

        return Command::SUCCESS;
    }
}
