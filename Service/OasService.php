<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Endpoint;
use Doctrine\ORM\EntityManagerInterface;

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
     * @param EntityManagerInterface $entityManager The Entity Manager
     */
    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;
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
                'title'      => '',
                'description'=> '',
                'version'    => '0.1.9',
            ],
            'servers' => [
                'url'         => 'http://api.example.com/v1',
                'description' => '',
            ],
            'paths'      => [],
            'components' => [
                'schemas' =>[]
            ],
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
     * @param array $oas The OAS array where security should be added
     *
     * @return array The OAS array including security
     */
    private function addEndpoints(array $oas): array
    {
        // Get all the endpoints.
        $endpoints = $this->entityManager->getRepository('App:Endpoint')->findAll();

        // Add the endpoints to the OAS.
        foreach ($endpoints as $endpoint) {
            // Add the path to the paths.

            // Lets see if we have proprties in the path
            $pathArray =  $endpoint->getPath();
            $parameters = [];
            foreach($pathArray as $key => $part) {
                if (in_array($part, $endpoint->getParameters())) {
                    $pathArray[$key] = '{' . $part . '}';
                    $parameters = [
                        'in' => 'path',
                        'name' => $part,
                        'schema' => [
                            'type' => 'string',
                            'format' => 'uuid'
                        ],
                    ];
                }//end if
            }//end for each

            $oas['paths'][implode('/', $pathArray)] = $this->getEndpointOperations($endpoint);
            $oas['paths'][implode('/', $pathArray)]['parameters'] = $parameters;


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
            if (in_array($method, ['DELETE', 'UPDATE']) === true) {
                $operations[$method] = [
                    'summary'     => $endpoint->getName(),
                    'description' => $endpoint->getDescription(),
                ];
                continue;
            }

            // In all other cases we want include a schema.
            $operations[$method] = [
                'summary'     => $endpoint->getName(),
                'description' => $endpoint->getDescription(),
                'requestBody' => [
                    'description' => $endpoint->getDescription(),
                    //'required' =>//Todo: figure out what we want to do here
                    'content' => [
                        'application/json' => '#/components/schemas/'.$endpoint->getEntities()->first()->getName(),
                        'application/xml'  => '#/components/schemas/'.$endpoint->getEntities()->first()->getName(),
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => $endpoint->getDescription(),
                        'content'     => [
                            'application/json' => '#/components/schemas/'.$endpoint->getEntities()->first()->getName(),
                            'application/xml'  => '#/components/schemas/'.$endpoint->getEntities()->first()->getName(),
                        ],
                    ],
                ],
            ];

            // If we are dealing with a get request we do not expect an requestBody.
            if ($method === 'GET') {
                unset($operations[$method]['requestBody']);
            }

            // TODO: Collection endpoints
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
