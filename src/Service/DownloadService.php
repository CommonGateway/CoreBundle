<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Template;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Twig\Environment;
use Symfony\Component\HttpFoundation\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Handles incoming notification api-calls by finding or creating a synchronization and synchronizing an object.
 *
 * @Author Robert Zondervan <robert@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class DownloadService
{

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var Environment
     */
    private Environment $twig;

    /**
     * The constructor sets al needed variables.
     *
     * @param EntityManagerInterface $entityManager The EntityManager
     * @param LoggerInterface        $requestLogger The Logger
     * @param Environment            $twig          Twig
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $requestLogger,
        Environment $twig
    ) {
        $this->entityManager = $entityManager;
        $this->logger        = $requestLogger;
        $this->twig          = $twig;

    }//end __construct()

    /**
     * Renders a pdf.
     *
     * @param array $data The data to render.
     *
     * @return string The content rendered.
     */
    public function render(array $data): string
    {
        $criteria = Criteria::create()->where(Criteria::expr()->memberOf("supportedSchemas", $data['_self']['schema']['id']));

        $templates = new ArrayCollection($this->entityManager->getRepository('App:Template')->findAll());
        $templates = $templates->matching($criteria);

        if ($templates->count() === 0) {
            $this->logger->error('There is no render template for this type of object.');
            throw new BadRequestException('There is no render template for this type of object.', 406);
        } else if ($templates->count() > 1) {
            $this->logger->warning('There are more than 1 templates for this object, resolving by rendering the first template found.');
        }

        $template = $templates[0];
        if ($template instanceof Template !== true) {
            return '';
        }

        $twigTemplate = $this->twig->createTemplate($template->getContent());
        $content      = $twigTemplate->render(['object' => $data]);

        return $content;

    }//end render()

    /**
     * Downloads a pdf.
     *
     * @param array $data The data to render for this pdf.
     *
     * @return string The pdf as string output.
     */
    public function downloadPdf(array $data): string
    {
        $raw = $this->render($data);

        $pdfWriter = new Dompdf();
        $pdfWriter->setPaper('A4', 'portrait');
        $pdfWriter->loadHtml($raw);
        $pdfWriter->render();

        return $pdfWriter->output();

    }//end downloadPdf()

    /**
     * Creates a CSV download response.
     *
     * @param string $csvString.
     *
     * @return Response 
     */
    public function downloadCSV(string $csvString): Response {
        $response = new Response($csvString, 200, ['Content-Type' => 'text/csv']);
        $response->headers->set('Content-Disposition', 'attachment; filename="data.csv"');

        return $response;
    }//end collectionToCSV()

    /**
     * Creates a XLSX spreadsheet download response.
     *
     * @param array $objects.
     *
     * @return Response 
     */
    public function downloadXLSX(array $objects): Response {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
    
        if (empty($objects) === false) {
            // Set headers.
            $headers = array_keys($objects[0]);
            $column = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($column . '1', $header);
                $column++;
            }
    
            // Fill the data.
            // Starting from the second row, since first row contains headers.
            $row = 2; 
            foreach ($objects as $object) {
                $column = 'A';
                foreach ($object as $value) {
                    if (is_array($value) === true) {
                        $value = json_encode($value);
                    }
                    $sheet->setCellValue($column . $row, $value);
                    $column++;
                }
                $row++;
            }
        }
    
        // Create a streamed response to avoid memory issues with large files.
        $response = new StreamedResponse(
            function () use ($spreadsheet) {
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            }, 200
        );
    
        // Set headers for XLSX file download.
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $dispositionHeader = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'data.xlsx'
        );
        $response->headers->set('Content-Disposition', $dispositionHeader);

        return $response;
    }//end downloadXLSX()

}//end class
