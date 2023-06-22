<?php

namespace CommonGateway\CoreBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use MongoDB\Client;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Creates arrays for prometheus.
 *
 * @Author Conduction <info@conduction.nl>, Ruben van der Linde <ruben@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 *
 * See https://prometheus.io/docs/concepts/metric_types/ for mor information about metric types
 */
class MetricsService
{

    /**
     * @var Client
     */
    private Client $client;

    /**
     * @var ComposerService
     */
    private ComposerService $composerService;

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var ParameterBagInterface
     */
    private ParameterBagInterface $parameters;

    /**
     * The constructor sets al needed variables.
     *
     * @codeCoverageIgnore We do not need to test constructors
     *
     * @param ComposerService        $composerService The Composer service
     * @param EntityManagerInterface $entityManager   The entity manager
     * @param ParameterBagInterface  $parameters      The Parameter bag
     * @param Client|null            $client          The mongodb client
     */
    public function __construct(
        ComposerService $composerService,
        EntityManagerInterface $entityManager,
        ParameterBagInterface $parameters,
        ?Client $client = null
    ) {
        $this->composerService = $composerService;
        $this->entityManager   = $entityManager;
        $this->parameters      = $parameters;

        if ($this->parameters->get('cache_url', false)) {
            $this->client = new Client($this->parameters->get('cache_url'));
        } else {
            $this->client = $client;
        }

    }//end __construct()

    /**
     * Search for a given term.
     *
     * See https://getcomposer.org/doc/03-cli.md#show-info for a full list of al options and there function
     *
     * @return array
     */
    public function getAll(): array
    {
        $coreBundle = $this->composerService->getSingle('commongateway/corebundle');

        // @Todo the below should come out of mongoDB.
        $requests = 0;
        $calls    = 0;

        $metrics = [
            [
                'name'  => 'app_version',
                'type'  => 'gauge',
                'help'  => 'The current version of the application.',
                'value' => $coreBundle['version'],
            ],
            [
                'name'  => 'app_name',
                'type'  => 'gauge',
                'help'  => 'The name of the current version of the application.',
                'value' => $coreBundle['name'],
            ],
            [
                'name'  => 'app_description',
                'type'  => 'gauge',
                'help'  => 'The description of the current version of the application.',
                'value' => $coreBundle['description'],
            ],
            [
                'name'  => 'app_users',
                'type'  => 'gauge',
                'help'  => 'The current amount of users',
                'value' => $this->entityManager->getRepository('App:User')->count([]),
            ],
            [
                'name'  => 'app_organisations',
                'type'  => 'gauge',
                'help'  => 'The current amount of organisations',
                'value' => $this->entityManager->getRepository('App:Organization')->count([]),
            ],
            [
                'name'  => 'app_applications',
                'type'  => 'gauge',
                'help'  => 'The current amount of applications',
                'value' => $this->entityManager->getRepository('App:Application')->count([]),
            ],
            [
                // todo: count (request) monologs with unique request id
                'name'  => 'app_requests',
                // todo: should never get lower
                'type'  => 'counter',
                'help'  => 'The total amount of incomming requests handled by this gateway',
                'value' => $requests,
            ],
            [
                // todo: count (call) monologs with unique call id
                'name'  => 'app_calls',
                'type'  => 'counter',
                'help'  => 'The total amount of outgoing calls handled by this gateway',
                'value' => $calls,
            ],
        ];

        // Let get the data from the providers.
        $metrics = array_merge($metrics, $this->getErrors());
        $metrics = array_merge($metrics, $this->getPlugins());

        return array_merge($metrics, $this->getObjects());

    }//end getAll()

