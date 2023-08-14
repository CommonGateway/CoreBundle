<?php
/**
 * The UploadService handles the uploading and parsing of files to PHP arrays and eventually ObjectEntities.
 *
 * @author Conduction BV <info@conduction.nl>, Barry Brands <barry@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */

namespace CommonGateway\CoreBundle\Service;

use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\YamlEncoder;
use PhpOffice\PhpSpreadsheet\IOFactory;

use Symfony\Component\Serializer\Serializer;
use function Safe\json_decode;

class UploadService
{

    /**
     * @var array Supported file extensions this service can decode.
     */
    private array $supportedExtensions = [
        'xlsx',
        'xls',
        'ods',
        'json',
        'csv',
        'xml',
        'yaml',
    ];

    /**
     * Combines the headers of a table into a row to create an associative array.
     *
     * @param  array $rows    The rows to parse.
     * @param  array $headers The headers of the columns.
     * @return array
     */
    public function makeArrayAssociative(array $rows, array $headers): array
    {
        array_walk(
            $rows,
            function (&$row) use ($headers) {
                $row = array_combine($headers, $row);
            }
        );

        return $rows;

    }//end makeArrayAssociative()

    /**
     * Decodes an incoming file based upon its extension.
     *
     * @param string       $extension The extension of the file to decode.
     * @param UploadedFile $file      The uploaded file to decode.
     * @param Request      $request   The incoming request.
     *
     * @return array The array of objects derived from the file.
     */
    public function decodeFile(string $extension, UploadedFile $file, Request $request): array
    {
        // Get the file content as a string.
        $fileContent = $file->getContent();

        $delimiter = ',';
        if ($request->request->has('delimiter') === true) {
            $delimiter = $request->request->get('delimiter');
        }

        // Create a serializer for the most common formats.
        $serializer = new Serializer(
            [],
            [
                new CsvEncoder(
                    [
                        'no_headers'    => !($request->request->get('headers') === 'true'),
                        'csv_delimiter' => $delimiter,
                    ]
                ),
                new YamlEncoder(),
                new JsonEncoder(),
                new XmlEncoder(),
            ]
        );

        switch ($extension) {
        case 'xlsx':
        case 'ods':
        case 'xls':
            // Load the XLSX file using PhpSpreadsheet.
            $spreadsheet = IOFactory::load($file->getPathname());

            // Convert the XLSX data to an array.
            $worksheet = $spreadsheet->getActiveSheet();
            $data      = $worksheet->toArray();

            if ($request->request->get('headers') === 'true') {
                $headers = array_shift($data);
                $data    = $this->makeArrayAssociative($data, $headers);
            }

            $data['objects'] = $data;

            break;
        default:
            $data = $serializer->decode($fileContent, $extension);
        }//end switch

        $objects = $data['objects'];

        return $objects;

    }//end decodeFile()

    /**
     * Handles a file upload.
     *
     * @param  Request $request The request containing a file upload.
     * @return array
     */
    public function upload(Request $request): array
    {
        // Unsure about what the standard name will be here.
        $file = $request->files->get('upload');
        if ($file instanceof UploadedFile === false) {
            throw new Exception("No file uploaded.");
        }

        // Validate file extension.
        $extension = $file->getClientOriginalExtension();
        if (in_array($extension, $this->supportedExtensions) === false) {
            throw new Exception("File extension: $extension not supported.");
        }

        return $this->decodeFile($extension, $file, $request);

    }//end upload()
}//end class
