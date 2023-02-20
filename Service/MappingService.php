<?php

namespace CommonGateway\CoreBundle\Service;

use Adbar\Dot;
use App\Entity\Mapping;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Twig\Environment;

/**
 * The mapping service handles the mapping (or transformation) of array A (input) to array B (output).
 *
 * More information on how to write your own mappings can be found at [/docs/mapping.md](/docs/mapping.md).
 *
 * @Author Robert Zondervan <robert@conduction.nl>, Ruben van der Linde <ruben@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class MappingService
{
    /**
     * @var Environment Create a private variable to store the twig environment.
     */
    private Environment $twig;

    /**
     * @var SessionInterface The current session.
     */
    private SessionInterface $session;

    /**
     * @var LoggerInterface The logger interface.
     */
    private LoggerInterface $logger;

    /**
     * Setting up the base class with required services.
     *
     * @param Environment      $twig          The twig environment.
     * @param SessionInterface $session       The current session.
     * @param LoggerInterface  $mappingLogger The logger.
     */
    public function __construct(
        Environment $twig,
        SessionInterface $session,
        LoggerInterface $mappingLogger
    ) {
        $this->twig = $twig;
        $this->session = $session;
        $this->logger = $mappingLogger;
    }//end __construct()

    /**
     * Maps (transforms) an array (input) to a different array (output).
     *
     * @param Mapping $mappingObject The mapping object that forms the recipe for the mapping.
     * @param array   $input         The array that need to be mapped (transformed) otherwise known as input.
     *
     * @return array The result (output) of the mapping process.
     */
    public function mapping(Mapping $mappingObject, array $input): array
    {
        // Log to the session that we are doing a mapping.
        $this->session->set('mapping', $mappingObject->getId()->toString());

        // Ccreate dot array based on https://github.com/adbario/php-dot-notation.
        $dotArray = new Dot();
        // Determine pass trough and fill the array if it is set
        if ($mappingObject->getPassTrough() === true) {
            $dotArray = new Dot($input);
        }

        $dotInput = new Dot($input);

        // Let's do the actual mapping.
        foreach ($mappingObject->getMapping() as $key => $value) {
            // If the value exists in the input dot take it from there.
            if ($dotInput->has($value) === true) {
                $dotArray->set($key, $dotInput->get($value));
                continue;
            }

            // Render the value from twig.
            $dotArray->set($key, $this->twig->createTemplate($value)->render($input));
        }

        // Unset unwanted key's.
        foreach ($mappingObject->getUnset() as $unset) {
            if ($dotArray->has($unset) === false) {
                $this->logger->debug("Trying to unset an property that doesn't exist during mapping", ['mapping' => $mappingObject->toSchema(), 'input' => $input, 'property' => $unset]);
                continue;
            }

            $dotArray->delete($unset);
        }

        $dotArray = $this->cast($mappingObject, $dotArray);

        // Back to array.
        $output = $dotArray->all();

        // Log the result.
        $this->logger->info('Mapped array based on mapping object', ['mapping' => $mappingObject->toSchema(), 'input' => $input, 'output' => $output]);

        $this->session->remove('mapping');

        return $output;
    }//end mapping()

    /**
     * Cast values to a specific type.
     *
     * This function breaks complexity rules, but since a switch is more effective a design desicion was made to allow it
     *
     * @param Mapping $mappingObject The mapping object used to map.
     * @param Dot     $dotArray      The current status of the mappings as a dot array.
     *
     * @return Dot The status of the mapping afther casting has been applied.
     */
    public function cast(Mapping $mappingObject, Dot $dotArray): Dot
    {
        // Loop trough the configured castings.
        foreach ($mappingObject->getCast() as $key => $cast) {
            if ($dotArray->has($key) === false) {
                $this->logger->error("Trying to cast an property that doesn't exist during mapping", ['mapping' => $mappingObject->toSchema(), 'property' => $key, 'cast' => $cast]);
                continue;
            }

            // Get the value.
            $value = $dotArray->get($key);

            // Do the casting.
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
                    if ($key === $value) {
                        $dotArray->delete($key);
                    }
                    break;
                case 'jsonToArray':
                    $value = str_replace(['&quot;', '&amp;quot;'], '"', $value);
                    $value = json_decode($value, true);
                default:
                    $this->logger->error('Trying to cast to an unsupported cast type', ['mapping' => $mappingObject->toSchema(), 'property' => $key, 'cast' => $cast]);
                    break;
            }//end switch

            // Don't reset key that was deleted on purpose.
            if ($dotArray->has($key) === true) {
                $dotArray->set($key, $value);
            }
        }//end foreach

        return $dotArray;
    }//end cast()
}
