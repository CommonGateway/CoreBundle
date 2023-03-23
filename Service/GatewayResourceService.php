<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\Gateway as Source;
use App\Entity\Mapping;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service to find gateway resources by reference.
 *
 * This service provides methods to find resources from the gateway by their reference.
 *
 * @Author Robert Zondervan <robert@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @package commongateway/corebundle
 * @category Service
 */
class GatewayResourceService
{
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $pluginLogger;

    /**
     * @param EntityManagerInterface $entityManager
     * @param LoggerInterface        $pluginLogger
     */
    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $pluginLogger)
    {
        $this->entityManager = $entityManager;
        $this->pluginLogger = $pluginLogger;
    }

    /**
     * Get a schema by reference.
     *
     * @param string $reference  The reference to look for.
     * @param string $pluginName The name of the plugin that requests the resource.
     *
     * @return Entity|null
     */
    public function getSchema(string $reference, string $pluginName): ?Entity
    {
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $reference]);
        if ($entity === null) {
            $this->pluginLogger->error("No entity found for $reference.", ['plugin'=>$pluginName]);
        }//end if

        return $entity;
    }//end getSchema()

    /**
     * Get a mapping by reference.
     *
     * @param string $reference  The reference to look for.
     * @param string $pluginName The name of the plugin that requests the resource.
     *
     * @return Mapping|null
     */
    public function getMapping(string $reference, string $pluginName): ?Mapping
    {
        $mapping = $this->entityManager->getRepository('App:Mapping')->findOneBy(['reference' => $reference]);
        if ($mapping === null) {
            $this->pluginLogger->error("No mapping found for $reference.", ['plugin'=>$pluginName]);
        }//end if

        return $mapping;
    }//end getMapping()

    /**
     * Get a source by reference.
     *
     * @param string $reference  The reference to look for.
     * @param string $pluginName The name of the plugin that requests the resource.
     *
     * @return Source|null
     */
    public function getSource(string $reference, string $pluginName): ?Source
    {
        $source = $this->entityManager->getRepository('App:Gateway')->findOneBy(['reference' => $reference]);
        if ($source === null) {
            $this->pluginLogger->error("No source found for $reference.", ['plugin'=>$pluginName]);
        }//end if

        return $source;
    }//end getSource()

    /**
     * Get a source by reference.
     *
     * @param string $reference  The location to look for.
     * @param string $pluginName The name of the plugin that requests the resource.
     *
     * @return Endpoint|null
     */
    public function getEndpoint(string $reference, string $pluginName): ?Endpoint
    {
        $source = $this->entityManager->getRepository('App:Endpoint')->findOneBy(['reference' => $reference]);
        if ($source === null) {
            $this->pluginLogger->error("No endpoint found for $reference.", ['plugin'=>$pluginName]);
        }//end if

        return $source;
    }//end getEndpoint()
}
