<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\ObjectEntity;
use CommonGateway\CoreBundle\Service\CacheService;
use Doctrine\ORM\EntityManagerInterface;
use ErrorException;
use Exception;
use Symfony\Component\HttpFoundation\Response;

class RequestService
{
    private EntityManagerInterface $entityManager;
    private CacheService $cacheService;
    private array $configuration;
    private array $data;
    private ObjectEntity $object;
    private string $id;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        EntityManagerInterface $entityManager,
    ) {
        $this->entityManager = $entityManager;
    }

    /**
     *
     * @param array $data The data from the call
     * @param array $configuration The configuration from the call
     *
     * @return array The modified data
     */
    public function collectionRequestHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        $method = $this->data['request']->getMethod();

        switch ($method) {
            case 'GET':
                break;
            case 'POST':
                break;
            default:
                break;
        }



       return $this->data;
    }

    /**
     *
     * @param array $data The data from the call
     * @param array $configuration The configuration from the call
     *
     * @return array The modified data
     */
    public function itemRequestHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        $method = $this->data['request']->getMethod();
        $content = $this->data['request']->getContent();

        // Lets see if we have an object
        if(array_key_exists('id', $this->data)){
            $this->id = $data['id'];
            $this->object = $this->cacheService->getObject($data['id']);
        }

        switch ($method) {
            case 'GET':
                break;
            case 'PUT':

                if($validation = $this->object->validate($content) && $this->object->hydrate($content, true)){
                    $this->entityManager->persist($this->object);
                }
                else{
                    // Use validation to throw an error
                }
                break;
            case 'PATCH':
                if($this->object->hydrate($content) && $validation = $this->object->validate()) {
                    $this->entityManager->persist($this->object);
                }
                else{
                    // Use validation to throw an error
                }
                break;
            case 'DELETE':
                $this->entityManager->remove($this->object);
                return new Response('','202');
                break;
            default:
                break;
        }

        $this->entityManager->flush();

        return $this->data;
    }

    /**
     * This function searches all the objectEntities and formats the data
     *
     * @param array $data The data from the call
     * @param array $configuration The configuration from the call
     *
     * @return array The modified data
     */
    public function searchRequestHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        if (!$searchEntityId = $this->configuration['searchEntityId']) {
            $objectEntities = $this->entityManager->getRepository('App:ObjectEntity')->findAll();
        } else {
            $searchEntity = $this->entityManager->getRepository('App:Entity')->findBy($searchEntityId);
            $objectEntities = $this->entityManager->getRepository('App:ObjectEntity')->findAll();
        }

        var_dump(count($objectEntities));

        $response = [];
        foreach ($objectEntities as $objectEntity) {
            $response[] = [
                'entity' => $objectEntity->getEntity()->toSchema(null),
                'objectEntity' => $objectEntity->toArray()
            ];
        }

        $this->data['response'] = $response = new Response(
            json_encode($response),
            200,
            ['content-type' => 'application/json']
        );

        return $this->data;
    }

    /**
     * Creating the responce object
     *
     * @param $data
     * @return \CommonGateway\CoreBundle\Service\Response
     */
    public function createResponce($data): Response
    {
        if($data instanceof ObjectEntity){
            $data = $data->toArray();
        }
        else{
          //
        }

        return new new Response(
        json_encode($data),
        200,
        ['content-type' => 'application/json']
        );
    }
}
