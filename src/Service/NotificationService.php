<?php

namespace CommonGateway\CoreBundle\Service;

use Adbar\Dot;
use App\Entity\Gateway as Source;
use App\Entity\Synchronization;
use App\Service\SynchronizationService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
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
     * @var CallService
     */
    private CallService $callService;

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
        CallService $callService,
        GatewayResourceService $resourceService
    ) {
        $this->entityManager   = $entityManager;
        $this->logger          = $notificationLogger;
        $this->syncService     = $syncService;
        $this->callService     = $callService;
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
        if ($data['method'] !== 'POST') {
            return $data;
        }

        $this->data          = $data;
        $this->configuration = $configuration;

        $this->logger->debug('NotificationService -> notificationHandler()');

        $dot = new Dot($this->data);
        $url = $dot->get($this->configuration['urlLocation']);

        // Get the correct Entity.
        $entity = $this->resourceService->getSchema($this->configuration['entity'], 'commongateway/corebundle');
        if ($entity === null) {
            $response = json_encode(['Message' => "Could not find an Entity with this reference: {$this->configuration['entity']}"]);
            return ['response' => new Response($response, 500, ['Content-type' => 'application/json'])];
        }

        try {
            $this->syncService->aquireObject($url, $entity);
        } catch (\Exception $exception) {
            $response = json_encode(['Message' => "Notification call before sync returned an Exception: {$exception->getMessage()}"]);
            return ['response' => new Response($response, 500, ['Content-type' => 'application/json'])];
        }//end try

        $this->entityManager->flush();

        $response         = ['Message' => 'Notification received, object synchronized'];
        $data['response'] = new Response(json_encode($response), 200, ['Content-type' => 'application/json']);

        return $data;

    }//end notificationHandler()
}//end class
