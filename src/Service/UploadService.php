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
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\YamlEncoder;
use PhpOffice\PhpSpreadsheet\IOFactory;

use function Safe\json_decode;

class UploadService
{

    /**
     * Supported file extensions this service can decode.
     */
    private array $supportedFileExtensions = [
        'xlsx',
        'json',
        'csv',
        'xml',
        'yaml',
    ];

    public function upload(Request $request): string
    {
        // Unsure about what the standard name will be here.
        $file = $request->files->get('upload');
        if ($file instanceof UploadedFile === false) {
            return new Exception("No file uploaded.");
        }

        // Validate file extension.
        $extension = $file->getClientOriginalExtension();
        if (in_array($extension, $this->supportedFileExtensions) === false) {
            return new Exception("File extension: $extension not supported.");
        }

        // Get the file content as a string.
        $fileContent = file_get_contents($file->getPathname());

        switch ($extension) {
        case 'xlsx':
            // Load the XLSX file using PhpSpreadsheet
            $spreadsheet = IOFactory::load($file->getPathname());

            // Convert the XLSX data to an array
            $worksheet = $spreadsheet->getActiveSheet();
            $data      = $worksheet->toArray();
            break;
        case 'json':
            $data = json_decode($fileContent);
            break;
        case 'csv':
            $data = str_getcsv($fileContent);
            break;
        case 'xml':
            $xmlEncoder = new XmlEncoder();
            $data       = $xmlEncoder->decode($fileContent, 'xml');
            break;
        case 'yaml':
            $yamlEncoder = new YamlEncoder();
            $data        = $yamlEncoder->decode($fileContent, 'yaml');
            break;
        }//end switch

        var_dump($data);

        $objects = $data['objects'];

        return $objects;

    }//end upload()
}//end class
