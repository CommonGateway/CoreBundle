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

use App\Entity\Entity as Schema;
use App\Entity\Mapping;
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
     * @var GatewayResourceService The gateway resource service.
     */
    private GatewayResourceService $resourceService;

    /**
     * @var ValidationService The validation service.
     */
    private ValidationService $validationService;

    /**
     * @var MappingService The mapping service.
     */
    private MappingService $mappingService;

    /**
     * @var CacheService The cache service.
     */
    private CacheService $cacheService;

    public function __construct(
        GatewayResourceService $resourceService,
        ValidationService $validationService,
        MappingService $mappingService,
        CacheService $cacheService
    ) {
        $this->resourceService   = $resourceService;
        $this->validationService = $validationService;
        $this->mappingService    = $mappingService;
        $this->cacheService      = $cacheService;

    }//end __construct()

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

            break;
        default:
            $data = $serializer->decode($fileContent, $extension);
        }//end switch

        $objects = $data;

        return $objects;

    }//end decodeFile()

    /**
     * Checks if the object already exists in the database.
     *
     * @param mixed  $id     The identifier of the object.
     * @param string $field  The field the identifier is in.
     * @param array  $result The result record.
     *
     * @return array The updated result record.
     *
     * @throws Exception
     */
    public function getExistingObject($id, string $field, array $result): array
    {
        $objects = $this->cacheService->searchObjects(null, [$field => $id], [$result['object']['_self']['schema']['id']]);

        if (count($objects['results']) === 0) {
            return $result;
        }

        $result['id']     = $objects['results'][0]['_id'];
        $result['action'] = 'UPDATE';

        return $result;

    }//end getExistingObject()

    /**
     * Processes the decoded objects to fit a schema.
     *
     * @param array        $objects The objects that have been derived from the file.
     * @param Schema       $schema  The schema the objects should be stored in.
     * @param Mapping|null $mapping The mapping to map the objects in.
     *
     * @return array The array of results.
     */
    public function processObjects(array $objects, Schema $schema, ?Mapping $mapping): array
    {
        $results = [];
        foreach ($objects as $object) {
            if ($mapping !== null) {
                $object = $this->mappingService->mapping($mapping, $object);
            }

            $object['_self']['schema']['id'] = $schema->getId()->toString();

            $result = [
                'action'      => 'CREATE',
                'object'      => $object,
                'validations' => $this->validationService->validateData($object, $schema, 'POST'),
                'id'          => null,
            ];

            if (isset($object['_id']) === true) {
                $field  = ($mapping->getMapping()['_id'] ?? '_id');
                $result = $this->getExistingObject($object['_id'], $field, $result);
                unset($result['object']['_id']);
            }

            $results[] = $result;
        }//end foreach

        return $results;

    }//end processObjects()

    /**
     * Handles a file upload.
     *
     * @param Request $request The request containing a file upload.
     *
     * @return array The result of the file upload.
     */
    public function upload(Request $request): array
    {
        // Unsure about what the standard name will be here.
        $file = $request->files->get('upload');
        if ($file instanceof UploadedFile === false) {
            return new Exception("No file uploaded.");
        }

        // Validate file extension.
        $extension = $file->getClientOriginalExtension();
        if (in_array($extension, $this->supportedExtensions) === false) {
            throw new Exception("File extension: $extension not supported.");
        }

        $schema  = $this->resourceService->getSchema($request->request->get('schema'), 'commongateway/corebundle');
        $mapping = null;

        if ($request->request->has('mapping') === true) {
            $mapping = $this->resourceService->getMapping($request->request->get('mapping'), 'commongateway/corebundle');
        }

        $objects = $this->decodeFile($extension, $file, $request);
        return $this->processObjects($objects, $schema, $mapping);

    }//end upload()
}//end class