    /**
     * Get metrics concerning errors.
     *
     * @return array
     */
    public function getErrors(): array
    {
        $collection = $this->client->logs->logs;

        // Count all error logs with one of these level_names.
        $errorTypes = [
            'EMERGENCY' => $collection->count(['level_name' => ['$in' => ['EMERGENCY']]]),
            'ALERT'     => $collection->count(['level_name' => ['$in' => ['ALERT']]]),
            'CRITICAL'  => $collection->count(['level_name' => ['$in' => ['CRITICAL']]]),
            'ERROR'     => $collection->count(['level_name' => ['$in' => ['ERROR']]]),

            // NOTE: The following log types are not counted towards the total number of errors:
            'WARNING'   => $collection->count(['level_name' => ['$in' => ['WARNING']]]),
            'NOTICE'    => $collection->count(['level_name' => ['$in' => ['NOTICE']]]),
        ];

        $metrics[] = [
            'name'  => 'app_error_count',
            'type'  => 'counter',
            'help'  => "The amount of errors, this only counts logs with level_name 'EMERGENCY', 'ALERT', 'CRITICAL' or 'ERROR'.",
            'value' => ((int) $errorTypes['EMERGENCY'] + $errorTypes['ALERT'] + $errorTypes['CRITICAL'] + $errorTypes['ERROR']),
        ];

        // Create a list
        foreach ($errorTypes as $name => $count) {
            $metrics[] = [
                'name'   => 'app_error_list',
                'type'   => 'counter',
                'help'   => 'The list of errors and their error level/type.',
                'labels' => ['error_level' => $name],
                'value'  => (int) $count,
            ];
        }

        return $metrics;

    }//end getErrors()

    /**
     * Get metrics concerning plugins.
     *
     * @return array
     */
    public function getPlugins(): array
    {
        // Get all the plugins.
        $plugins = $this->composerService->getAll(['--installed']);

        $metrics[] = [
            'name'  => 'app_plugins_count',
            'type'  => 'gauge',
            'help'  => 'The amount of installed plugins',
            'value' => count($plugins),
        ];

        // Create a list.
        foreach ($plugins as $plugin) {
            $metrics[] = [
                'name'   => 'app_installed_plugins',
                'type'   => 'gauge',
                'help'   => 'The list of installed plugins.',
                'labels' => [
                    'plugin_name'        => $plugin['name'],
                    'plugin_description' => $plugin['description'],
                    'plugin_repository'  => $plugin['repository'],
                    'plugin_version'     => $plugin['version'],
                ],
                'value'  => 1,
            ];
        }

        return $metrics;

    }//end getPlugins()

    /**
     * Get metrics concerning objects.
     *
     * @return array
     */
    public function getObjects(): array
    {
        $collection = $this->client->objects->json;

        $schemas = $this->entityManager->getRepository('App:Entity')
            ->findAllSelect('e.id, e.name, e.description, e.reference, e.version');

        $metrics[] = [
            'name'  => 'app_objects_count',
            'type'  => 'gauge',
            'help'  => 'The amount objects in the data layer',
            'value' => $this->entityManager->getRepository('App:ObjectEntity')->count([]),
        ];
        $metrics[] = [
            'name'  => 'app_cached_objects_count',
            'type'  => 'gauge',
            'help'  => 'The amount objects in the data layer that are stored in the MongoDB cache',
            'value' => $collection->count([]),
        ];
        $metrics[] = [
            'name'  => 'app_schemas_count',
            'type'  => 'gauge',
            'help'  => 'The amount defined schemas',
            'value' => count($schemas),
        ];

        // Create a list.
        foreach ($schemas as $schema) {
            $filter = ['_self.schema.id' => $schema['id']->toString()];

            $metrics[] = [
                'name'   => 'app_schemas',
                'type'   => 'gauge',
                'help'   => 'The list of defined schemas and the amount of objects.',
                'labels' => [
                    'schema_name'        => $schema['name'],
                    'schema_description' => $schema['description'],
                    'schema_reference'   => $schema['reference'],
                    'schema_version'     => $schema['version'],
                ],
                'value'  => $collection->count($filter),
            ];
        }

        return $metrics;

    }//end getObjects()
}//end class
