<?php

namespace CommonGateway\CoreBundle\Service;

use Adbar\Dot;
use App\Service\SynchronizationService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
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
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var SynchronizationService
     */
    private SynchronizationService $syncService;

    /**
     * @var GatewayResourceService
     */
    private GatewayResourceService $resourceService;

    /**
     * The constructor sets al needed variables.
     *
     * @param EntityManagerInterface $entityManager      The EntityManager.
     * @param LoggerInterface        $notificationLogger The notification logger.
     * @param SynchronizationService $syncService        The SynchronizationService.
     * @param CallService            $callService        The Call Service.
     * @param GatewayResourceService $resourceService    The GatewayResourceService.
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $notificationLogger,
        SynchronizationService $syncService,
        GatewayResourceService $resourceService
    ) {
        $this->entityManager   = $entityManager;
        $this->logger          = $notificationLogger;
        $this->syncService     = $syncService;
        $this->resourceService = $resourceService;

    }//end __construct()

    /**
     * Handles incoming notification api-call and is responsible for generating a response.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration from the call
     *
     * @return array A handler must ALWAYS return an array
     * @throws Exception
     */
    public function notificationHandler(array $data, array $configuration): array
    {
        $this->logger->debug('NotificationService -> notificationHandler()');

        // Check if we have a method and is POST or GET.
        $allowedMethods = ($this->configuration['allowedMethods'] ?? ['POST']);
        if (isset($data['method']) === false || in_array($data['method'], $allowedMethods) === false) {
            $message  = 'Notification request method is not one of '.implode(', ', $allowedMethods);
            $response = json_encode(value: ['message' => $message]);
            $this->logger->error($message);

            return ['response' => new Response($response, 500, ['Content-type' => 'application/json'])];
        }//end if

        $this->data          = $data;
        $this->configuration = $configuration;
        $pluginName          = ($this->configuration['pluginName'] ?? 'commongateway/corebundle');

        $dot = new Dot($this->data);

        // Get or generate url to fetch object from.
        if (isset($this->configuration['urlLocation']) === true) {
            $url = $dot->get($this->configuration['urlLocation']);
        } else if (isset($this->configuration['source']) === true && isset($this->configuration['endpoint']) === true && isset($this->configuration['sourceIdField']) === true) {
            $source = $this->resourceService->getSource(reference: $this->configuration['source'], pluginName: $pluginName);

            if ($source === null) {
                $message  = "Could not find an Source with this reference: {$this->configuration['source']}";
                $response = json_encode(['message' => $message]);
                return ['response' => new Response($response, 500, ['Content-type' => 'application/json'])];
            }

            $url = $source->getLocation().$this->configuration['endpoint'].'/'.$dot->get($this->configuration['sourceIdField']);
        }//end if

        // Throw error if url not found or generated.
        if (isset($url) === false) {
            $message  = "Could not find find or generate the url to fetch the source object from";
            $response = json_encode(['message' => $message]);
            $this->logger->error($message);

            return ['response' => new Response($response, 500, ['Content-type' => 'application/json'])];
        }//end if

        // Get schema the fetched object will belong to.
        $schema = $this->resourceService->getSchema(reference: $this->configuration['schema'], pluginName: $pluginName);
        if ($schema === null) {
            $message  = "Could not find an Schema with this reference: {$this->configuration['schema']}";
            $response = json_encode(['message' => $message]);

            return ['response' => new Response($response, 500, ['Content-type' => 'application/json'])];
        }//end if

        // Get mapping which is optional.
        $mapping = null;
        if (isset($this->configuration['mapping']) === true) {
            $mapping = $this->resourceService->getMapping(reference: $this->configuration['mapping'], pluginName: $pluginName);
            if ($mapping === null) {
                $message  = "Could not find an Mapping with this reference: {$this->configuration['mapping']}";
                $response = json_encode(['message' => $message]);

                return ['response' => new Response($response, 500, ['Content-type' => 'application/json'])];
            }
        }//end if

        // Fetch and synchronise the source object we are notified about.
        try {
            $this->syncService->aquireObject(url: $url, schema: $schema, mapping: $mapping);
        } catch (Exception $exception) {
            $response = json_encode(['Message' => "Notification call before sync returned an Exception: {$exception->getMessage()}"]);
            return ['response' => new Response($response, 500, ['Content-type' => 'application/json'])];
        }//end try

        // Flush anything managed by the EntityManager.
        $this->entityManager->flush();

        // Let the notifier know the notification has been handled Successfully.
        $response         = ['message' => 'Notification received, object synchronized'];
        $data['response'] = new Response(json_encode($response), 200, ['Content-type' => 'application/json']);

        return $data;

    }//end notificationHandler()
}//end class
