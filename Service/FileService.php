<?php

namespace CommonGateway\CoreBundle\Service;

use Psr\Log\LoggerInterface;

/**
 *
 *
 * @Author Robert Zondervan <robert@conduction.nl>, Ruben van der Linde <ruben@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class FileService
{
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param LoggerInterface $fileLogger The logger interface
     */
    public function __construct(
        LoggerInterface $fileLogger
    ) {
        $this->logger = $fileLogger;
    }//end __construct()

    /**
     * @param string $baseFileName The name of the file to write
     * @param string $contents     The content to wrtie into het file
     *
     * @return string
     */
    public function writeFile(string $baseFileName, string $contents): string
    {
        $stamp = microtime().getmypid();
        file_put_contents("/srv/api/var/$baseFileName-$stamp", $contents);

        return "/srv/api/var/$baseFileName-$stamp";
    }// end writeFile()

    /**
     * @param $filename The name of the file to remove
     *
     * @return void
     */
    public function removeFile($filename): void
    {
        unlink($filename);
    }// end removeFile
}
