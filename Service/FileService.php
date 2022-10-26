<?php

namespace CommonGateway\CoreBundle\Service;

class FileService
{
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
