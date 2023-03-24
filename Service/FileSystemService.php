<?php
/**
 * Service to call external Filesystem sources.
 *
 * This service provides a flysystem wrapper to get content from files.
 *
 * @Author Robert Zondervan <robert@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @package commongateway/corebundle
 *
 * @category Service
 */
namespace CommonGateway\CoreBundle\Service;

use App\Entity\Gateway as Source;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\YamlEncoder;

class FileSystemService
{
    /**
     * The entity manager.
     *
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * The mapping service.
     *
     * @var MappingServic
     */
    private MappingService $mappingService;

    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    private LoggerInterface $callLogger;

    /**
     * Create File System Service.
     *
     * @var CreateFileSystemService
     */
    private CreateFileSystemService $cfsService;

    /**
     * The class constructor.
     *
     * @param EntityManagerInterface  $entityManager  The entity manager.
     * @param MappingService          $mappingService The mapping service.
     * @param LoggerInterface         $callLogger     The call logger.
     * @param CreateFileSystemService $cfsService     The create file system service
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        MappingService $mappingService,
        LoggerInterface $callLogger,
        CreateFileSystemService $cfsService
    ) {
        $this->entityManager = $entityManager;
        $this->mappingService = $mappingService;
        $this->callLogger = $callLogger;
        $this->cfsService = $cfsService;
    }//end __construct()

    /**
     * Gets the content of a file from a specific file on a filesystem.
     *
     * @param Filesystem $filesystem The filesystem to get a file from.
     * @param string     $location   The location of the file to get.
     *
     * @throws FilesystemException
     *
     * @return string|null The file content or null.
     */
    public function getFileContents(Filesystem $filesystem, string $location): ?string
    {
        if ($filesystem->fileExists($location) === true) {
            return $filesystem->read($location);
        }

        return null;
    }//end getFileContents()

    /**
     * Returns the contents of all files in a filesystem.
     *
     * @param Filesystem $filesystem The local filesystem.
     *
     * @throws \Exception
     *
     * @return array
     */
    public function getContentFromAllFiles(FileSystem $filesystem): array
    {
        // Get all files on the filesystem.
        $files = $filesystem->listContents('/', true);

        // Recursively get data from the files in the filesystem file.
        foreach ($files as $file) {
            $contents[$file->path()] = $this->decodeFile(
                $this->getFileContents($filesystem, $file->path()),
                $file->path()
            );
        }

        return $contents;
    }//end getContentFromAllFiles()

    /**
     * Decodes a file content using a given format, default = json_decode.
     *
     * @param string|null $content  The content to decode.
     * @param string      $location The (file) location to get a format from if no format is given.
     * @param string|null $format   The format to use when decoding the file content.
     *
     * @throws \Exception
     *
     * @return array The decoded file content.
     */
    public function decodeFile(?string $content, string $location, ?string $format = null): array
    {
        /*
         * Increase memory, data read from file can get quite large.
         * Design decision is to ignore codesniffer here for now until we find an alternative.
         */

        ini_set('memory_limit', '4096M');

        if ($format === null) {
            $fileArray = explode('.', $location);
            $format = end($fileArray);
        }

        switch ($format) {
            case 'zip':
                $zipFile = $this->cfsService->createZipFileFromContent($content);
                $filesystem = $this->cfsService->openZipFilesystem($zipFile);
                $content = $this->getContentFromAllFiles($filesystem);
                $this->cfsService->removeZipFile($zipFile);

                return $content;
            case 'yaml':
                $yamlEncoder = new YamlEncoder();

                return $yamlEncoder->decode($content, $format);
            case 'xml':
                $xmlEncoder = new XmlEncoder();

                return $xmlEncoder->decode($content, $format);
            case 'json':
            default:
                try {
                    return \Safe\json_decode($content, true);
                } catch (\Exception $exception) {
                    return [];
                }
        }//end switch
    }//end decodeFile()

