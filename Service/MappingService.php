<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Mapping;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Logger;

class MappingService
{
    private EntityManagerInterface $em;;
    private Logger $logger;

    public function __construct(
        EntityManagerInterface $em
    ) {
        $this->em = $em;
        $this->logger = New Logger('cache');
    }

    public function mapping(Mapping $mappingObject, array $input): array
    {
        $output = [];

        // Check for troughput
        if($mappingObject->getPassTrough()){
            $output = $input;
        }

        // Lets get the dot array bassed on https://github.com/adbario/php-dot-notation
        $dotArray = dot($output);

        // Lets do the actual mapping
        foreach($mappingObject->getMapping() as $key => $value){
            $dotArray->set($key, $value);
        }

        // Back to arrray
        $output = $dotArray->all();

        // Unset unwanted key's
        foreach ($mappingObject->getUnset() as $unset){
            if(!isset($output[$unset])){
                $this->logger->error("Trying to unset and property that doensnt exist during mapping");
                continue;
            }
            $unset($output[$unset]);
        }

        // Log the result
        $this->logger->info('Mapped object',[
            "input" => $input,
            "output" => $output,
            "passTrough" => $mappingObject->getPassTrough(),
            "mapping" => $mappingObject->getMapping(),
        ]);

        return $output;
    }
}
