<?php
/**
 * Service to call external Filesystem sources.
 *
 * This service provides a guzzle wrapper to work with Filesystem sources in the common gateway.
 *
 * @Author Robert Zondervan <robert@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
namespace CommonGateway\CoreBundle\Service;

use App\Entity\Gateway as Source;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use League\Flysystem\ZipArchive\FilesystemZipArchiveProvider;
use League\Flysystem\ZipArchive\ZipArchiveAdapter;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Safe\Exceptions\UrlException;
use Safe\Exceptions\JsonException;
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
     * The class constructor
     *
     * @param EntityManagerInterface $entityManager  The entity manager.
     * @param MappingService         $mappingService The mapping service.
     * @param LoggerInterface        $callLogger     The call logger.
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        MappingService $mappingService,
        LoggerInterface $callLogger
    ) {
        $this->entityManager = $entityManager;
        $this->mappingService = $mappingService;
        $this->callLogger = $callLogger;
    }//end __construct()

    /**
     * Connects to a Filesystem.
     *
     * @param Source $source The Filesystem source to connect to.
     *
     * @return Filesystem The Filesystem Operator.
     *
     * @throws UrlException
     */
    public function connectFilesystem(Source $source): Filesystem
    {
        $url = \Safe\parse_url($source->getLocation());
        $ssl = false;

        if ($url['scheme'] === 'sftp') {
            $ssl = true;
        }

        $connectionOptions = new FtpConnectionOptions(
            $url['host'],
            $url['path'],
            $source->getUsername(),
            $source->getPassword(),
            $url['port'],
            $ssl
        );

        $adapter = new FtpAdapter($connectionOptions);

        return new Filesystem($adapter);

    }//end connectFilesystem()

    /**
     * Gets the content of a file from a specific file on a filesystem.
     *
     * @param Filesystem $filesystem The filesystem to get a file from.
     * @param string     $location   The location of the file to get.
     *
     * @return string|null The file content or null.
     *
     * @throws FilesystemException
     */
    public function getFileContents(Filesystem $filesystem, string $location): ?string
    {
        if ($filesystem->fileExists($location) === true) {
            return $filesystem->read($location);
        }

        return null;
    }//end getFileContents()

    /**
     * Writes a zip file to a temporary file, merges the contents into an array.
     *
     * @param string $content The zip file as a string.
     *
     * @return array
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    public function getZipContents(string $content): array
    {
        // Let's create a temporary file.
        $fileId = new Uuid();
        $filename = "/var/tmp/tmp-{$fileId->toString()}.zip";
        $localFileSystem = new \Symfony\Component\Filesystem\Filesystem();
        $localFileSystem->touch($filename);
        $localFileSystem->appendToFile($filename, $content);

        // Open the temporary zip file.
        $provider = new FilesystemZipArchiveProvider($filename);
        $zip = new ZipArchiveAdapter($provider);

        // Get the files in the zip file.
        $files = $zip->listContents('/', true);

        // Recursively get data from the files in the zip file.
        $contents = [];
        foreach ($files as $file) {
            $contents[$file->path()] = $this->decodeFile(
                $zip->read($file->path()),
                $file->path()
            );
        }

        // Remove the temporary file.
        $localFileSystem->remove($filename);

        return $contents;

    }//end getZipContents()

    /**
     * Decodes a file content using a given format, default = json_decode.
     *
     * @param string|null $content  The content to decode.
     * @param string      $location The (file) location to get a format from if no format is given.
     * @param string|null $format   The format to use when decoding the file content.
     *
     * @return array The decoded file content.
     *
     * @throws JsonException
     */
    public function decodeFile(?string $content, string $location, ?string $format = null): array
    {
        if ($format === null) {
            $fileArray = explode('.', $location);
            $format = end($fileArray);
        }

        switch ($format) {
            case 'zip':
                return $this->getZipContents($content);
            case 'yaml':
                $yamlEncoder = new YamlEncoder();
                return $yamlEncoder->decode($content, $format);
            case 'xml':
                $xmlEncoder = new XmlEncoder();
                return $xmlEncoder->decode($content, $format);
            case 'json':
            default:
                $data = \Safe\json_decode($content, true);
                if ($data === null) {
                    return [];
                }
                return $data;
        }
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
        $fileSystem = $this->connectFilesystem($source);

        $content = $this->getFileContents($fileSystem, $location);

        if (isset($config['format']) === true) {
            $decodedFile = $this->decodeFile($content, $location, $config['format']);
        } else if (isset($config['format']) === false) {
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
     * @param string $key              The specific key to check if its data needs to be changed and if so, change the data for.
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
