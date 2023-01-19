<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Mapping;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class MappingService
{
    private EntityManagerInterface $em;

    public function __construct(
        EntityManagerInterface $em
    ) {
        $this->em = $em;
    }

    public function mapping(Mapping $mappingObject, array $requestMapping): array
    {
        return $requestMapping;
    }
}
