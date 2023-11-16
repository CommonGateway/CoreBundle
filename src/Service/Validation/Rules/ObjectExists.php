<?php

namespace CommonGateway\CoreBundle\Service\Validation\Rules;

use Doctrine\ORM\EntityManagerInterface;
use Respect\Validation\Rules\AbstractRule;

/**
 * @author Wilco Louwerse <wilco@conduction.nl>
 */
final class ObjectExists extends AbstractRule
{
    /**
     * An EntityManager.
     *
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;
    
    /**
     * An Entity / Schema UUID.
     *
     * @var string|null
     */
    private ?string $schemaId;
    
    /**
     * Construct for this Rule.
     *
     * @param EntityManagerInterface $entityManager An EntityManager for finding existing ObjectEntities.
     * @param string|null $schemaId An Entity / Schema UUID that the ObjectEntity should be an Object of.
     */
    public function __construct(EntityManagerInterface $entityManager, ?string $schemaId)
    {
        $this->entityManager = $entityManager;
        $this->schemaId = $schemaId;
        
    }//end __construct()
    
    /**
     * @inheritDoc
     *
     * @param mixed $input The input UUID.
     *
     * @return bool True if the input UUID is an existing ObjectEntity. False if not. (Will also check if this object matches Entity/Schema if it is set in construct)
     */
    public function validate($input): bool
    {
        //todo

        return false;

    }//end validate()
}//end class
