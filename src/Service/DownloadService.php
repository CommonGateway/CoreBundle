<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Template;
use App\Entity\ObjectEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Exception;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\Writer\Word2007;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Twig\Environment;
use Symfony\Component\HttpFoundation\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use MongoDB\Model\BSONDocument;
use MongoDB\Model\BSONArray;

/**
 * Handles incoming notification api-calls by finding or creating a synchronization and synchronizing an object.
 *
 * @Author Robert Zondervan <robert@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 *
 * This service belongs to the open services framework.
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
     * @param array       $data        The data to render.
     * @param string|null $templateRef The templateRef.
     *
     * @return string The content rendered.
     */
    public function render(array $data, ?string $templateRef = null): string
    {
        if (isset($data['_self']['schema']['id']) === false && isset($data['message']) !== false) {
            return "<html><body><h1>{$data['message']}</h1></body></html>";
        }

        if (isset($templateRef) === true) {
            $template = $this->entityManager->getRepository('App:Template')->findOneBy(['reference' => $templateRef]);
        } else {
            $criteria = Criteria::create()->where(Criteria::expr()->memberOf("supportedSchemas", $data['_self']['schema']['id']));

            $templates = new ArrayCollection($this->entityManager->getRepository('App:Template')->findAll());
            $templates = $templates->matching($criteria);

            if ($templates->count() === 0) {
                $this->logger->error('There is no render template for this type of object.');
                throw new BadRequestException('There is no render template for this type of object.', 406);
            } else if ($templates->count() > 1) {
                $this->logger->warning('There are more than 1 templates for this object, resolving by rendering the first template found.');
            }

            $template = $templates->first();
            if ($template instanceof Template !== true) {
                return '';
            }
        }

        $twigTemplate = $this->twig->createTemplate($template->getContent());
        $content      = $twigTemplate->render(['object' => $data]);

        return $content;

    }//end render()

    /**
     * Downloads a docx.
     * The html that is added has to be whitout a <head><style></style></head> section.
     *
     * @param array $data The data to render for this docx.
     *
     * @return string The docx as file output.
     */
    public function downloadDocx(array $data): string
    {
        $raw = $this->render($data);

        $docx    = new PhpWord();
        $section = $docx->addSection();
        try {
            Html::addHtml($section, $raw);
        } catch (\ErrorException $exception) {
            return json_encode(
                [
                    'message'   => $exception->getMessage(),
                    'exception' => 400,
                ]
            );
        }

        $file = 'data.docx';

        header("Content-Description: File Transfer");
        header('Content-Disposition: attachment; filename="'.$file.'"');
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');

        $docxWriter = IOFactory::createWriter($docx, 'Word2007');
        $fileId     = Uuid::uuid4();
        $filename   = '/var/tmp/'.$fileId->toString().'.docx';

        $docxWriter->save($filename);
        $rendered = \Safe\file_get_contents($filename);
        \Safe\unlink($filename);

        return $rendered;

    }//end downloadDocx()

    /**
     * Downloads a html.
     *
     * @param array $data The data to render for this html.
     *
     * @return string The html as file output.
     */
    public function downloadHtml(array $data): string
    {
        $raw = $this->render($data);

        $response = new Response($raw, 200, ['Content-Type' => 'text/html']);
        $response->headers->set('Content-Disposition', 'attachment; filename="data.html"');

        return $response->getContent();

    }//end downloadHtml()

    /**
     * Downloads a pdf.
     *
     * @param array       $data        The data to render for this pdf.
     * @param string|null $templateRef The templateRef.
     *
     * @return string The pdf as string output.
     */
    public function downloadPdf(array $data, ?string $templateRef = null): string
    {
        $raw = $this->render($data, $templateRef);

        $pdfWriter = new Dompdf();
        $pdfWriter->setPaper('A4', 'portrait');
        $pdfWriter->loadHtml($raw);
        $pdfWriter->render();

        return $pdfWriter->output();

    }//end downloadPdf()

    /**
     * Generates a CSV response from a given CSV string.
     *
     * This method takes a CSV-formatted string and creates a downloadable CSV response.
     * The client will be prompted to download the resulting file with the name "data.csv".
     *
     * @param string $csvString The CSV-formatted string to be returned as a downloadable file.
     *
     * @return Response A Symfony response object that serves the provided CSV string as a downloadable CSV file.
     */
    public function downloadCSV(string $csvString): Response
    {
        $response = new Response($csvString, 200, ['Content-Type' => 'text/csv']);
        $response->headers->set('Content-Disposition', 'attachment; filename="data.csv"');

        return $response;

    }//end downloadCSV()

    /**
     * Generates an XLSX response from a given array of associative arrays.
     *
     * This method takes an array of associative arrays (potentially having nested arrays) and
     * creates an XLSX spreadsheet with columns for each unique key (using dot notation for nested keys).
     * The method then streams this spreadsheet as a downloadable XLSX file to the client.
     *
     * @param array $objects An array of associative arrays to convert into an XLSX file.
     *
     * @return Response A Symfony response object that allows the client to download the generated XLSX file.
     */
    public function downloadXLSX(array $objects): Response
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        if (empty($objects) === false) {
            if ($objects[0] instanceof BSONDocument || $objects[0] instanceof BSONArray === true) {
                $objects = \Safe\json_decode(\Safe\json_encode($objects), true);
            }

            // Flatten the array and get headers.
            $flatSample = $this->flattenArray($objects[0]);
            $headers    = array_keys($flatSample);

            // Set headers.
            $columnIndex = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($columnIndex.'1', $header);
                $columnIndex++;
            }

            // Fill the data
            $row = 2;
            // Starting from the second row, since first row contains headers.
            foreach ($objects as $array) {
                $flatArray   = $this->flattenArray($array);
                $columnIndex = 'A';
                foreach ($flatArray as $value) {
                    $sheet->setCellValue($columnIndex.$row, $value);
                    $columnIndex++;
                }

                $row++;
            }
        }//end if

        // Create a streamed response to avoid memory issues with large files.
        $response = new StreamedResponse(
            function () use ($spreadsheet) {
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            }
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

    /**
     * Flattens a nested associative array into a single-level array.
     *
     * Given an array with potential nested arrays, this method will transform it into a single-level array.
     * Nested keys will be concatenated with a dot notation.
     *
     * @param array  $array  The array to flatten.
     * @param string $prefix Used for recursive calls to prefix the keys. Generally, this shouldn't be provided during an initial call.
     *
     * @return array The flattened array with dot notation keys for nested values.
     */
    private function flattenArray(array $array, string $prefix = ''): array
    {
        $data = [];
        foreach ($array as $key => $value) {
            if (is_array($value) === true) {
                $data += $this->flattenArray($value, $prefix.$key.'.');
            } else {
                $data[$prefix.$key] = $value;
            }
        }

        return $data;

    }//end flattenArray()
}//end class
