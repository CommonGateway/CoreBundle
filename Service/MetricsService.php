<?php

namespace CommonGateway\CoreBundle\Service;

use CommonGateway\CoreBundle\Service\ComposerService;
use Doctrine\ORM\EntityManagerInterface;
use MongoDB\Client;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * creates arrays for prometheus
 *
 * @Author Conduction <info@conduction.nl>
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
     * The constructor sets al needed variables
     *
     * @codeCoverageIgnore We do not need to test constructors
     *
     * @param ComposerService        $composerService    The Composer service
     * @param EntityManagerInterface $entityManager      The entity manager
     * @param ParameterBagInterface  $parameters    The Parameter bag
     */
    public function __construct(
        ComposerService $composerService,
        EntityManagerInterface $entityManager,
        ParameterBagInterface $parameters
    )
    {
        $this->composerService = $composerService;
        $this->entityManager = $entityManager;
        $this->parameters = $parameters;
        
        if ($this->parameters->get('cache_url', false)) {
            $this->client = new Client($this->parameters->get('cache_url'));
        }
    }

    /**
     * Search for a given term.
     *
     * See https://getcomposer.org/doc/03-cli.md#show-info for a full list of al options and there function
     *
     * @param array $options
     *
     * @return array
     */
    public function getAll(): array
    {
        // Lets tart with a bit of default info
        // @todo the below should come out of the db
        $users= 1;
        $applications = 1;
        $organizations = 1;

        // @todo the below should come out of mango
        $requests = 1;
        $calls = 1;

        // @todo get this from the core bundle plugin
        $metrics = [
            [
                "name"=>"app_version",
                "type"=>"gauge", // todo: 3 types, we mostly use gauge and counter
                "help"=>"The current version of the application.",
                "value"=>"1.2.3" // todo: corebundle version, can get from composerService
            ],
            [
                "name"=>"app_name", // todo: Get name from corebundle
                "type"=>"gauge",
                "help"=>"The current version of the application.",
                "value"=>"Common Gateway"
            ],
            [
                "name"=>"app_description", // todo: desc corebundle
                "type"=>"gauge",
                "help"=>"The current version of the application.",
                "value"=>""
            ],
            [
                "name"=>"app_users", // todo: amount of user objects
                "type"=>"gauge",
                "help"=>"The current amount of users",
                "value"=>$users
            ],
            [
                "name"=>"app_organisations", // todo: amount of orgs
                "type"=>"gauge",
                "help"=>"The current amount of organisations",
                "value"=>$organizations
            ],
            [
                "name"=>"app_applications", // todo: amount of apps
                "type"=>"gauge",
                "help"=>"The current amount of applications",
                "value"=>$applications
            ],
            [
                "name"=>"app_requests", // todo: count requestlogs with unique request id
                "type"=>"counter", // todo: should never get lower
                "help"=>"The total amount of incomming requests handled by this gateway",
                "value"=>$requests
            ],
            [
                "name"=>"app_calls", // todo: count calllogs with unique call id
                "type"=>"counter",
                "help"=>"The total amount of outgoing calls handled by this gateway",
                "value"=>$calls
            ],
        ];

        // Let get the data from the providers
        $metrics = array_merge($metrics, $this->getErrors());
        $metrics = array_merge($metrics, $this->getPlugins());
        $metrics = array_merge($metrics, $this->getObjects());

        return $metrics;
    }//end getAll();

    /**
     * Get metrics conserning errors
     *
     * @return array
     */
    public function getErrors(): array
    {
        $collection = $this->client->logs->logs;
    
        // Count all error logs with one of these level_names
        $errorTypes = [
            "EMERGENCY" => $collection->count(['level_name' => ['$in' => ['EMERGENCY']]]),
            "ALERT" => $collection->count(['level_name' => ['$in' => ['ALERT']]]),
            "CRITICAL" => $collection->count(['level_name' => ['$in' => ['CRITICAL']]]),
            "ERROR" => $collection->count(['level_name' => ['$in' => ['ERROR']]]),
//            "WARNING" => $collection->count(['level_name' => ['$in' => ['WARNING']]]),
        ];
    
        $metrics = [
            [
                "name"  => "app_error_count",
                "type"  => "counter",
                "help"  => "The amount of errors",
                "value" => (int) $errorTypes['EMERGENCY']
                    + $errorTypes['ALERT']
                    + $errorTypes['CRITICAL']
                    + $errorTypes['ERROR']
//                    + $errorTypes['WARNING']
            ]
        ];
    
        // Create a list
        foreach ($errorTypes as $name => $count) {
            $metrics[] = [
                [
                    "name"   => "app_error_list",
                    "type"   => "counter",
                    "help"   => "The list of errors and their error level/type.",
                    "labels" => [
                        "error_level" => $name,
                    ],
                    "value"  => (int) $count
                ]
            ];
        }
    
        return $metrics;
    }// getErrors()

    /**
     * Get metrics conserning plugins
     *
     * @return array
     */
    public function getPlugins(): array
    {
        // Get all the plugins
        $plugins = $this->composerService->getAll(['--installed']);

        $metrics = [
            [
                "name"=>"app_plugins_count",
                "type"=>"gauge",
                "help"=>"The amount of installed plugins",
                "value"=>count($plugins)
            ]
        ];


        //create a list
        foreach($plugins as $plugin){
            $metrics[] = [
                [
                    "name"=>"app_installed_plugins",
                    "type"=>"gauge",
                    "help"=>"The list of installed plugins.",
                    "labels"=>[
                        "plugin_name"=>$plugin["name"],
                        "plugin_description"=>$plugin["description"],
                        "plugin_version"=>$plugin["version"],
                    ],
                    "value"=>1
                ]
            ];
        }

        return $metrics;

    }// getPlugins()


    /**
     * Get metrics conserning objects
     *
     * @return array
     */
    public function getObjects(): array
    {
        //@todo get below data from database
        $objects = 1;
        $schemas = [];

        $metrics = [
            [
                "name"=>"app_objects_count",
                "type"=>"gauge",
                "help"=>"The amount objects in the data layer",
                "value"=>$objects
            ],
            [
                "name"=>"app_schemas_count",
                "type"=>"gauge",
                "help"=>"The amount defined schemas",
                "value"=>count($schemas)
            ]
        ];

        //create a list
        foreach($schemas as $schema){
            $metrics[] = [
                [
                    "name"=>"app_schemas",
                    "type"=>"gauge",
                    "help"=>"The list of defined schemas and the amount of objects.",
                    "labels"=>[
                        "schema_name"=>$schema["name"],
                        "schema_reference"=>$schema["ref"],
                    ],
                    "value"=>$schema->getCount() // todo: amount of objects for this schema
                ]
            ];
        }

        return $metrics;

    }//end getObjects()
}//end class
