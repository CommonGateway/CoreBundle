<?php
/**
 * The FilesController handles the endpoint where to send files to.
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Controller
 */

namespace CommonGateway\CoreBundle\Controller;

// use CommonGateway\CoreBundle\Service\UploadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use function Safe\json_encode;

class FilesController extends AbstractController
{

    // /**
    //  * @var UploadService The UploadService.
    //  */
    // private UploadService $uploadService;

    /**
     * The constructor sets al needed variables.
     *
     * @param UploadService $UploadService.
     */
    public function __construct(
        // UploadService $UploadService
        )
    {
        // $this->UploadService = $UploadService;
    }//end __construct()

    /**
     * Provides a files endpoint.
     *
     * @return Response
     *
     * @Route("/admin/files", methods={"POST"})
     */
    public function files(Request $request): Response
    {
        // Example code.
        // $objects = $this->uploadService->upload();
        // $responseArray = [
        //     'objects' => $objects
        // ];
        // return new Response(json_encode($responseArray), 200, ['Content-type' => $request->headers->get('accept')]);

        return new Response(json_encode(['message' => 'The FilesController works']), 200, ['Content-type' => $request->headers->get('accept')]);

    }//end metrics()
}//end class
