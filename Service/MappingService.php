<?php

namespace CommonGateway\CoreBundle\Service;

use Adbar\Dot;
use App\Entity\Mapping;
use Symfony\Component\Console\Style\SymfonyStyle;
use Twig\Environment;

/**
 * The mapping service handles the mapping (or transformation) of array A (input) to array B (output).
 *
 * More information on how to write your own mappings can be found at [/docs/mapping.md](/docs/mapping.md).
 */
class MappingService
{
    /**
     * Add symfony style bundle in order to output to the console.
     *
     * @var SymfonyStyle
     */
    private SymfonyStyle $io;

    /**
     * Create a private variable to store the twig environment.
     *
     * @var Environment
     */
    private Environment $twig;

    /**
     * Setting up the base class with required services.
     *
     * @param Environment $twig The twig envirnoment to use
     */
    public function __construct(
        Environment $twig
    ) {
        $this->twig = $twig;
    }

    /**
     * Set symfony style in order to output to the console.
     *
     * @param SymfonyStyle $io The symfony style to set
     *
     * @return self This object
     */
    public function setStyle(SymfonyStyle $io): self
    {
        $this->io = $io;

        return $this;
    }

    /**
     * Maps (transforms) an array (input) to a different array (output).
     *
     * @param Mapping $mappingObject The mapping object that forms the recipe for the mapping
     * @param array   $input         The array that need to be mapped (transformed) otherwise known as input
     *
     * @return array The result (output) of the mapping process
     */
    public function mapping(Mapping $mappingObject, array $input): array
    {
        isset($this->io) ?? $this->io->debug('Mapping array based on mapping object '.$mappingObject->getName().' (id:'.$mappingObject->getId()->toString().' / ref:'.$mappingObject->getReference().') v:'.$mappingObject->getversion());

        // Determine pass trough.
        if ($mappingObject->getPassTrough()) {
            // Let's create and fill the dot array based on https://github.com/adbario/php-dot-notation.
            $dotArray = new Dot($input);
            isset($this->io) ?? $this->io->debug('Mapping *with* pass trough');
        } else {
            // Let's create the dot array based on https://github.com/adbario/php-dot-notation.
            $dotArray = new Dot();
            isset($this->io) ?? $this->io->debug('Mapping *without* pass trough');
        }

        $dotInput = new Dot($input);

        // Let's do the actual mapping.
        foreach ($mappingObject->getMapping() as $key => $value) {
            // If the value exists in the input dot take it from there.
            if ($dotInput->has($value)) {
                $dotArray->set($key, $dotInput->get($value));
                continue;
            }

            // Render the value from twig.
            $dotArray->set($key, $this->twig->createTemplate($value)->render($input));
        }

        // Unset unwanted key's.
        foreach ($mappingObject->getUnset() as $unset) {
            if (!$dotArray->has($unset)) {
                isset($this->io) ?? $this->io->debug("Trying to unset an property that doesn't exist during mapping");
                continue;
            }
            $dotArray->delete($unset);
        }

        $dotArray = $this->cast($mappingObject, $dotArray);

        // Back to array.
        $output = $dotArray->all();

        // Log the result.
        isset($this->io) ?? $this->io->debug('Mapped object', [
            'input'      => $input,
            'output'     => $output,
            'passTrough' => $mappingObject->getPassTrough(),
            'mapping'    => $mappingObject->getMapping(),
        ]);

        return $output;
    }

    /**
     * Cast values to a specific type
     *
     * @param Mapping $mappingObject The mapping object used to map
     * @param Dot $dotArray The current status of the mappings as a dot array
     *
     * @return Dot The status of the mapping afther casting has been applied
     */
    public function cast(Mapping $mappingObject,  Dot $dotArray):Dot{
        foreach ($mappingObject->getCast() as $key => $cast) {
            if (!$dotArray->has($key)) {
                    isset($this->io) ?? $this->io->debug("Trying to cast an property that doesn't exist during mapping");
                continue;
            }

            $value = $dotArray->get($key);

            switch ($cast) {
                case 'int':
                case 'integer':
                    $value = (int) $value;
                    break;
                case 'bool':
                case 'boolean':
                    $value = (bool) $value;
                    break;
                case 'string':
                    $value = (string) $value;
                    break;
                case 'keyCantBeValue':
                    if ($key == $value) {
                        $dotArray->delete($key);
                    }
                    break;
                // Todo: Add more casts
                default:
                    isset($this->io) ?? $this->io->debug('Trying to cast to an unsupported cast type: '.$cast);
                    break;
            } //end switch

            // Don't reset key that was deleted on purpose.
            if ($dotArray->has($key) === true) {
                $dotArray->set($key, $value);
            }
        }

        return $dotArray;
    }
}
