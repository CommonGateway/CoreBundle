<?php
/**
 * Service to find gateway resources by reference.
 *
 * This service provides methods to find resources from the gateway by their reference.
 *
 * @Author Robert Zondervan <robert@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @package commongateway/corebundle
 *
 * @category Service
 */

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Action;
use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\Gateway as Source;
use App\Entity\Mapping;
use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

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
     * The constructor sets al needed variables.
     *
     * @param EntityManagerInterface $entityManager
     * @param LoggerInterface        $pluginLogger
     */
    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $pluginLogger)
    {
        $this->entityManager = $entityManager;
        $this->pluginLogger  = $pluginLogger;

    }//end __construct()

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

        if (Uuid::isValid($reference) === true && $entity === null) {
            $entity = $this->entityManager->find('App:Entity', $reference);
        }

        if ($entity === null) {
            $this->pluginLogger->error("No entity found for $reference.", ['plugin' => $pluginName]);
        }//end if

        return $entity;

    }//end getSchema()

    /**
     * Get a object by identifier.
     *
     * @param string $id The id to look for.
     *
     * @return ObjectEntity|null
     */
    public function getObject(string $id): ?ObjectEntity
    {
        $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->find($id);
        if ($objectEntity === null) {
            $this->pluginLogger->error("No objectEntity found for $id.");
        }

        return $objectEntity;

    }//end getObject()

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

        if (Uuid::isValid($reference) === true && $mapping === null) {
            $mapping = $this->entityManager->find('App:Mapping', $reference);
        }

        if ($mapping === null) {
            $this->pluginLogger->error("No mapping found for $reference.", ['plugin' => $pluginName]);
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

        if (Uuid::isValid($reference) === true && $source === null) {
            $source = $this->entityManager->find('App:Gateway', $reference);
        }

        if ($source === null) {
            $this->pluginLogger->error("No source found for $reference.", ['plugin' => $pluginName]);
        }//end if

        return $source;

    }//end getSource()

    /**
     * Find all sources that have a location that match the specified url.
     * Todo: we should use a mongoDB filter instead of this, sources should exist in MongoDB.
     *
     * @param string $url        The url we are trying to find a matching source for.
     * @param string $pluginName The name of the plugin that requests these resources.
     *
     * @return array|null
     */
    public function findSourcesForUrl(string $url, string $pluginName): ?array
    {
        $sources    = [];
        $allSources = $this->entityManager->getRepository('App:Gateway')->findAll();

        foreach ($allSources as $source) {
            if (empty($source->getLocation()) === false && str_contains($url, $source->getLocation()) === true) {
                $sources[] = $source;
            }
        }

        if (empty($sources) === true) {
            $this->pluginLogger->error("No sources found for $url.", ['plugin' => $pluginName]);
        }//end if

        return $sources;

    }//end findSourcesForUrl()

    /**
     * Get a endpoint by reference.
     *
     * @param string $reference  The location to look for.
     * @param string $pluginName The name of the plugin that requests the resource.
     *
     * @return Endpoint|null
     */
    public function getEndpoint(string $reference, string $pluginName): ?Endpoint
    {

        $endpoint = $this->entityManager->getRepository('App:Endpoint')->findOneBy(['reference' => $reference]);

        if (Uuid::isValid($reference) === true && $endpoint === null) {
            $endpoint = $this->entityManager->find('App:Endpoint', $reference);
        }

        if ($endpoint === null) {
            $this->pluginLogger->error("No endpoint found for $reference.", ['plugin' => $pluginName]);
        }//end if

        return $endpoint;

    }//end getEndpoint()

    /**
     * Get an action by reference.
     *
     * @param string $reference  The reference to look for
     * @param string $pluginName The name of the plugin that requests the resource.
     *
     * @return Action|null
     */
    public function getAction(string $reference, string $pluginName): ?Action
    {
        $action = $this->entityManager->getRepository('App:Action')->findOneBy(['reference' => $reference]);

        if (Uuid::isValid($reference) === true && $action === null) {
            $action = $this->entityManager->find('App:Action', $reference);
        }

        if ($action === null) {
            $this->logger->error("No action found for $reference.", ['plugin' => $pluginName]);
        }//end if

        return $action;

    }//end getAction()
}//end class
