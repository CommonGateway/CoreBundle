<?php

namespace CommonGateway\CoreBundle\Service\Validation\Rules;

use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;
use Respect\Validation\Rules\AbstractRule;

use function is_string;

/**
 * @author Wilco Louwerse <wilco@conduction.nl>
 */
final class ObjectExists extends AbstractRule
{

    /**
     * An Entity Manager.
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
     * It is recommended to use the Uuid() validation rule before using this rule.
     * (maybe in combination with the When(if->UUID(),true->ObjectExists(),else) rule)
     *
     * @param EntityManagerInterface $entityManager An Entity Manager for finding existing ObjectEntities.
     * @param string|null            $schemaId      An Entity / Schema UUID that the ObjectEntity should be an Object of.
     */
    public function __construct(EntityManagerInterface $entityManager, ?string $schemaId = null)
    {
        $this->entityManager = $entityManager;
        $this->schemaId      = $schemaId;

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
        if (is_string($input) === false) {
            return false;
        }

        $criteria = ['id' => $input];

        if ($this->schemaId !== null) {
            $criteria['entity'] = $this->schemaId;
        }

        $objectEntity = $this->entityManager->getRepository(ObjectEntity::class)->findOneBy($criteria);
        if ($objectEntity === null) {
            return false;
        }

        return true;

    }//end validate()
}//end class
