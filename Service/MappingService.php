<?php

namespace CommonGateway\CoreBundle\Service;

use Adbar\Dot;
use App\Entity\Mapping;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Twig\Environment;

/**
 * The mapping service handles the mapping (or transformation) of array A (input) to array B (output).
 *
 * More information on how to write your own mappings can be found at [/docs/mapping.md](/docs/mapping.md).
 */
class MappingService
{
    // Add symfony style bundle in order to output to the console.
    private SymfonyStyle $io;

    // Create a private variable to store the twig environment
    private Environment $twig;
    private SessionInterface $session;
    private LoggerInterface $logger;

    /**
     * Setting up the base class with required services.
     *
     * @param Environment $twig
     */
    public function __construct(
        Environment $twig,
        SessionInterface $session,
        LoggerInterface $mappingLogger
    ) {
        $this->twig = $twig;
        $this->session = $session;
        $this->logger = $mappingLogger;
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
     * Maps (transforms) an array (input) to a different array (output).
     *
     * @param Mapping $mappingObject The mapping object that forms the recipe for the mapping
     * @param array   $input         The array that need to be mapped (transformed) otherwise known as input
     *
     * @return array The result (output) of the mapping process
     */
    public function mapping(Mapping $mappingObject, array $input): array
    {
        $this->session->set('mapping', $mappingObject->getId()->toString());
        isset($this->io) ?? $this->io->debug('Mapping array based on mapping object '.$mappingObject->getName().' (id:'.$mappingObject->getId()->toString().' / ref:'.$mappingObject->getReference().') v:'.$mappingObject->getversion());
        $this->logger->info('Mapping array based on mapping object '.$mappingObject->getName().' (id:'.$mappingObject->getId()->toString().' / ref:'.$mappingObject->getReference().') v:'.$mappingObject->getversion());

        // Determine pass trough
        // Let's get the dot array based on https://github.com/adbario/php-dot-notation
        if ($mappingObject->getPassTrough()) {
            $dotArray = new Dot($input);
            isset($this->io) ?? $this->io->debug('Mapping *with* pass trough');
            $this->logger->debug('mapping *with* pass trough');
        } else {
            $dotArray = new Dot();
            isset($this->io) ?? $this->io->debug('Mapping *without* pass trough');
            $this->logger->debug('Mapping *without* pass trough');
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
                $this->logger->debug("Trying to unset an property that doesn't exist during mapping");
                continue;
            }
            $dotArray->delete($unset);
        }

        // Cast values to a specific type
        foreach ($mappingObject->getCast() as $key => $cast) {
            if (!$dotArray->has($key)) {
                isset($this->io) ?? $this->io->debug("Trying to cast an property that doesn't exist during mapping");
                $this->logger->debug("Trying to cast an property that doesn't exist during mapping");
                continue;
            }

            $value = $dotArray->get($key);

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
                // Todo: Add more casts
                default:
                    isset($this->io) ?? $this->io->debug('Trying to cast to an unsupported cast type: '.$cast);
                    $this->logger->debug('Trying to cast to an unsupported cast type: '.$cast);
                    break;
            }

            // dont reset key that was deleted on purpose
            if ($dotArray->has($key)) {
                $dotArray->set($key, $value);
            }
        }

        // Back to array
        $output = $dotArray->all();

        $result = [
            'input'      => $input,
            'output'     => $output,
            'passTrough' => $mappingObject->getPassTrough(),
            'mapping'    => $mappingObject->getMapping(),
        ];

        // Log the result
        isset($this->io) ?? $this->io->debug('Mapped object', $result);
        $this->logger->debug('Mapped object', ['mapping' => $result]);

        $this->session->remove('mapping');

        return $output;
    }
}
