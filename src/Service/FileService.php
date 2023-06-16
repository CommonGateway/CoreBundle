<?php

namespace CommonGateway\CoreBundle\Service;

/**
 * @Author Robert Zondervan <robert@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class FileService
{


    public function writeFile(string $baseFileName, string $contents): string
    {
        $stamp = microtime().getmypid();
        file_put_contents("/srv/api/var/$baseFileName-$stamp", $contents);

        return "/srv/api/var/$baseFileName-$stamp";

    }//end writeFile()


    public function removeFile($filename): void
    {
        unlink($filename);

    }//end removeFile()


}//end class
