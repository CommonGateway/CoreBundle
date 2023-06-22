<?php

namespace CommonGateway\CoreBundle\src\Entity;

use ApiPlatform\Core\Annotation\ApiResource;

/**
 * @ApiResource()
 *
 * @ORM\Entity()
 */
class CronjobLog
{
    /**
     * @ORM\Id
     *
     * @ORM\GeneratedValue
     *
     * @ORM\Column(type="integer")
     */
    private $id;

    public function getId(): ?int
    {
        return $this->id;
    }
}
