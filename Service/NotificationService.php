<?php

namespace CommonGateway\CoreBundle\Service;

use App\Service\SynchronizationService;
use Psr\Log\LoggerInterface;

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
     * @param LoggerInterface        $notificationLogger     The notification logger.
     * @param SynchronizationService $synchronizationService The SynchronizationService.
     */
    public function __construct(
        LoggerInterface $notificationLogger,
        SynchronizationService $synchronizationService
    ) {
        $this->logger = $notificationLogger;
        $this->synchronizationService = $synchronizationService;
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
        
        $data['response'] = ["Message" => "OK"];

        return $data;
    }//end proxyRequestHandler()
}
