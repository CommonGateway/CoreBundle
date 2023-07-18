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
 * More information on how to write your own mappings can be found at [Mappings](/docs/features/Mappings.md).
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
    private SymfonyStyle $style;

    // Create a private variable to store the twig environment.
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

    }//end __construct()

    /**
     * Set symfony style in order to output to the console.
     *
     * @param SymfonyStyle $style
     *
     * @return self
     */
    public function setStyle(SymfonyStyle $style): self
    {
        $this->style = $style;

        return $this;

    }//end setStyle()

    /**
     * Replaces strings in array keys, helpful for characters like . in array keys.
     *
     * @param array  $array       The array to encode the array keys for.
     * @param string $toReplace   The character to encode.
     * @param string $replacement The encoded character.
     *
     * @return array The array with encoded array keys
     */
    public function encodeArrayKeys(array $array, string $toReplace, string $replacement): array
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
     * @param bool    $list          Wheter we want a list instead of a sngle item
     *
     * @throws LoaderError|SyntaxError Twig Exceptions
     *
     * @return array The result (output) of the mapping process
     */
    public function mapping(Mapping $mappingObject, array $input, bool $list = false): array
    {
        // Check for list
        if ($list === true) {
            $list        = [];
            $extraValues = [];

            // Allow extra(input)values to be passed down for mapping while dealing with a list.
            if (array_key_exists('listInput', $input) === true) {
                $extraValues = $input;
                $input       = $input['listInput'];
                unset($extraValues['listInput'], $extraValues['value']);
            }

            foreach ($input as $key => $value) {
                // Mapping function expects an array for $input, make sure we always pass an array to this function.
                if (is_array($value) === false || empty($extraValues) === false) {
                    $value = array_merge(['value' => $value], $extraValues);
                }

                $list[$key] = $this->mapping($mappingObject, $value);
            }

            return $list;
        }//end if

        $input = $this->encodeArrayKeys($input, '.', '&#46;');

        isset($this->style) === true && $this->style->info('Mapping array based on mapping object '.$mappingObject->getName().' (id:'.$mappingObject->getId()->toString().' / ref:'.$mappingObject->getReference().') v:'.$mappingObject->getversion());

        // Determine pass trough.
        // Let's get the dot array based on https://github.com/adbario/php-dot-notation.
        if ($mappingObject->getPassTrough()) {
            $dotArray = new Dot($input);
            isset($this->style) === true && $this->style->info('Mapping *with* pass trough');
        } else {
            $dotArray = new Dot();
            isset($this->style) === true && $this->style->info('Mapping *without* pass trough');
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
        $unsets = ($mappingObject->getUnset() ?? []);
        foreach ($unsets as $unset) {
            if ($dotArray->has($unset) === false) {
                isset($this->style) === true && $this->style->info("Trying to unset an property that doesn't exist during mapping");
                continue;
            }

            $dotArray->delete($unset);
        }

        // Cast values to a specific type.
        $casts = ($mappingObject->getCast() ?? []);

        foreach ($casts as $key => $cast) {
            if ($dotArray->has($key) === false) {
                isset($this->style) === true && $this->style->info("Trying to cast an property that doesn't exist during mapping");
                continue;
            }

            if (is_array($cast) === false) {
                $cast = explode(',', $cast);
            }

            if ($cast === false) {
                isset($this->style) === true && $this->style->info("Cast for property $key is an empty string");
                continue;
            }

            foreach ($cast as $singleCast) {
                $this->handleCast($dotArray, $key, $singleCast);
            }
        }

        // Back to array.
        $output = $dotArray->all();

        $output = $this->encodeArrayKeys($output, '&#46;', '.');

        // If something has been defined to work on root level (i.e. the object lives on root level), we can use # to define writing the root object.
        $keys = array_keys($output);
        if (count($keys) === 1 && $keys[0] === '#') {
            $output = $output['#'];
        }

        // Log the result.
        isset($this->style) === true && $this->style->info(
            'Mapped object',
            [
                'input'      => $input,
                'output'     => $output,
                'passTrough' => $mappingObject->getPassTrough(),
                'mapping'    => $mappingObject->getMapping(),
            ]
        );

        return $output;

    }//end mapping()

    /**
     * Handles a single cast.
     *
     * @param Dot    $dotArray The dotArray of the array we are mapping.
     * @param string $key      The key of the field we want to cast.
     * @param string $cast     The type of cast we want to do.
     *
     * @return void
     */
    private function handleCast(Dot $dotArray, string $key, string $cast)
    {
        $value = $dotArray->get($key);

        // Todo: This works, we should go to php 8.0 later.
        if (str_starts_with($cast, 'unsetIfValue==')) {
            $unsetIfValue = substr($cast, 14);
            $cast         = 'unsetIfValue';
        }

        // Todo: Add more casts.
        switch ($cast) {
        case 'string':
            $value = (string) $value;
            break;
        case 'bool':
        case 'boolean':
            if ((int) $value === 1 || strtolower($value) === 'true' || strtolower($value) === 'yes') {
                $value = true;
                break;
            }

            $value = false;
            break;
        case 'int':
        case 'integer':
            $value = (int) $value;
            break;
        case 'float':
            $value = (float) $value;
            break;
        case 'array':
            $value = (array) $value;
            break;
        case 'date':
            $value = date($value);
            break;
        case 'url':
            $value = urlencode($value);
            break;
        case 'rawurl':
            $value = rawurlencode($value);
            break;
        case 'base64':
            $value = \Safe\base64_encode($value);
            break;
        case 'json':
            $value = \Safe\json_encode($value);
            break;
        case 'jsonToArray':
            $value = str_replace(['&quot;', '&amp;quot;'], '"', $value);
            $value = json_decode($value, true);
            break;
        case 'nullStringToNull':
            if ($value === 'null') {
                $value = null;
            }
            break;
        case 'coordinateStringToArray':
            $value = $this->coordinateStringToArray($value);
            break;
        case 'keyCantBeValue':
            if ($key == $value) {
                $dotArray->delete($key);
            }
            break;
        case 'unsetIfValue':
            if (isset($unsetIfValue) === true
                && $value == $unsetIfValue
                || ($unsetIfValue === '' && empty($value))
            ) {
                $dotArray->delete($key);
            }
            break;
        default:
            isset($this->style) === true && $this->style->info('Trying to cast to an unsupported cast type: '.$cast);
            break;
        }//end switch

        // Don't reset key that was deleted on purpose.
        if ($dotArray->has($key)) {
            $dotArray->set($key, $value);
        }

    }//end handleCast()

    /**
     * Converts a coordinate string to an array of coordinates.
     *
     * @param string $coordinates A string containing coordinates.
     *
     * @return array An array of coordinates.
     */
    public function coordinateStringToArray(string $coordinates): array
    {
        $halfs           = explode(' ', $coordinates);
        $point           = [];
        $coordinateArray = [];
        foreach ($halfs as $half) {
            if (count($point) > 1) {
                $coordinateArray[] = $point;
                $point             = [];
            }

            $point[] = $half;
        }//end foreach

        $coordinateArray[] = $point;

        if (count($coordinateArray) === 1) {
            $coordinateArray = $coordinateArray[0];
        }

        return $coordinateArray;

    }//end coordinateStringToArray()
}//end class
