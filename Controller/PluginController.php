<?php

namespace CommonGateway\CoreBundle\Controller;

use CommonGateway\CoreBundle\Service\ComposerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class PluginController.
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
     * @param ComposerService $composerService The composer service
     */
    public function __construct(ComposerService $composerService)
    {
        $this->composerService = $composerService;
    }//end __construct()

    /**
     * @Route("/installed", methods={"GET"})
     * @return Response
     */
    public function installedAction(): Response
    {
        $status = 200;
        $plugins = $this->composerService->getAll(['--installed'])['installed'];

        return new Response(json_encode($plugins), $status, ['Content-type' => 'application/json']);
    }

    /**
     * @Route("/audit", methods={"GET"})
     * @return Response
     */
    public function auditAction(): Response
    {
        $status = 200;
        $plugins = $this->composerService->audit(['--format=json']);

        return new Response(json_encode($plugins), $status, ['Content-type' => 'application/json']);
    }//end auditAction()

    /**
     * @Route("/available", methods={"GET"})
     *
     * @param Request $request The request
     * @return Response
     */
    public function availableAction(Request $request): Response
    {
        $status = 200;

        $search = $request->query->get('search', 'a');

        $plugins = $this->composerService->search($search, ['--type=common-gateway-plugin']);

        return new Response(json_encode($plugins), $status, ['Content-type' => 'application/json']);
    }//end availableAction()

    /**
     * @Route("/view", methods={"GET"})
     *
     * @param Request $request The request
     * @return Response
     */
    public function viewAction(Request $request): Response
    {
        $status = 200;

        $package = $request->query->get('plugin', 'commongateway/corebundle');

        $plugins = $this->composerService->getSingle($package);

        return new Response(json_encode($plugins), $status, ['Content-type' => 'application/json']);
    }//end viewAction()

    /**
     * @Route("/installl", methods={"POST"})
     *
     * @param Request $request The request
     * @return Response
     */
    public function installlAction(Request $request): Response
    {
        $status = 200;

        if ($package = $request->query->get('plugin', false) === false) {
            return new Response('No plugin provided as query parameters', 400, ['Content-type' => 'application/json']);
        }

        $plugins = $this->composerService->require($package);

        return new Response(json_encode($plugins), $status, ['Content-type' => 'application/json']);
    }//end installlAction()

    /**
     * @Route("/upgrade", methods={"POST"})
     *
     * @param Request $request The request
     * @return Response
     */
    public function upgradeAction(Request $request): Response
    {
        $status = 200;

        if (!$package = $request->query->get('plugin', false) === false) {
            return new Response('No plugin provided as query parameters', 400, ['Content-type' => 'application/json']);
        }

        $plugins = $this->composerService->upgrade($package);

        return new Response(json_encode($plugins), $status, ['Content-type' => 'application/json']);
    }//end upgradeAction()

    /**
     * @Route("/remove", methods={"POST"})
     *
     * @param Request $request The request
     * @return Response
     */
    public function removeAction(Request $request): Response
    {
        $status = 200;

        if (!$package = $request->query->get('plugin', false) === false) {
            return new Response('No plugin provided as query parameters', 400, ['Content-type' => 'application/json']);
        }

        $plugins = $this->composerService->remove($package);

        return new Response(json_encode($plugins), $status, ['Content-type' => 'application/json']);
    }//end removeAction()
}//end class
