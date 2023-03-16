<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Gateway as Source;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;

use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\YamlEncoder;
use const FTP_BINARY;

class FileSystemService
{

    /**
     * @param Source $source
     * @param string $path
     * @return FilesystemOperator
     *
     * @throws \Safe\Exceptions\UrlException
     */
    public function connectFileSystem (Source $source): FilesystemOperator
    {
        $url = \Safe\parse_url($source->getLocation());
        $ssl = false;

        if($url['scheme'] === 'sftp') {
            $ssl = true;
        }

        var_Dump('connecting filesystem');
        $connectionOptions = new FtpConnectionOptions($url['host'], $url['path'], $source->getUsername(), $source->getPassword(), $url['port'], $ssl);

        $adapter = new FtpAdapter($connectionOptions);

        var_Dump('Filesystem connected');
        return new Filesystem($adapter);
    }//end connectFileSystem()

    /**
     * @param FilesystemOperator $filesystem
     * @param string $location
     * @return string|null
     *
     * @throws \League\Flysystem\FilesystemException
     */
    public function getFileContents(FilesystemOperator $filesystem, string $location): ?string
    {
        var_dump('get file contents');
        if($filesystem->fileExists($location)) {
            return $filesystem->read($location);
        }
        var_dump("file $location not found");
        var_dump($filesystem->listContents('/')->toArray());
        return null;
    }//end getFileContents()

    /**
     * @param string|null $content
     * @param string      $location
     * @param string|null $format
     *
     * @return array
     *
     * @throws \Safe\Exceptions\JsonException
     */
    public function decodeFile(?string $content, string $location, ?string $format = null): array
    {
        var_dump('decode file contents');
        if($format === null) {
            $fileArray = explode('.', $location);
            $format = end($fileArray);
        }
        switch($format) {
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

    public function call(Source $source, string $location, array $config = []): array
    {
        $fileSystem = $this->connectFileSystem($source);

        $content = $this->getFileContents($fileSystem, $location);

        if(isset($config['format'])) {

            return $this->decodeFile($content, $location, $config['format']);
        }

        return $this->decodeFile($content, $location);
    }//end call()

}//end class
