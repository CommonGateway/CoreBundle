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
     * @param CallService $callService The Call Service.
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
        $this->callService = $callService;
        $this->resourceService = $resourceService;

    }//end __construct()

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
        if ($data['method'] !== 'POST') {
            return $data;
        }

        $this->data          = $data;
        $this->configuration = $configuration;

        $this->logger->debug('NotificationService -> notificationHandler()');

        // Find Synchronization with the data from the notification and action->configuration.
        try {
            $synchronization = $this->findSync();
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage());

            $response = json_encode(['Message' => $exception->getMessage()]);

            return ['response' => new Response($response, $exception->getCode(), ['Content-type' => 'application/json'])];
        }

        try {
            $response = $this->callService->call(
                $synchronization->getSource(),
                $synchronization->getEndpoint(),
                'GET'
            );
        } catch (\Exception $exception) {

            // Todo set error log
            throw new Exception($exception->getMessage());
        }//end try

        $sourceResponse = json_decode($response->getBody()->getContents(), true);
        $this->syncService->synchronize($synchronization, $sourceResponse);

        $this->entityManager->flush();

        $response         = ['Message' => 'Notification received, object synchronized'];
        $data['response'] = new Response(json_encode($response), 200, ['Content-type' => 'application/json']);

        return $data;

    }//end notificationHandler()

    /**
     * Tries to find a synchronisation with the data from the notification and action->configuration.
     *
     * @throws Exception If we could not find a Source or Entity we throw an exception.
     *
     * @return Synchronization
     */
    private function findSync(): Synchronization
    {
        $dot = new Dot($this->data);
        $url = $dot->get($this->configuration['urlLocation']);

        // Find source by resource url from the notification.
        $source = $this->findSource($url);

        // Get the correct Entity.
        $entity = $this->resourceService->getSchema($this->configuration['entity'], 'commongateway/corebundle');
        if ($entity === null) {
            throw new Exception("Could not find an Entity with this reference: {$this->configuration['entity']}", 500);
        }

        // Get (source) id from notification data.
        $explodedUrl = explode('/', $url);
        $sourceId    = end($explodedUrl);

        $synchronization = $this->syncService->findSyncBySource($source, $entity, $sourceId);
        $synchronization->setEndpoint(str_replace($source->getLocation(), '', $url));
        $this->entityManager->persist($synchronization);

        return $synchronization;

    }//end findSync()

    /**
     * Tries to find a source using the url of the object a notification was created for.
     *
     * @param string $url The url we are trying to find a matching Source for.
     *
     * @throws Exception If we did not find one Source we throw an exception.
     *
     * @return Source The Source we found.
     */
    public function findSource(string $url): Source
    {
        $sources = $this->resourceService->findSourcesForUrl($url, 'commongateway/corebundle');

        if (count($sources) === 0) {
            throw new Exception("Could not find a Source with this url: $url", 400);
        }

        if (count($sources) > 1) {
            // Todo: Maybe we want to just use the first one found or the one that matches the most, or just repeat for all sources?
            throw new Exception("Found more than one Source (".count($sources).") with this url: $url", 400);
        }

        return $sources[0];

    }//end findSource()
}//end class
