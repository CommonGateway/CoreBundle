<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Gateway as Source;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use League\Flysystem\ZipArchive\FilesystemZipArchiveProvider;
use League\Flysystem\ZipArchive\ZipArchiveAdapter;
class CreateFileSystemService
{

    /**
     * The local filesystem.
     *
     * @var Filesystem
     */
    private Filesystem $filesystem;

    /**
     * The class constructor.
     */
    public function __construct()
    {
        $this->localFilesystem = new \Symfony\Component\Filesystem\Filesystem();
    }//end __construct()

    /**
     * Writes a zip file in the local filesystem.
     *
     * @param string $content The string contents of the zip file.
     *
     * @return string
     */
    public function createZipFileFromContent(string $content): string
    {
        // Let's create a temporary file.
        $fileId = new Uuid();
        $filename = "/var/tmp/tmp-{$fileId->toString()}.zip";
        $this->localFilesystem->touch($filename);
        $this->localFilesystem->appendToFile($filename, $content);

        return $filename;
    }//end createZipFileFromContent()

    /**
     * Removes a zip file from the local filesystem.
     *
     * @param string $filename The file to delete.
     *
     * @return void
     */
    public function removeZipFile(string $filename): void
    {
        $this->localFilesystem->remove($filename);
    }//end removeZipFile()

    /**
     * Connects to a Filesystem.
     *
     * @param Source $source The Filesystem source to connect to.
     *
     * @return Filesystem The Filesystem Operator.
     *
     * @throws \Exception
     */
    public function openFtpFilesystem(Source $source): Filesystem
    {
        try {
            $url = \Safe\parse_url($source->getLocation());
        } catch (\Exception $exception) {
            throw new \Exception('Could not parse source location');
        }
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
    }//end openFtpFilesystem()

    /**
     * Opens a zip filesystem.
     *
     * @param string $filename The Filename of the zip file.
     *
     * @return Filesystem The Filesystem Operator.
     *
     * @throws \Exception
     */
    public function openZipFilesystem(string $filename): Filesystem
    {
        // Open the zip file.
        $provider = new FilesystemZipArchiveProvider($filename);
        $adapter = new ZipArchiveAdapter($provider);

        return new Filesystem($adapter);
    }//end openZipFilesystem
}