<?php

namespace CommonGateway\CoreBundle\Service;

use Psr\Log\LoggerInterface;

class FileService
{

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param LoggerInterface $fileLogger
     */
    public function __construct(
        LoggerInterface $fileLogger

    ) {
        $this->logger = $fileLogger;
    }//end __construct()

    public function writeFile(string $baseFileName, string $contents): string
    {
        $stamp = microtime().getmypid();
        file_put_contents("/srv/api/var/$baseFileName-$stamp", $contents);

        return "/srv/api/var/$baseFileName-$stamp";
    }

    public function removeFile($filename): void
    {
        unlink($filename);
    }
}