    /**
     * Calls a Filesystem source according to given configuration.
     *
     * @param Source $source   The Filesystem source to call.
     * @param string $location The (file) location on the Filesystem source to call.
     * @param array  $config   The additional configuration to call the Filesystem source.
     *
     * @return array The decoded response array of the call.
     */
    public function call(Source $source, string $location, array $config = []): array
    {
        // Todo: Also add handleEndpointsConfigOut?
        $fileSystem = $this->cfsService->openFtpFilesystem($source);

        $content = $this->getFileContents($fileSystem, $location);

        if (isset($config['format']) === true) {
            $decodedFile = $this->decodeFile($content, $location, $config['format']);
        } elseif (isset($config['format']) === false) {
            $decodedFile = $this->decodeFile($content, $location);
        }

        return $this->handleEndpointsConfigIn($source, $location, $decodedFile);
    }//end call()

    /**
     * Handles the endpointsConfig of a Filesystem Source after we did a guzzle call.
     * See CallService->handleEndpointsConfigIn() for how we handle this on other (/normal) type of sources.
     *
     * @param Source $source      The Filesystem source.
     * @param string $location    The (file) location used to do a guzzle call on the Filesystem source.
     * @param array  $decodedFile The decoded file, response of the guzzle call we might want to change.
     *
     * @return array The decoded file as array.
     */
    private function handleEndpointsConfigIn(Source $source, string $location, array $decodedFile): array
    {
        $this->callLogger->info('Handling incoming configuration for Filesystem endpoints');
        $endpointsConfig = $source->getEndpointsConfig();
        if (empty($endpointsConfig) === true) {
            return $decodedFile;
        }

        // Let's check if the endpoint used on this source has "in" configuration in the EndpointsConfig of the source.
        if (array_key_exists($location, $endpointsConfig) === true
            && array_key_exists('in', $endpointsConfig[$location]) === true
        ) {
            $endpointConfigIn = $endpointsConfig[$location]['in'];
        } elseif (array_key_exists('global', $endpointsConfig) === true
            && array_key_exists('in', $endpointsConfig['global']) === true
        ) {
            $endpointConfigIn = $endpointsConfig['global']['in'];
        }

        if (isset($endpointConfigIn) === true) {
            $decodedFile = $this->handleEndpointConfigIn($decodedFile, $endpointConfigIn, 'root');
        }

        return $decodedFile;
    }//end handleEndpointsConfigIn()

    /**
     * Handles endpointConfig for a specific endpoint on a Filesystem source and a specific key like: 'root'.
     * After we did a guzzle call.
     * See CallService->handleEndpointConfigIn() for how we handle this on other (/normal) type of sources.
     *
     * @param array  $decodedFile      The decoded file, response of the guzzle call we might want to change.
     * @param array  $endpointConfigIn The endpointConfig 'in' of a specific endpoint and Filesystem source.
     * @param string $key              The specific key to check if data needs to be changed.
     *
     * @return array The decoded file as array.
     */
    private function handleEndpointConfigIn(array $decodedFile, array $endpointConfigIn, string $key): array
    {
        $this->callLogger->info('Handling incoming configuration for Filesystem endpoint');
        if ((array_key_exists($key, $decodedFile) === false && $key !== 'root')
            || array_key_exists($key, $endpointConfigIn) === false
        ) {
            return $decodedFile;
        }

        if (array_key_exists('mapping', $endpointConfigIn[$key]) === true) {
            $mapping = $this->entityManager->getRepository('App:Mapping')
                ->findOneBy(['reference' => $endpointConfigIn[$key]['mapping']]);
            if ($mapping === null) {
                $this->callLogger->error("Could not find mapping with reference {$endpointConfigIn[$key]['mapping']} while handling $key EndpointConfigIn for a Filesystem Source");

                return $decodedFile;
            }

            if ($key === 'root') {
                return$this->mappingService->mapping($mapping, $decodedFile);
            }

            $decodedFile[$key] = $this->mappingService->mapping($mapping, $decodedFile[$key]);
        }

        return $decodedFile;
    }//end handleEndpointConfigIn()
}//end class
