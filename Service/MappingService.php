<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Mapping;
use Doctrine\ORM\EntityManagerInterface;

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
