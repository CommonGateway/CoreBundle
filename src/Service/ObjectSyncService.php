<?php

namespace CommonGateway\CoreBundle\Service;

use App\Service\SynchronizationService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * This service handles calls on the ZZ endpoint (or in other words abstract routing).
 *
 * @Author Ruben van der Linde <ruben@conduction.nl>, Robert Zondervan <robert@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class ObjectSyncService
{

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var SynchronizationService
     */
    private SynchronizationService $syncService;

    /**
     * @var CallService
     */
    private CallService $callService;

    /**
     * @var GatewayResourceService
     */
    private GatewayResourceService $resourceService;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $objectSyncLogger;

    /**
     * The constructor sets al needed variables.
     *
     * @param EntityManagerInterface $entityManager    The enitymanger
     * @param SynchronizationService $syncService      The synchronisation service
     * @param CallService            $callService      The call service
     * @param GatewayResourceService $resourceService  The resource service
     * @param LoggerInterface        $objectSyncLogger The logger interface
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        SynchronizationService $syncService,
        CallService $callService,
        GatewayResourceService $resourceService,
        LoggerInterface $objectSyncLogger
    ) {
        $this->entityManager    = $entityManager;
        $this->syncService      = $syncService;
        $this->callService      = $callService;
        $this->resourceService  = $resourceService;
        $this->objectSyncLogger = $objectSyncLogger;

    }//end __construct()

    /**
     * Synchronise the object to the source.
     *
     * @param array $data A data arry containing a source, a schema and an object.
     *
     * @throws Exception
     *
     * @return array The path array for a proxy endpoint.
     */
    public function objectSyncHandler(array $data): array
    {
        $synchronisation = $this->syncService->findSyncByObject($data['object'], $data['source'], $data['schema']);

        // @todo Syncing to the source must go through the synchronisationService.
        $configuration = $data['source']->getConfiguration();

        if (key_exists('path', $configuration) === false) {
            $this->objectSyncLogger->error('Path is not set in the configuration of the source');

            return [];
        }

        $query = null;
        if (key_exists('query', $configuration) === true) {
            $query = $configuration['query'];
        }

        $objectArray = $data['object']->toArray();

        try {
            $result = $this->callService->call(
                $data['source'],
                // @todo Check if this is the right way to do this
                $configuration['path'],
                'POST',
                [
                    'body'    => json_encode($objectArray),
                    'query'   => $query,
                    'headers' => $configuration,
                ]
            );
        } catch (Exception | GuzzleException $exception) {
            $this->objectSyncLogger->error($exception->getMessage(), [$exception->getFile()]);

            return [];
        }

        $body = $this->callService->decodeResponse($data['source'], $result);

        $data['object']->hydrate($body);
        $data['object']->addSynchronization($synchronisation);
        $this->entityManager->persist($data['object']);
        $this->entityManager->flush();

        return $synchronisation->getObject()->toArray();

    }//end objectSyncHandler()
}//end class
