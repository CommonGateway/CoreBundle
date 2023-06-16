<?php

// src/Controller/PluginController.php
namespace CommonGateway\CoreBundle\Controller;

use CommonGateway\CoreBundle\Service\ComposerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class PluginControllerc.
 *
 * @Route("/admin/plugins")
 */
class PluginController extends AbstractController
{

    /**
     * @var ComposerService
     */
    private ComposerService $composerService;


    /**
     * The constructor sets al needed variables.
     *
     * @codeCoverageIgnore We do not need to test constructors
     *
     * @param ComposerService $composerService
     */
    public function __construct(ComposerService $composerService)
    {
        $this->composerService = $composerService;

    }//end __construct()


    /**
     * @Route("/installed", methods={"GET"})
     */
    public function installedAction()
    {
        $status  = 200;
        $plugins = $this->composerService->getAll()['installed'];

        return new Response(json_encode($plugins), $status, ['Content-type' => 'application/json']);

    }//end installedAction()


    /**
     * @Route("/audit", methods={"GET"})
     */
    public function auditAction()
    {
        $status  = 200;
        $plugins = $this->composerService->audit(['--format=json']);

        return new Response(json_encode($plugins), $status, ['Content-type' => 'application/json']);

    }//end auditAction()


    /**
     * @Route("/available", methods={"GET"})
     */
    public function availableAction(Request $request)
    {
        $status = 200;

        $search = $request->query->get('search', 'a');

        $plugins = $this->composerService->search($search);

        return new Response(json_encode($plugins), $status, ['Content-type' => 'application/json']);

    }//end availableAction()


    /**
     * @Route("/view", methods={"GET"})
     */
    public function viewAction(Request $request)
    {
        $status = 200;

        $package = $request->query->get('plugin', 'commongateway/corebundle');

        $plugins = $this->composerService->getSingle($package);

        return new Response(json_encode($plugins), $status, ['Content-type' => 'application/json']);

    }//end viewAction()


    /**
     * @Route("/installl", methods={"POST"})
     */
    public function installlAction(Request $request)
    {
        $status = 200;

        if (empty($package = $request->query->get('plugin', false)) === true) {
            return new Response('No plugin provided as query parameters', 400, ['Content-type' => 'application/json']);
        }

        $plugins = $this->composerService->require($package);

        return new Response(json_encode($plugins), $status, ['Content-type' => 'application/json']);

    }//end installlAction()


    /**
     * @Route("/upgrade", methods={"POST"})
     */
    public function upgradeAction(Request $request)
    {
        $status = 200;

        if (empty($package = $request->query->get('plugin', false)) === true) {
            return new Response('No plugin provided as query parameters', 400, ['Content-type' => 'application/json']);
        }

        $plugins = $this->composerService->upgrade($package);

        return new Response(json_encode($plugins), $status, ['Content-type' => 'application/json']);

    }//end upgradeAction()


    /**
     * @Route("/remove", methods={"POST"})
     */
    public function removeAction(Request $request)
    {
        $status = 200;

        if (empty($package = $request->query->get('plugin', false)) === true) {
            return new Response('No plugin provided as query parameters', 400, ['Content-type' => 'application/json']);
        }

        $plugins = $this->composerService->remove($package);

        return new Response(json_encode($plugins), $status, ['Content-type' => 'application/json']);

    }//end removeAction()


}//end class
