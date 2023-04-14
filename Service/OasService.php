<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Endpoint;
use App\Entity\Entity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * This service handles the creation of OAS documentation for a given application.
 *
 * @Author Ruben van der Linde <ruben@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
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
        ParameterBagInterface $parameters
    ) {
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
            'info'    => [
                'title'       => 'Common Gateway',
                'description' => 'The Common Gateway is a further Dutch development of the European API Platform. API Platform is a project of Les Tilleus and, in itself, an extension of the Symfony framework. API Platform is a tool for delivering APIs based on standardized documentation and is used for various French and German government projects. Including Digital state, a precursor to Xroute, GOV.UK and Common Ground. The project is now part of joinup.eu (the European equivalent of Common Ground).',
                'version'     => '1.0.3',
            ],
            'servers' => [
                [
                    'url'         => $this->parameters->get('app_url', 'https://localhost'),
                    'description' => 'The kubernetes server',
                ],
            ],
            'paths'      => [],
            'components' => [],
            'security'   => [],
            'tags'       => [],
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
     * @param Endpoint $endpoint The endpoint.
     *
     * @return array The path array
     */
    private function getPathArray(Endpoint $endpoint): array
    {
        $pathArray = $endpoint->getPath();
        if (end($pathArray) === 'id') {
            array_pop($pathArray);
        }

        return $pathArray;
    }

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
            // TODO: endpoint without entities do exist...
            if (count($endpoint->getEntities()) === 0) {
                continue;
            }//end if

            // Add the path to the paths.
            $pathArray = $this->getPathArray($endpoint);

            foreach ($endpoint->getMethods() as $method) {
                if ($method === 'GET'
                    || $method === 'PUT'
                    || $method === 'PATCH'
                    || $method === 'DELETE'
                ) {
                    $oas['paths']['/'.implode('/', $pathArray).'/{id}'][strtolower($method)] = $this->getEndpointOperations($endpoint, $method, 'item');
                }//end if

                if ($method === 'GET'
                    || $method === 'POST'
                ) {
                    if ($method === 'GET') {
                        $operationId = 'collection';
                    }//end if

                    if ($method === 'POST') {
                        $operationId = 'item';
                    }//end if

                    unset($oas['paths']['/'.implode('/', $pathArray).'/{id}']['parameters']);
                    $oas['paths']['/'.implode('/', $pathArray)][strtolower($method)] = $this->getEndpointOperations($endpoint, $method, $operationId);
                }//end if
            }//end foreach

            // Add the schemas.
            $oas['components']['schemas'] = array_merge($oas['components']['schemas'], $this->getEndpointSchemas($endpoint));
        }//end foreach

        return $oas;
    }//end addEndpoints()

    /**
     * Gets the operations for a given endpoint.
     *
     * @param Entity $entity The entity to create parameters for.
     *
     * @return array The operations for the given endpoint
     */
    private function addParameters(Entity $entity): array
    {
        $parameters = [];
        $index = 0;
        foreach ($entity->getAttributes() as $attribute) {
            if ($attribute->getType() === 'object') {
                $schema = $attribute->getObject()->toSchema();

                $properties = $schema['properties'];
            }

            $parameters[] = [
                'name'        => $attribute->getName(),
                'in'          => 'query',
                'description' => $attribute->getDescription() !== null ? $attribute->getDescription() : '',
                'required' => $attribute->getRequired() === true ? true : false,
                'schema' => [
                    'type' => $attribute->getType(),
                    'format' => $attribute->getFormat(),
                    'properties' => isset($properties) ? $properties : null,
                    'items' => [
                        'type' => 'string'
                    ]
                ]
            ];

            if ($parameters[$index]['schema']['type'] === 'datetime'
                || $parameters[$index]['schema']['type'] === 'date') {

                $parameters[$index]['schema']['type'] = 'string';
                $parameters[$index]['schema']['format'] = $attribute->getType();
            }

            if ($attribute->getType() !== 'array') {
                unset($parameters[$index]['schema']['items']);
            }

            if ($parameters[$index]['schema']['properties'] === null) {
                unset($parameters[$index]['schema']['properties']);
            }

            if ($parameters[$index]['schema']['format'] === null) {
                unset($parameters[$index]['schema']['format']);
            }

            $index++;
        }

        return $parameters;
    }

    /**
     * Gets the operations for a given endpoint.
     *
     * @param Endpoint $endpoint The endpoint to create operations for
     *
     * @return array The operations for the given endpoint
     */
    private function setCollectionResponse(Endpoint $endpoint): array
    {
        return [
            'schema' => [
                'required'   => ['count', 'results'],
                'type'       => 'object',
                'properties' => [
                    'count' => [
                        'type'    => 'integer',
                        'example' => 1,
                    ],
                    'next' => [
                        'type'     => 'string',
                        'format'   => 'uri',
                        'nullable' => true,
                    ],
                    'previous' => [
                        'type'     => 'string',
                        'format'   => 'uri',
                        'nullable' => true,
                    ],
                    'results' => [
                        'type'  => 'array',
                        'items' => [
                            '$ref' => '#/components/schemas/'.$endpoint->getEntities()->first()->getName(),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Gets the operations for a given endpoint.
     *
     * @param Endpoint $endpoint The endpoint to create operations for
     *
     * @return array The operations for the given endpoint
     */
    private function getEndpointOperations(Endpoint $endpoint, string $method = null, string $operationId): array
    {
        $operations = [];

        // We dont do a request body on GET and DELETE requests.
        if ($method === 'DELETE') {
            $operations = [
                'operationId' => strtolower($endpoint->getName().'-'.$method.'-'.$operationId),
                'summary'     => $endpoint->getName(),
                'tags'        => [strtolower($endpoint->getName())],
                'description' => $endpoint->getDescription(),
                'parameters'  => [
                    ['name'           => 'id',
                        'in'          => 'path',
                        'description' => '',
                        'required'    => true,
                        'schema'      => [
                            'type' => 'string',
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => $endpoint->getDescription(),
                        'content'     => [
                            'application/json' => [
                                'schema' => [
                                    'type'    => 'string',
                                    'example' => 'Object is successfully deleted',
                                ],
                            ],
                            'application/xml' => [
                                'schema' => [
                                    'type'    => 'string',
                                    'example' => 'Object is successfully deleted',
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            return $operations;
        }//end if

        // In all other cases we want include a schema.
        $operations = [
            'operationId' => strtolower($endpoint->getName().'-'.$method.'-'.$operationId),
            'summary'     => $endpoint->getName(),
            'tags'        => [strtolower($endpoint->getName())],
            'description' => $endpoint->getDescription(),
            'parameters'  => [
                ['name'           => 'id',
                    'in'          => 'path',
                    'description' => '',
                    'required'    => true,
                    'schema'      => [
                        'type' => 'string',
                    ],
                ],
            ],
            'requestBody' => [
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/'.$endpoint->getEntities()->first()->getName(),
                        ],
                    ],
                    'application/xml' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/'.$endpoint->getEntities()->first()->getName(),
                        ],
                    ],
                ],
            ],
            'responses' => [
                '200' => [
                    'description' => $endpoint->getDescription(),
                    'content'     => [
                        'application/json' => [
                            'schema' => [
                                '$ref' => '#/components/schemas/'.$endpoint->getEntities()->first()->getName(),
                            ],
                        ],
                        'application/xml' => [
                            'schema' => [
                                '$ref' => '#/components/schemas/'.$endpoint->getEntities()->first()->getName(),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $collectionResponse = $this->setCollectionResponse($endpoint);

        // Don't set the parameters with a GET collection request
        if ($method === 'GET' && $operationId === 'collection') {
            unset($operations['responses']['200']);
            unset($operations['parameters']);
            $operations['parameters'] = $this->addParameters($endpoint->getEntities()->first());
            $operations['responses'] = [
                '200' => [
                    'description' => 'OK',
                    'content'     => [
                        'application/json' => $collectionResponse,
                        'application/xml'  => $collectionResponse,
                    ],
                ],
            ];
        }//end if

        // Don't set the parameters with a POST request
        if ($method === 'POST') {
            unset($operations['parameters']);
        }

        // We dont do a request body on GET and DELETE requests.
        if ($method === 'GET') {
            unset($operations['requestBody']);
        }

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
