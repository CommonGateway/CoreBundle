<?php

// src/Controller/MetricsController.php
namespace CommonGateway\CoreBundle\Controller;

use CommonGateway\CoreBundle\Service\MetricsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use MongoDB\Client;
use App\Entity\Database;
use MongoDB\Driver\Exception\Exception as MongoDBException;
use Exception;

/**
 * The Health controller provides a health check endpoint.
 *
 * Authors: Conduction <info@conduction.nl> Barry Brands <barry@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Controller
 */
class HealthController extends AbstractController
{

    /**
     * @var MetricsService The metrics service
     */
    private MetricsService $metricsService;

    /**
     * @var ParameterBagInterface The parameterbaginterface
     */
    private ParameterBagInterface $parameterBagInterface;

    /**
     * @var EntityManagerInterface The entityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * The constructor sets al needed variables.
     *
     * @param MetricsService         $metricsService The metrics service
     * @param ParameterBagInterface  $parameters     The Parameter bag
     * @param EntityManagerInterface $entityManager  The Parameter bag
     */
    public function __construct(MetricsService $metricsService, ParameterBagInterface $parameters, EntityManagerInterface $entityManager)
    {
        $this->metricsService = $metricsService;
        $this->parameters     = $parameters;
        $this->entityManager  = $entityManager;

    }//end __construct()

    /**
     * Provides a health check endpoint.
     *
     * @return Response
     *
     * @Route("/health-check", methods={"GET"})
     */
    public function healthCheck(Request $request): Response
    {
        $acceptHeader = ($request->headers->get('Accept') ?? 'application/json');

        $status             = 'pass';
        $outputMessage      = null;
        $responseStatusCode = 200;
        $mongoDBCacheStatus = 'pass';

        try {
            $client = new Client($this->parameters->get('cache_url'));
            $client->listDatabases();
        } catch (Exception | MongoDBException $e) {
            $mongoDBCacheStatus = 'fail';
            $status             = 'fail';
            $outputMessage      = 'The cache MongoDB connection failed: '.$e->getMessage();
            $responseStatusCode = 400;
        }

        $objectDatabases  = $this->entityManager->getRepository(Database::class)->findAll();
        $mongoDBCount     = count($objectDatabases);
        $mongoDBPassCount = 0;
        $mongoDBFailCount = 0;

        foreach ($objectDatabases as $database) {
            try {
                $client = new Client($database->getUri());
                $client->listDatabases();
                $mongoDBPassCount++;
            } catch (Exception | MongoDBException $e) {
                $mongoDBFailCount++;
            }
        }

        if ($mongoDBFailCount > 0) {
            if ($status !== 'fail') {
                $status = 'warn';
            }

            $responseStatusCode = 400;
            $message            = 'ome database connections failed: '.(string) $mongoDBFailCount.' out of '.(string) $mongoDBCount.' connections';
            $outputMessage      = $outputMessage ? $outputMessage .= ' and s'.$message : $outputMessage = 'S'.$message;
        }

        try {
            $errors = $this->metricsService->getErrors();
        } catch (Exception $e) {
            $status             = 'fail';
            $errors             = null;
            $outputMessage      = $outputMessage ? $outputMessage .= ' and could not fetch error logs: '.$e->getMessage() : $outputMessage = 'Could not fetch error logs: '.$e->getMessage();
            $responseStatusCode = 400;
        }

        $responseArray = [
            'status'      => $status,
            'version'     => '0.1.0',
            'description' => 'This is an instance of the CommonGateway',
            'notes'       => [],
            'output'      => $outputMessage,
            'checks'      => [
                'errors'  => $errors,
                'mongodb' => [
                    'cache'            => $mongoDBCacheStatus,
                    'otherConnections' => [
                        'total' => $mongoDBCount,
                        'pass'  => $mongoDBPassCount,
                        'fail'  => $mongoDBFailCount,
                    ],
                ],

            ],
        ];

        return new Response(json_encode($responseArray), $responseStatusCode, ['Content-Type' => $acceptHeader]);

    }//end healthCheck()
}//end class
