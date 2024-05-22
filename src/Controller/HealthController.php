<?php

// src/Controller/MetricsController.php
namespace CommonGateway\CoreBundle\Controller;

use CommonGateway\CoreBundle\Service\MetricsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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
     * The constructor sets al needed variables.
     *
     * @param MetricsService $metricsService The metrics service
     */
    public function __construct(MetricsService $metricsService)
    {
        $this->metricsService = $metricsService;

    }//end __construct()

    /**
     * Provides a health check endpoint.
     *
     * @return Response
     *
     * @Route("/health-check", methods={"GET"})
     */
    public function metrics(): Response
    {
        $status = 200;

        // $metrics = $this->metricsService->getMetricsAsString();

        return new Response(['status' => 'ok'], $status, ['Content-type' => 'text/plain']);

    }//end metrics()
}//end class
