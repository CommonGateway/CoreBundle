<?php

namespace CommonGateway\CoreBundle\Service;

use Adbar\Dot;
use App\Entity\Mapping;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MappingService
{
    private EntityManagerInterface $em;

    public function __construct(
        EntityManagerInterface $em
    ) {
        $this->em = $em;
    }

    /**
     * Set symfony style in order to output to the console
     *
     * @param SymfonyStyle $io
     * @return self
     */
    public function setStyle(SymfonyStyle $io): self
    {
        $this->io = $io;
        $this->synchronizationService->setStyle($io);

        return $this;
    }

    public function mapping(Mapping $mappingObject, array $input): array
    {
        $this->io->debug("Mapping array based on mapping object ".$mappingObject->getName()." (id:".$mappingObject->getId()->toString()." / ref:".$mappingObject->getReference().") v:".$mappingObject->getversion());

        // Determine pass trough
        if($mappingObject->getPassTrough()){
            $dot = new Dot($input);
            $this->io->debug("Mapping *with* pass trough");
        }
        else{
            $dot = new Dot();
            $this->io->debug("Mapping *without* pass trough");
        }

        // Lets do actual mapping
        foreach ($mappingObject->getMapping() as $key => $value){
            $dot->set($key, $value); //todo need to be twig
        }

        // Casting
        foreach ($mappingObject->getCast() as $key => $type){

            $value = $dot->get($key);

            switch ($type) {
                case "int":
                case "integer":
                    echo "i equals 0";
                    break;
                case "bool":
                case "boolean":
                    echo "i equals 1";
                    break;
                case "string":
                    echo "i equals 2";
                    break;
                default:
                    break;
            }

            $dot->set($key, $value);
        }

        // Unset
        foreach ($mappingObject->getUnset() as $value){
            $dot->delete($value);
        }

        // Lets return the result
        return $requestMapping;
    }
}
