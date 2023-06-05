<?php

namespace CommonGateway\CoreBundle\Service;

use App\Service\SynchronizationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles incoming notification api-calls by finding or creating a synchronization and synchronizing an object.
 *
 * @Author Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class NotificationService
{
    /**
     * @var array
     */
    private array $configuration;
    
    /**
     * @var array
     */
    private array $data;
    
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    
    /**
     * @var SynchronizationService
     */
    private SynchronizationService $synchronizationService;
    
    /**
     * @var GatewayResourceService
     */
    private GatewayResourceService $gatewayResourceService;

    /**
     * @param LoggerInterface        $notificationLogger     The notification logger.
     * @param SynchronizationService $synchronizationService The SynchronizationService.
     * @param GatewayResourceService $gatewayResourceService The GatewayResourceService.
     */
    public function __construct(
        LoggerInterface $notificationLogger,
        SynchronizationService $synchronizationService,
        GatewayResourceService $gatewayResourceService
    ) {
        $this->logger = $notificationLogger;
        $this->synchronizationService = $synchronizationService;
        $this->gatewayResourceService = $gatewayResourceService;
    }
    
    /**
     * Handles incoming notification api-call and is responsible for generating a response.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration from the call
     *
     * @return array A handler must ALWAYS return an array
     */
    public function notificationHandler(array $data, array $configuration): array
    {
        $this->data          = $data;
        $this->configuration = $configuration;
    
        $this->logger->debug("NotificationService -> notificationHandler()");
        
        // Find source by resource url from the notification
        $sources = $this->gatewayResourceService->findSourcesForUrl($data['body']['resourceUrl'], 'commongateway/corebundle');
        if (count($sources) === 0) {
            $response = ["Message" => "Could not find a source with this resourceUrl: ".$data['body']['resourceUrl']];
            return ["response" => new Response(json_encode($response), 400, ['Content-type' => 'application/json'])];
        }
        if (count($sources) > 1) {
            $response = ["Message" => "Found more than one source with this resourceUrl: ".$data['body']['resourceUrl']];
            // todo: maybe we want to just use the first one found or the one that matches the most, or just repeat for all sources?
            return ["response" => new Response(json_encode($response), 400, ['Content-type' => 'application/json'])];
        }
        $source = $sources[0];
        
        // todo: get correct entity from notification data
        $entity = null; // todo: get from configuration.
        
        // todo: get (source) id from notification data
        $id = null;
        
        // todo: find/create synchronization and synchronize
        $synchronization = $this->synchronizationService->findSyncBySource($source, $entity, $id);
        $this->synchronizationService->synchronize($synchronization);
        
        $response = ["Message" => "Notification received, object synchronized"];
        $data['response'] = new Response(json_encode($response), 200, ['Content-type' => 'application/json']);

        return $data;
    }//end notificationHandler()
}
