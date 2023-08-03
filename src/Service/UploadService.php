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

use function Safe\json_decode;

class UploadService
{

    /**
     * Supported file extensions this service can decode.
     */
    private array $supportedFileExtensions = [
        'json',
        'csv',
        'xml',
        'yaml'
    ];

    public function upload(Request $request): string
    {
        // Unsure about what the standard name will be here.
        $file = $request->files->get('file_input');
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
        var_dump($fileContent);

        switch ($extension) {
            case 'json':
                $data = $this->decodeJson($fileContent);
                break;
            case 'csv':
                $data = $this->decodeCsv($fileContent);
                break;
            case 'xml':
                $data = $this->decodeXml($fileContent);
                break;
            case 'yaml':
                $data = $this->decodeYaml($fileContent);
                break;
        }

        $objects = [];

        return $objects;
    }//end writeFile()

}//end class
