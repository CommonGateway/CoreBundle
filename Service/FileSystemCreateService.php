<?php
/**
 * Service to connect external filesystems.
 *
 * This service provides a flysystem wrapper to connect to various kinds of filesystems.
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
use League\Flysystem\Filesystem;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use League\Flysystem\ZipArchive\FilesystemZipArchiveProvider;
use League\Flysystem\ZipArchive\ZipArchiveAdapter;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

class FileSystemCreateService
{
    /**
     * The local filesystem.
     *
     * @var SymfonyFilesystem
     */
    private SymfonyFilesystem $filesystem;

    /**
     * The class constructor.
     */
    public function __construct()
    {
        $this->filesystem = new SymfonyFilesystem();
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
        $this->filesystem->touch($filename);
        $this->filesystem->appendToFile($filename, $content);

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
        $this->filesystem->remove($filename);
    }//end removeZipFile()

    /**
     * Connects to a Filesystem.
     *
     * @param Source $source The Filesystem source to connect to.
     *
     * @throws \Exception
     *
     * @return Filesystem The Filesystem Operator.
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
            $url['path'] ?? '/',
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
     * @throws \Exception
     *
     * @return Filesystem The Filesystem Operator.
     */
    public function openZipFilesystem(string $filename): Filesystem
    {
        // Open the zip file.
        $provider = new FilesystemZipArchiveProvider($filename);
        $adapter = new ZipArchiveAdapter($provider);

        return new Filesystem($adapter);
    }//end openZipFilesystem()
}//end class
