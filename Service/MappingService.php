<?php

namespace CommonGateway\CoreBundle\Service;

use Adbar\Dot;
use App\Entity\Mapping;
use Symfony\Component\Console\Style\SymfonyStyle;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;

/**
 * The mapping service handles the mapping (or transformation) of array A (input) to array B (output).
 *
 * More information on how to write your own mappings can be found at [/docs/mapping.md](/docs/mapping.md).
 *
 * @Author Ruben van der Linde <ruben@conduction.nl>, Sarai Misidjan <sarai@conduction.nl>, Robert Zondervan <robert@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class MappingService
{
    // Add symfony style bundle in order to output to the console.
    private SymfonyStyle $io;

    // Create a private variable to store the twig environment
    private Environment $twig;

    /**
     * Setting up the base class with required services.
     *
     * @param Environment $twig
     */
    public function __construct(
        Environment $twig
    ) {
        $this->twig = $twig;
    }

    /**
     * Set symfony style in order to output to the console.
     *
     * @param SymfonyStyle $io
     *
     * @return self
     */
    public function setStyle(SymfonyStyle $io): self
    {
        $this->io = $io;

        return $this;
    }

    /**
     * Replaces strings in array keys, helpful for characters like . in array keys.
     *
     * @param array  $array       The array to encode the array keys for.
     * @param string $toReplace   The character to encode.
     * @param string $replacement The encoded character.
     *
     * @return array The array with encoded array keys
     */
    private function encodeArrayKeys(array $array, string $toReplace, string $replacement): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $newKey = str_replace($toReplace, $replacement, $key);

            if (\is_array($value) === true && $value !== []) {
                $result[$newKey] = $this->encodeArrayKeys($value, $toReplace, $replacement);
                continue;
            }

            $result[$newKey] = $value;
        }

        return $result;
    }//end encodeArrayKeys()

    /**
     * Maps (transforms) an array (input) to a different array (output).
     *
     * @param Mapping $mappingObject The mapping object that forms the recipe for the mapping
     * @param array   $input         The array that need to be mapped (transformed) otherwise known as input
     *
     * @throws LoaderError|SyntaxError Twig Exceptions
     *
     * @return array The result (output) of the mapping process
     */
    public function mapping(Mapping $mappingObject, array $input): array
    {
        $input = $this->encodeArrayKeys($input, '.', '&#46;');

        isset($this->io) ?? $this->io->debug('Mapping array based on mapping object '.$mappingObject->getName().' (id:'.$mappingObject->getId()->toString().' / ref:'.$mappingObject->getReference().') v:'.$mappingObject->getversion());

        // Determine pass trough
        // Let's get the dot array based on https://github.com/adbario/php-dot-notation
        if ($mappingObject->getPassTrough()) {
            $dotArray = new Dot($input);
            isset($this->io) ?? $this->io->debug('Mapping *with* pass trough');
        } else {
            $dotArray = new Dot();
            isset($this->io) ?? $this->io->debug('Mapping *without* pass trough');
        }

        $dotInput = new Dot($input);

        // Let's do the actual mapping
        foreach ($mappingObject->getMapping() as $key => $value) {
            // If the value exists in the input dot take it from there
            if ($dotInput->has($value)) {
                $dotArray->set($key, $dotInput->get($value));
                continue;
            }

            // Render the value from twig
            $dotArray->set($key, $this->twig->createTemplate($value)->render($input));
        }

        // Unset unwanted key's
        foreach ($mappingObject->getUnset() as $unset) {
            if (!$dotArray->has($unset)) {
                isset($this->io) ?? $this->io->debug("Trying to unset an property that doesn't exist during mapping");
                continue;
            }
            $dotArray->delete($unset);
        }

        // Cast values to a specific type
        foreach ($mappingObject->getCast() as $key => $cast) {
            if (!$dotArray->has($key)) {
                isset($this->io) ?? $this->io->debug("Trying to cast an property that doesn't exist during mapping");
                continue;
            }

            $value = $dotArray->get($key);

            // todo: this works, we should go to php 8.0 later
            if (str_starts_with($cast, 'unsetIfValue==')) {
                $unsetIfValue = substr($cast, 14);
                $cast = 'unsetIfValue';
            }

            switch ($cast) {
                case 'int':
                case 'integer':
                    $value = intval($value);
                    break;
                case 'bool':
                case 'boolean':
                    echo 'i equals 1';
                    break;
                case 'string':
                    echo 'i equals 2';
                    break;
                case 'keyCantBeValue':
                    if ($key == $value) {
                        $dotArray->delete($key);
                    }
                    break;
                case 'unsetIfValue':
                    if (isset($unsetIfValue) === true && $value == $unsetIfValue) {
                        $dotArray->delete($key);
                    }
                    break;
                    // Todo: Add more casts
                case 'jsonToArray':
                    $value = str_replace(['&quot;', '&amp;quot;'], '"', $value);
                    $value = json_decode($value, true);
                    break;
                case 'coordinateStringToArray':
                    $value = $this->coordinateStringToArray($value);
                    break;
                default:
                    isset($this->io) ?? $this->io->debug('Trying to cast to an unsupported cast type: '.$cast);
                    break;
            }

            // dont reset key that was deleted on purpose
            if ($dotArray->has($key)) {
                $dotArray->set($key, $value);
            }
        }

        // Back to array
        $output = $dotArray->all();

        // If something has been defined to work on root level (i.e. the object lives on root level), we can use # to define writing the root object.
        $keys = array_keys($output);
        if (count($keys) === 1 && $keys[0] === '#') {
            $output = $output['#'];
        }

        // Log the result
        isset($this->io) ?? $this->io->debug('Mapped object', [
            'input'      => $input,
            'output'     => $output,
            'passTrough' => $mappingObject->getPassTrough(),
            'mapping'    => $mappingObject->getMapping(),
        ]);

        return $output;
    }

    /**
     * Converts a coordinate string to an array of coordinates.
     *
     * @param string $coordinates A string containing coordinates.
     *
     * @return array An array of coordinates.
     */
    public function coordinateStringToArray(string $coordinates): array
    {
        $halfs = explode(' ', $coordinates);
        $point = [];
        $coordinateArray = [];
        foreach ($halfs as $half) {
            if (count($point) > 1) {
                $coordinateArray[] = $point;
                $point = [];
            }
            $point[] = $half;
        }
        $coordinateArray[] = $point;

        if (count($coordinateArray) === 1) {
            $coordinateArray = $coordinateArray[0];
        }

        return $coordinateArray;
    }//end coordinateStringToArray()
}//end class
