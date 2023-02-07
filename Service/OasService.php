<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Endpoint;
use Doctrine\ORM\EntityManagerInterface;

/**
 * This service handles the creation of OAS documentation for a given application
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
     * Create an OAS documentation for a specific application
     *
     * @return array
     */
    public function createOas():array{

        // Setup the basic oas array.
        $oas = [
            'openapi' => '3.0.0',
            'info' => [
                'title' =>'',
                'description'=>'',
                'version'=> '0.1.9'
            ],
            'servers' => [
                'url' => 'http://api.example.com/v1',
                'description' => ''
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
     * Adds the endpoints to an OAS Array
     *
     * @param array $oas The OAS array where security should be added
     * @return array The OAS array including security
     */
    private function addEndpoints(array  $oas): array{
        // Get all the endpoints.
        $endpoints = $this->entityManager->getRepository('App:Endpoint')->findAll();

        // Lets start ann array of schema's that we must include.


        // Add the endpoints to the OAS.
        foreach ($endpoints as $endpoint){
            // Add the path to the paths.
            $oas['paths'][implode('/', $endpoint->getPath())] = $this->getEndpointOperations($endpoint);

            // Add the schemas.
            $oas['components']['schemas'] = array_merge($oas['components']['schemas'],$this->getEndpointSchemas($endpoint));
        }

        return $oas;
    }//end addEndpoints()

    /**
     * Gets the operations for a given endpoint
     *
     * @param Endpoint $endpoint The endpoint to create operations for
     * @return array The operations for the given endpoint
     */
    private function getEndpointOperations(Endpoint $endpoint): array{

        $operations =[];

        // Lets take a look at the methods.
        foreach($endpoint->getMethods() as $method) {
            // We dont do a request body on GET, DELETE and UPDATE requests.
            if(in_array($method,['DELETE','UPDATE']) === true){
                $operations[$method] = [
                    'summary' => $endpoint->getTitle(),
                    'description' => $endpoint->getDescription()
                ];
                continue;
            }


            // In all other cases we want include a schema.
            $operations[$method] = [
                'summary' => $endpoint->getTitle(),
                'description' => $endpoint->getDescription(),
                'requestBody' => [
                    'description' => $endpoint->getDescription(),
                    //'required' => // Todo: figure out what we want to do here
                    'content' => [
                        'application/json' => '#/components/schemas/'.$endpoint->getEntites()->first()->getName(),
                        'application/xml' => '#/components/schemas/'.$endpoint->getEntites()->first()->getName()
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => $endpoint->getDescription(),
                        'content' => [
                            'application/json' => '#/components/schemas/'.$endpoint->getEntites()->first()->getName(),
                            'application/xml' => '#/components/schemas/'.$endpoint->getEntites()->first()->getName()
                        ]
                    ]
                ]
            ];

            // If we are dealing with a get request we do not expect an requestBody
            if($method === 'GET'){
                unset($operations[$method]['requestBody']);
            }

            // TODO: Collection endpoints

        }

        return $operations;
    }

    /**
     * Get the schema's for a given endpoint
     *
     * @param Endpoint $endpoint The endpoint
     * @return array The schema's for that endpoint
     */
    private function getEndpointSchemas(Endpoint $endpoint): array{
        $schemas = [];

        foreach($endpoint->getEntities as $entity){
            $schemas[$entity->getName()] = $entity->getSchema();
        }

        return $schemas;
    }//end getEndpointOperations()

    /**
     * Add the security to an OAS array
     *
     * @param array $oas The OAS array where security should be added
     * @return array The OAS array including security
     */
    private function addSecurity(array $oas): array{


        return $oas;
    }//end addSecurity()
}
