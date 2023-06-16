<?php

// src/Controller/SearchController.php
namespace CommonGateway\CoreBundle\Controller;

use CommonGateway\CoreBundle\Service\CacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class SearchControllerc.
 *
 * @Route("admin/search")
 */
class SearchController extends AbstractController
{

    /**
     * @var CacheService
     */
    private CacheService $cacheService;

    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;

    }//end __construct()

    /**
     * @Route("/", methods={"GET"})
     */
    public function installedAction()
    {
        $status = 200;
        // $this->cacheService->getAll()['installed'];
        // return new Response(json_encode($plugins), $status, ['Content-type' => 'application/json']);
        // Do we want/need this search command??
        return new Response(json_encode(['This command is not functional yet.']), $status, ['Content-type' => 'application/json']);

    }//end installedAction()
}//end class
