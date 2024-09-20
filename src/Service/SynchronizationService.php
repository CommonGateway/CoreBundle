<?php

namespace CommonGateway\CoreBundle\Service;

use DateTime;
use Exception;
use Adbar\Dot;
use App\Entity\Entity as Schema;
use App\Entity\ObjectEntity;
use App\Entity\Synchronization;
use App\Service\SynchronizationService as OldSynchronizationService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


/**
 * The synchronization service handles the fetching and sending of data or objects to and from sources (Source/Gateway objects).
 *
 * @author Conduction BV <info@conduction.nl>, Barry Brands <barry@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class SynchronizationService
{

    /**
     * @var SymfonyStyle $style.
     */
    private SymfonyStyle $style;

    /**
     * @var LoggerInterface $logger.
     */
    private LoggerInterface $logger;

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var CacheService
     */
    private CacheService $cacheService;

    /**
     * Old one from the gateway.
     *
     * @todo Remove once all code is moved to this new class.
     *
     * @var OldSynchronizationService
     */
    private OldSynchronizationService $oldSyncService;

    /**
     * @var CallService $callService.
     */
    private CallService $callService;

    /**
     * Setting up the base class with required services.
     *
     * @param LoggerInterface           $callLogger     The Logger Interface.
     * @param OldSynchronizationService $oldSyncService Old one from the gateway.
     * @param CallService               $callService    The callService.
     * @param EntityManagerInterface    $entityManager  EntityManagerInterface.
     * @param CacheService              $cacheService   CacheService.
     */
    public function __construct(
        LoggerInterface $callLogger,
        OldSynchronizationService $oldSyncService,
        CallService $callService,
        EntityManagerInterface $entityManager,
        CacheService $cacheService
    ) {
        $this->logger         = $callLogger;
        $this->oldSyncService = $oldSyncService;
        $this->callService    = $callService;
        $this->entityManager  = $entityManager;
        $this->cacheService   = $cacheService;

    }//end __construct()

    /**
     * Set symfony style in order to output to the console.
     *
     * @param SymfonyStyle $style
     *
     * @return self
     */
    public function setStyle(SymfonyStyle $style): self
    {
        $this->style = $style;

        return $this;

    }//end setStyle()

    /**
     * This function was created because currently $commonGateway->synchronizationService->synchronize() function
     * can only pull from a source and not push to a source.
     * This function can be used as 'temporary' replacement of the synchronize function.
     *
     * NOTE: Before calling this function a Synchronization object must exist or be created, please use the
     * $commonGateway->synchronizationService->findSyncBySource() or findSyncByObject() function for this.
     *
     * @param Synchronization $synchronization The synchronization we are going to synchronize.
     * @param array           $objectArray     The object data we are going to synchronize.
     * @param ObjectEntity    $objectEntity    The objectEntity which data we are going to synchronize.
     * @param string          $location        The path/endpoint we send the request to.
     * @param string|null     $idLocation      The location of the id in the response body.
     * @param string|null     $method          The request method PUT or POST.
     *
     * @return array The response body of the outgoing call, or an empty array on error.
     *
     * @throws Exception
     */
    public function synchronizeTemp(Synchronization &$synchronization, array $objectArray, ObjectEntity $objectEntity, string $location, ?string $idLocation = null, ?string $method = 'POST'): array
    {
        $objectString = $this->oldSyncService->getObjectString($objectArray);

        $this->logger->info("Sending $method message with body $objectString");
        isset($this->style) && $this->style->info("Sending $method message with body $objectString");

        // note/todo headers removed from given array because we already do this in the ->call function.
        try {
            $result = $this->callService->call(
                $synchronization->getSource(),
                $location,
                $method,
                [
                    'body'    => $objectString,
                    // 'query'   => [],
                    // 'headers' => $synchronization->getSource()->getHeaders()
                ]
            );
        } catch (Exception | GuzzleException $exception) {
            $this->oldSyncService->ioCatchException(
                $exception,
                [
                    'line',
                    'file',
                    'message' => ['preMessage' => 'Error while doing syncToSource in zgwToVrijbrpHandler: '],
                ]
            );
            if (method_exists(get_class($exception), 'getResponse') === true && $exception->getResponse() !== null) {
                $responseBody = $exception->getResponse()->getBody();
            }

            $this->logger->error('Could not synchronize object. Error message: '.$exception->getMessage().'\nFull Response: '.($responseBody ?? ''));
            isset($this->style) && $this->style->error('Could not synchronize object. Error message: '.$exception->getMessage().'\nFull Response: '.($responseBody ?? ''));

            return [];
        }//end try

        $body = $this->callService->decodeResponse($synchronization->getSource(), $result);

        $bodyDot = new Dot($body);

        if ($idLocation !== null) {
            $sourceId = $bodyDot->get($idLocation);
            if ($sourceId == null) {
                $jsonBody = json_encode($body);
                $this->logger->error("Could not find sourceId: $idLocation in response body: $jsonBody");
                isset($this->style) && $this->style->error("Could not find sourceId: $idLocation in response body: $jsonBody");
            } else {
                $synchronization->setSourceId($sourceId);
                $this->logger->info("Succesfull $method with sourceId: $sourceId");
                isset($this->style) && $this->style->info("Succesfull $method with sourceId: $sourceId");
            }
        } else {
            $this->logger->info("Succesfull $method");
            isset($this->style) && $this->style->info("Succesfull $method");
        }

        $synchronization->setObject($objectEntity);
        $now = new DateTime();
        $synchronization->setLastSynced($now);
        $synchronization->setSourceLastChanged($now);
        $synchronization->setLastChecked($now);
        $synchronization->setHash(hash('sha384', serialize($bodyDot->jsonSerialize())));

        $this->entityManager->persist($synchronization);
        $this->entityManager->flush();
        $this->cacheService->cacheObject($synchronization->getObject());

        $this->logger->info('Synchronize '.$method.' succesfull with response body '.json_encode($body));

        return $body;

    }//end synchronizeTemp()
}//end class
