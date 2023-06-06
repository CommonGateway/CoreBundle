<?php

namespace CommonGateway\CoreBundle\Service;

use Adbar\Dot;
use App\Service\SynchronizationService;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
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
        if ($data['method'] !== "POST") {
            return $data;
        }
        
        $this->data          = $data;
        $this->configuration = $configuration;
    
        $this->logger->debug("NotificationService -> notificationHandler()");
        
        // Find source by resource url from the notification
        $dot = new Dot($data);
        $url = $dot->get($configuration['urlLocation']);
        $sources = $this->gatewayResourceService->findSourcesForUrl($url, 'commongateway/corebundle');
        if (count($sources) === 0) {
            $response = ["Message" => "Could not find a Source with this url: $url"];
            return ["response" => new Response(json_encode($response), 400, ['Content-type' => 'application/json'])];
        }
        if (count($sources) > 1) {
            $response = ["Message" => "Found more than one Source (".count($sources).") with this url: $url"];
            // todo: maybe we want to just use the first one found or the one that matches the most, or just repeat for all sources?
            return ["response" => new Response(json_encode($response), 400, ['Content-type' => 'application/json'])];
        }
        $source = $sources[0];
        
        // Get the correct Entity
        $entity = $this->gatewayResourceService->getSchema($configuration['entity'], 'commongateway/corebundle');
        if ($entity === null) {
            $response = ["Message" => "Could not find an Entity with this reference: {$configuration['entity']}"];
            return ["response" => new Response(json_encode($response), 500, ['Content-type' => 'application/json'])];
        }
        
        // Get (source) id from notification data
        $explodedUrl = explode('/', $url);
        $id = end($explodedUrl);
        
        $synchronization = $this->synchronizationService->findSyncBySource($source, $entity, $id);
        $this->synchronizationService->synchronize($synchronization);
        
        $response = ["Message" => "Notification received, object synchronized"];
        $data['response'] = new Response(json_encode($response), 200, ['Content-type' => 'application/json']);

        return $data;
    }//end notificationHandler()
}
