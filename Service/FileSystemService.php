<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Gateway as Source;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use Psr\Log\LoggerInterface;
use Safe\Exceptions\UrlException;
use Safe\Exceptions\JsonException;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\YamlEncoder;

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
class FileSystemService
{
    private EntityManagerInterface $entityManager;
    private MappingService $mappingService;
    private LoggerInterface $logger;
    
    /**
     * @param EntityManagerInterface $entityManager
     * @param MappingService $mappingService
     * @param LoggerInterface $filesystemLogger
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        MappingService $mappingService,
        LoggerInterface $filesystemLogger
    ) {
        $this->entityManager = $entityManager;
        $this->mappingService = $mappingService;
        $this->logger = $filesystemLogger;
    }
    
    /**
     * Connects to a Filesystem.
     *
     * @param Source $source The Filesystem source to connect to.
     *
     * @return FilesystemOperator The Filesystem Operator.
     *
     * @throws UrlException
     */
    public function connectFilesystem (Source $source): FilesystemOperator
    {
        $url = \Safe\parse_url($source->getLocation());
        $ssl = false;

        if ($url['scheme'] === 'sftp') {
            $ssl = true;
        }

        var_Dump('connecting filesystem');
        $connectionOptions = new FtpConnectionOptions($url['host'], $url['path'], $source->getUsername(), $source->getPassword(), $url['port'], $ssl);

        $adapter = new FtpAdapter($connectionOptions);

        var_Dump('Filesystem connected');
        return new Filesystem($adapter);
    }//end connectFilesystem()

    /**
     * Gets the content of a file from a specific file on a filesystem.
     *
     * @param FilesystemOperator $filesystem The filesystem to get a file from.
     * @param string $location The location of the file to get.
     *
     * @return string|null The file content or null.
     *
     * @throws FilesystemException
     */
    public function getFileContents(FilesystemOperator $filesystem, string $location): ?string
    {
        var_dump('get file contents');
        if ($filesystem->fileExists($location)) {
            return $filesystem->read($location);
        }
        var_dump("file $location not found");
        var_dump($filesystem->listContents('/')->toArray());
        return null;
    }//end getFileContents()

    /**
     * Decodes a file content using a given format, default = json_decode.
     *
     * @param string|null $content The content to decode.
     * @param string      $location The (file) location to get a format from if no format is given.
     * @param string|null $format The format to use when decoding the file content.
     *
     * @return array The decoded file content.
     *
     * @throws JsonException
     */
    public function decodeFile(?string $content, string $location, ?string $format = null): array
    {
        var_dump('decode file contents');
        if($format === null) {
            $fileArray = explode('.', $location);
            $format = end($fileArray);
        }
        switch ($format) {
            case 'yaml':
                $yamlEncoder = new YamlEncoder();
                return $yamlEncoder->decode($content, $format);
            case 'xml':
                $xmlEncoder = new XmlEncoder();
                return $xmlEncoder->decode($content, $format);
            case 'json':
            default:
                $data = \Safe\json_decode($content, true);
                if($data === null) {
                    return [];
                }
                return $data;
        }
    }//end decodeFile()
    
    /**
     * Calls a Filesystem source according to given configuration.
     *
     * @param Source $source The Filesystem source to call.
     * @param string $location The (file) location on the Filesystem source to call.
     * @param array $config The additional configuration to call the Filesystem source.
     *
     * @return array The decoded response array of the call.
     */
    public function call(Source $source, string $location, array $config = []): array
    {
        // todo: handleEndpointsConfigOut?
        $fileSystem = $this->connectFilesystem($source);

        $content = $this->getFileContents($fileSystem, $location);

        if (isset($config['format'])) {
            $decodedFile = $this->decodeFile($content, $location, $config['format']);
        } else {
            $decodedFile = $this->decodeFile($content, $location);
        }
        
        return $this->handleEndpointsConfigIn($source, $location, $decodedFile);
    }//end call()
    
    /**
     * Handles the endpointsConfig of a Filesystem Source after we did a guzzle call.
     * See CallService->handleEndpointsConfigIn() for how we handle this on other (/normal) type of sources.
     *
     * @param Source   $source   The Filesystem source.
     * @param string   $location The (file) location used to do a guzzle call on the Filesystem source.
     * @param array    $decodedFile The decoded file, response of the guzzle call we might want to change.
     *
     * @return array The decoded file as array.
     */
    private function handleEndpointsConfigIn(Source $source, string $location, array $decodedFile): array
    {
        $this->logger->info('Handling incoming configuration for Filesystem endpoints');
        $endpointsConfig = $source->getEndpointsConfig();
        if (empty($endpointsConfig)) {
            return $decodedFile;
        }
        
        // Let's check if the endpoint used on this source has "in" configuration in the EndpointsConfig of the source.
        if (array_key_exists($location, $endpointsConfig) === true && array_key_exists('in', $endpointsConfig[$location])) {
            $endpointConfigIn = $endpointsConfig[$location]['in'];
        } elseif (array_key_exists('global', $endpointsConfig) === true && array_key_exists('in', $endpointsConfig['global'])) {
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
     * @param array  $decodedFile The decoded file, response of the guzzle call we might want to change.
     * @param array  $endpointConfigIn The endpointConfig 'in' of a specific endpoint and Filesystem source.
     * @param string $key The specific key to check if its data needs to be changed and if so, change the data for.
     *
     * @return array The decoded file as array.
     */
    private function handleEndpointConfigIn(array $decodedFile, array $endpointConfigIn, string $key): array
    {
        $this->logger->info('Handling incoming configuration for Filesystem endpoint');
        if ((array_key_exists($key, $decodedFile) === false && $key !== 'root') || array_key_exists($key, $endpointConfigIn) === false) {
            return $decodedFile;
        }
    
        if (array_key_exists('mapping', $endpointConfigIn[$key])) {
            $mapping = $this->entityManager->getRepository('App:Mapping')->findOneBy(['reference' => $endpointConfigIn[$key]['mapping']]);
            if ($mapping === null) {
                $this->logger->error("Could not find mapping with reference {$endpointConfigIn[$key]['mapping']} while handling $key EndpointConfigIn for a Filesystem Source");
                
                return $decodedFile;
            }
            if ($key === 'root') {
                $decodedFile = $this->mappingService->mapping($mapping, $decodedFile);
            } else {
                $decodedFile[$key] = $this->mappingService->mapping($mapping, $decodedFile[$key]);
            }
        }
        
        return $decodedFile;
    }//end handleEndpointConfigIn()

}//end class
