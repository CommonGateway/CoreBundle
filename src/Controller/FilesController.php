<?php
/**
 * The FileController handles the endpoint where to send files to.
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Controller
 */

namespace CommonGateway\CoreBundle\Controller;

use CommonGateway\CoreBundle\Service\UploadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use function Safe\json_encode;

class FileController extends AbstractController
{

    /**
    * @var UploadService The UploadService.
    */
    private UploadService $uploadService;

    /**
     * The constructor sets al needed variables.
     *
     * @param UploadService $UploadService.
     */
    public function __construct(UploadService $uploadService) {
        $this->uploadService = $uploadService;
    }//end __construct()

    /**
     * Provides a files endpoint.
     *
     * @Route("/admin/file-upload", methods={"POST"})
     *
     * @return Response
     */
    public function file(Request $request): Response
    {
        // Example code.
        $objects = $this->uploadService->upload($request);
        $responseArray = [
            'objects' => $objects
        ];
        return new Response(json_encode($responseArray), 200, ['Content-type' => $request->headers->get('accept')]);
        // return new Response(json_encode(['message' => 'The FileController works']), 200, ['Content-type' => $request->headers->get('accept')]);

    }//end files()
}//end class
