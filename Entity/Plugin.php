<?php

namespace CommonGateway\CoreBundle\Entity;

use ApiPlatform\Core\Annotation\ApiResource;

/**
 * @ApiResource()
 */
class Plugin
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    public function getId(): ?int
    {
        return $this->id;
    }

}
