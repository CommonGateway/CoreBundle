<?php

namespace CommonGateway\CoreBundle\Service;

use Psr\Log\LoggerInterface;

/**
 * Todo.
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
     * @var LoggerInterface The logger interface.
     */
    private LoggerInterface $logger;

    /**
     * @param LoggerInterface $fileLogger The logger interface.
     */
    public function __construct(
        LoggerInterface $fileLogger
    ) {
        $this->logger = $fileLogger;
    }//end __construct()

    /**
     * Write the file to the server
     *
     * @param string $baseFileName The name of the file to write.
     * @param string $contents     The content to write into het file.
     *
     * @return string Todo
     */
    public function writeFile(string $baseFileName, string $contents): string
    {
        $stamp = microtime().getmypid();
        file_put_contents("/srv/api/var/$baseFileName-$stamp", $contents);

        return "/srv/api/var/$baseFileName-$stamp";
    }//end writeFile()

    /**
     * @param mixed $filename The name of the file to remove
     *
     * @return void This function doesn't return anything.
     */
    public function removeFile($filename): void
    {
        unlink($filename);
    }//end removeFile()
}//end class
