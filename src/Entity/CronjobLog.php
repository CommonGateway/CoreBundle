<?php

namespace CommonGateway\CoreBundle\Entity;


use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;

#[
    ApiResource(),
    ORM\Entity()
]
class CronjobLog
{

    #[
        ORM\Id,
        ORM\GeneratedValue,
        ORM\Column(
            type: 'integer'
        )
    ]
    private $id;

    /**
     * Gets the id of this CronjobLog.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;

    }//end getId()
}//end class
