<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Endpoint;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * This service handles the creation of OAS documentation for a given application.
 */
class OasService
{
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var ParameterBagInterface
     */
    private ParameterBagInterface $parameters;

    /**
     * @param EntityManagerInterface $entityManager The Entity Manager
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        ParameterBagInterface  $parameters
    )
    {
        $this->entityManager = $entityManager;
        $this->parameters = $parameters;
    }//end __construct()

    /**
     * Create an OAS documentation for a specific application.
     *
     * @return array
     */
    public function createOas(): array
    {

        // Setup the basic oas array.
        $oas = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Common Gateway',
                'description' => 'The Common Gateway is a further Dutch development of the European API Platform. API Platform is a project of Les Tilleus and, in itself, an extension of the Symfony framework. API Platform is a tool for delivering APIs based on standardized documentation and is used for various French and German government projects. Including Digital state, a precursor to Xroute, GOV.UK and Common Ground. The project is now part of joinup.eu (the European equivalent of Common Ground).',
                'version' => '1.0.3',
            ],
            'servers' => [
                [
                    'url' => $this->parameters->get('app_url', 'https://localhost'),
                    'description' => 'The kubernetes server',
                ]
            ],
            'paths' => [],
            'components' => [],
            'security' => [],
            'tags' => [],
        ];

        // Add the endpoints.
        $oas = $this->addEndpoints($oas);

        // Add the security options.
        $oas = $this->addSecurity($oas);

        return $oas;
    }//end createOas();

    /**
     * Adds the endpoints to an OAS Array.
     *
     * @param array $oas The OAS array where security should be added
     *
     * @return array The OAS array including security
     */
    private function addEndpoints(array $oas): array
    {
        // Get all the endpoints.
        $endpoints = $this->entityManager->getRepository('App:Endpoint')->findAll();

        $oas['components']['schemas'] = [];
        // Add the endpoints to the OAS.
        foreach ($endpoints as $endpoint) {
            // Add the path to the paths.
            $pathArray = [];
            foreach ($endpoint->getPath() as $path) {
                if ($path === 'id') {
                    $pathArray[] = '{' . $path . '}';
                    continue;
                }
                $pathArray[] = $path;
            }
            $oas['paths']['/' . implode('/', $pathArray)] = $this->getEndpointOperations($endpoint);

            // Add the schemas.
            $oas['components']['schemas'] = array_merge($oas['components']['schemas'], $this->getEndpointSchemas($endpoint));
        }

        return $oas;
    }//end addEndpoints()

    /**
     * Gets the operations for a given endpoint.
     *
     * @param Endpoint $endpoint The endpoint to create operations for
     *
     * @return array The operations for the given endpoint
     */
    private function getEndpointOperations(Endpoint $endpoint): array
    {
        $operations = [];

        // Lets take a look at the methods.
        foreach ($endpoint->getMethods() as $method) {
            // We dont do a request body on GET, DELETE and UPDATE requests.
            if (in_array($method, ['DELETE']) === true) {
                $operations[strtolower($method)] = [
                    'operationId' => strtolower($endpoint->getName() . '-' . $method),
                    'summary' => $endpoint->getName(),
                    'tags' => [strtolower($endpoint->getName())],
                    'description' => $endpoint->getDescription(),
                    'responses' => [
                        '200' => [
                            'description' => $endpoint->getDescription(),
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'string',
                                        'example' => 'Object is successfully deleted'
                                    ]
                                ],
                                'application/xml' => [
                                    'schema' => [
                                        'type' => 'string',
                                        'example' => 'Object is successfully deleted'
                                    ]
                                ],
                            ],
                        ],
                    ],
                ];
                continue;
            }

            // In all other cases we want include a schema.
            $operations[strtolower($method)] = [
                'operationId' => strtolower($endpoint->getName() . '-' . $method),
                'summary' => $endpoint->getName(),
                'tags' => [strtolower($endpoint->getName())],
                'description' => $endpoint->getDescription(),
                'parameters' => [
                    ['name' => 'id',
                        'in' => 'path',
                        'description' => '',
                        'required' => true,
                        'schema' => [
                            'type' => 'string'
                        ]
                    ]
                ],
                'requestBody' => [
                    'description' => $endpoint->getDescription(),
                    //'required' =>// Todo: figure out what we want to do here
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                '$ref' => '#/components/schemas/' . $endpoint->getEntities()->first()->getName()
                            ],
                        ],
                        'application/xml' => [
                            'schema' => [
                                '$ref' => '#/components/schemas/' . $endpoint->getEntities()->first()->getName()
                            ],
                        ]
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => $endpoint->getDescription(),
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/' . $endpoint->getEntities()->first()->getName()
                                ]
                            ],
                            'application/xml' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/' . $endpoint->getEntities()->first()->getName()
                                ]
                            ],
                        ],
                    ],
                ],
            ];

            // We dont do a request body on GET, DELETE and UPDATE requests.
            if ($method === 'GET' || $method === 'PUT') {
                unset($operations[strtolower($method)]['requestBody']);
            }

            // TODO: Collection endpoints
        }//end foreach

        return $operations;
    }//end getEndpointOperations()

    /**
     * Get the schema's for a given endpoint.
     *
     * @param Endpoint $endpoint The endpoint
     *
     * @return array The schema's for that endpoint
     */
    private function getEndpointSchemas(Endpoint $endpoint): array
    {
        $schemas = [];

        foreach ($endpoint->getEntities() as $entity) {
            $schemas[$entity->getName()] = $entity->toSchema(null);
        }

        return $schemas;
    }//end getEndpointSchemas()

    /**
     * Add the security to an OAS array.
     *
     * @param array $oas The OAS array where security should be added
     *
     * @return array The OAS array including security
     */
    private function addSecurity(array $oas): array
    {
        return $oas;
    }//end addSecurity()
}//end class
