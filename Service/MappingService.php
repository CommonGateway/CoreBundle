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
 */
class MappingService
{
    /**
     * Create a private variable to store the twig environment.
     *
     * @var Environment
     */
    private Environment $twig;

    /**
     * @var SessionInterface
     */
    private SessionInterface $session;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Setting up the base class with required services.
     *
     * @param Environment      $twig
     * @param SessionInterface $session
     * @param LoggerInterface  $mappingLogger
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
     * @param Mapping $mappingObject The mapping object that forms the recipe for the mapping
     * @param array   $input         The array that need to be mapped (transformed) otherwise known as input
     *
     * @return array The result (output) of the mapping process
     */
    public function mapping(Mapping $mappingObject, array $input): array
    {
        // Log to the session that we are doing a mapping
        $this->session->set('mapping', $mappingObject->getId()->toString());

        // Determine pass trough and create dot array based on https://github.com/adbario/php-dot-notation.
        if ($mappingObject->getPassTrough() === true) {
            $dotArray = new Dot($input);
        } else {
            $dotArray = new Dot();
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
                $this->logger->debug("Trying to unset an property that doesn't exist during mapping", ['mapping'=>$mappingObject->toSchema(), 'input'=>$input, 'property'=>$unset]);
                continue;
            }

            $dotArray->delete($unset);
        }

        $dotArray = $this->cast($mappingObject, $dotArray);

        // Back to array.
        $output = $dotArray->all();

        // Log the result.
        $this->logger->info('Mapped array based on mapping object', ['mapping'=>$mappingObject->toSchema(), 'input'=>$input, 'output'=>$output]);

        $this->session->remove('mapping');

        return $output;
    }

    /**
     * Cast values to a specific type.
     *
     * @param Mapping $mappingObject The mapping object used to map
     * @param Dot     $dotArray      The current status of the mappings as a dot array
     *
     * @return Dot The status of the mapping afther casting has been applied
     */
    public function cast(Mapping $mappingObject, Dot $dotArray): Dot
    {
        // Loop trough the configured castings
        foreach ($mappingObject->getCast() as $key => $cast) {
            if (!$dotArray->has($key)) {
                $this->logger->error("Trying to cast an property that doesn't exist during mapping", ['mapping'=>$mappingObject->toSchema(), 'property'=>$key, 'cast'=>$cast]);
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
                    if ($key == $value) {
                        $dotArray->delete($key);
                    }
                    break;
                // Todo: Add more casts
                default:
                    $this->logger->error('Trying to cast to an unsupported cast type', ['mapping'=>$mappingObject->toSchema(), 'property'=>$key, 'cast'=>$cast]);
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
