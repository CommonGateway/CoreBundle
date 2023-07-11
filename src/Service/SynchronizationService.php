<?php

namespace CommonGateway\CoreBundle\Service;

use DateTime;
use Exception;
use Adbar\Dot;
use App\Entity\Entity as Schema;
use App\Entity\ObjectEntity;
use App\Entity\Synchronization;
use App\Service\SynchronizationService as OldSynchronizationService;
use CommonGateway\CoreBundle\Service\CallService;
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
     * @param Environment            $twig.
     * @param LoggerInterface        $actionLogger.
     * @param SynchronizationService $syncService   Old one from the gateway.
     * @param CallService            $callService.
     */
    public function __construct(
        LoggerInterface $actionLogger,
        OldSynchronizationService $oldSyncService,
        CallService $callService
    ) {
        $this->logger         = $actionLogger;
        $this->oldSyncService = $oldSyncService;
        $this->callService    = $callService;

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
     * Temporary function as replacement of the $this->oldSyncService->synchronize() function.
     * Because currently synchronize function can only pull from a source and not push to a source.
     *
     * @todo: Temp way of doing this without updating the oldSyncService->synchronize() function...
     *
     * @param Synchronization|null $synchronization The synchronization we are going to synchronize.
     * @param array                $objectArray     The object data we are going to synchronize.
     * @param ObjectEntity         $objectEntity    The objectEntity which data we are going to synchronize.
     * @param Schema               $schema          The schema the object we are going to send belongs to.
     * @param string               $location        The path/endpoint we send the request to.
     * @param string               $idLocation      The location of the id in the response body.
     * @param string               $method          The request method PUT or POST.
     *
     * @return array The response body of the outgoing call, or an empty array on error.
     */
    public function synchronizeTemp(?Synchronization &$synchronization = null, array $objectArray, ObjectEntity $objectEntity, Schema $schema, string $location, string $idLocation, ?string $method = 'POST'): array
    {
        $objectString = $this->oldSyncService->getObjectString($objectArray);

        $this->logger->info('Sending message with body '.$objectString);
        isset($this->style) && $this->style->info('Sending message with body '.$objectString);
        
        try {
            $result = $this->callService->call(
                $synchronization->getSource(),
                $location,
                $method,
                [
                    'body'    => $objectString,
                    // 'query'   => [],
                    'headers' => $synchronization->getSource()->getHeaders(),
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
            $this->logger->error('Could not synchronize object. Error message: '.$exception->getMessage().'\nFull Response'.($exception instanceof ServerException || $exception instanceof ClientException || $exception instanceof RequestException === true ? $exception->getResponse()->getBody() : ''));
            isset($this->style) && $this->style->error('Could not synchronize object. Error message: '.$exception->getMessage().'\nFull Response'.($exception instanceof ServerException || $exception instanceof ClientException || $exception instanceof RequestException === true ? $exception->getResponse()->getBody() : ''));

            return [];
        }//end try

        $body = $this->callService->decodeResponse($synchronization->getSource(), $result);

        if (isset($synchronization) === false) {
            $synchronization = new Synchronization();
            $synchronization->setEntity($schema);
        }

        $bodyDot  = new Dot($body);
        $sourceId = $bodyDot->get($idLocation);
        $synchronization->setSourceId($sourceId);
        $synchronization->setObject($objectEntity);
        $now = new DateTime();
        $synchronization->setLastSynced($now);
        $synchronization->setSourceLastChanged($now);
        $synchronization->setLastChecked($now);
        $synchronization->setHash(hash('sha384', serialize($bodyDot->jsonSerialize())));
        
        $this->logger->error('Synchronize succesfull with response body ' . json_encode($body));
            

        return $body;

    }//end synchronizeTemp()
}//end class
