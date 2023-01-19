<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Mapping;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Logger;
use Twig\Environment;

class MappingService
{
    private EntityManagerInterface $em;;
    private Logger $logger;

    // Create a private variable to store the twig environment
    private Environment $twig;

    public function __construct(
        EntityManagerInterface $em,
        Environment $twig
    ) {
        $this->em = $em;
        $this->twig = $twig;
        $this->logger = New Logger('mapping');
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
            // Render the value from twig
            $dotArray->set($key, $twig->createTemplate($value)->render($input));
        }

        // Unset unwanted key's
        foreach ($mappingObject->getUnset() as $unset){
            if(!$dotArray->has($unset)){
                $this->logger->error("Trying to unset an property that doensnt exist during mapping");
                continue;
            }
            $dotArray->delete($unset);
        }

        // Cast values to a specific type
        foreach ($mappingObject->getCast() as $key => $cast){
            if(!$dotArray->has($key)){
                $this->logger->error("Trying to cast an property that doensnt exist during mapping");
                continue;
            }

            $value = $dotArray->get($key);

            switch ($cast) {
                case 'int':
                case 'integer':
                    $value = intval($value);
                    break;
                // Todo: Add more casts
                default:
                    $this->logger->error("Trying to cast to an unsuported cast type: ".$cast);
                    continue;
            }

            $dotArray->set($key, $value);
        }

        // Back to arrray
        $output = $dotArray->all();

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
