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
        CacheService $cacheService
    ) {
        $this->entityManager = $entityManager;
        $this->cacheService = $cacheService;
    }

    /**
     * A function to replace Request->query->all() because Request->query->all() will replace some characters with an underscore.
     * This function will not.
     *
     * @param string $method The method of the Request
     *
     * @return array An array with all query parameters.
     */
    public function realRequestQueryAll(string $method = 'get'): array
    {
        $vars = [];
        if (strtolower($method) === 'get' && empty($this->data['querystring'])) {
            return $vars;
        }
        $pairs = explode('&', strtolower($method) == 'post' ? file_get_contents('php://input') : $_SERVER['QUERY_STRING']);
        foreach ($pairs as $pair) {
            $nv = explode('=', $pair);
            $name = urldecode($nv[0]);
            $value = '';
            if (count($nv) == 2) {
                $value = urldecode($nv[1]);
            }
            $matchesCount = preg_match('/(\[.*])/', $name, $matches);
            if ($matchesCount == 1) {
                $key = $matches[1];
                $name = str_replace($key, '', $name);
                $key = trim($key, '[]');
                if (!empty($key)) {
                    $vars[$name][$key] = $value;
                } else {
                    $vars[$name][] = $value;
                }
                continue;
            }
            $vars[$name] = $value;
        }

        return $vars;
    }

    /**
     *
     *
     * @param array $data The data from the call
     * @param array $configuration The configuration from the call
     *
     * @return array The modified data
     */
    public function requestHandler(array $data, array $configuration): Response
    {

        $this->data = $data;
        $this->configuration = $configuration;

        $filters = [];

        // haat aan de de _
        if(isset($this->data['querystring'])){
//            $query = explode('&',$this->data['querystring']);
//            foreach($query as $row){
//                $row = explode('=', $row);
//                $key = $row[0];
//                $value = $row[1];
//                $filters[$key] = $value;
//            }
            $filters = $this->realRequestQueryAll($this->data['method']);
            unset($filters['_search']);
        }

        // Try to grap an id
        if(isset($this->data['path']['{id}'])) {
            $this->id = $this->data['path']['{id}'];
        }
        if(isset($this->data['path']['[id]'])) {
            $this->id = $this->data['path']['[id]'];
        }
        if(isset($this->data['query']['id'])) {
            $this->id = $this->data['path']['id'];
        }
        if(isset($this->data['path']['id'])) {
            $this->id = $this->data['path']['id'];
        }

        // If we have an ID we can get an entity to work with (except on gets we handle those from cache)
        if(isset($this->id) and $this->data['method'] != 'GET'){
            $this->object = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id'=>$this->id]);
        }

        // We might have some content
        if(isset($this->data['body'])) {
            $this->content = $this->data['body'];
        }

        // Bit os savety cleanup <- dit zou eigenlijk in de hydrator moeten gebeuren
        unset($this->content['id']);
        unset($this->content['_id']);
        unset($this->content['x-commongateway-metadata']);
        unset($this->content['_schema']);

        /** controlleren of de gebruiker ingelogd is **/

        // All prepped so lets go
        switch ($this->data['method']) {
            case 'GET':
                // We have an id (so single object)
                if(isset($this->id)) {
                    $result = $this->cacheService->getObject($this->id);
                }
                else{
                    // generic search
                    $search = null;
                    if(isset($this->data['query']['_search'])) {
                        $search = $this->data['query']['_search'];
                        unset($this->data['query']['_search']);
                    }

                    //var_dump($this->data['endpoint']->getEntities()->toArray());

                    //$this->data['query']['_schema'] = $this->data['endpoint']->getEntities()->first()->getReference();
                    $results = $this->cacheService->searchObjects($search, $filters, $this->data['endpoint']->getEntities()->toArray());

                    // Lets build the page
    
                    // todo: add support for query params start, limit and page
                    $start = 0;
                    $limit = 100; // Default this high?!
                    $page = 1;
                    if ($start > 1) {
                        $offset = $start - 1;
                    } else {
                        $offset = ($page - 1) * $limit;
                    }
                    $total = count($results);
                    $pages = ceil($total / $limit);
    
                    $result = [
                        'results' => $results,
                        'limit' => $limit,
                        'total' => $total,
                        'offset' => $offset,
                        'page' => floor($offset / $limit) + 1,
                        'pages' => $pages == 0 ? 1 : $pages
                    ];
                }
                break;
            case 'POST':
                // We have an id on a post so die
                if(isset($this->id)) {
                    return new Response('You can not POST to an (exsisting) id, consider using PUT or PATCH instead','400');
                }

                // We need to know the type of object that the user is trying to post, so lets look that up
                if(count($this->data['endpoint']->getEntities())){
                    // We can make more gueses do
                    $entity = $this->data['endpoint']->getEntities()->first();
                }
                else{
                    return new Response('No entity could be established for your post','400');
                }

                $this->object = New ObjectEntity($entity);

                //if($validation = $this->object->validate($this->content) && $this->object->hydrate($content, true)){
                if($this->object->hydrate($this->content, true)){
                    $this->entityManager->persist($this->object);
                    $this->cacheService->cacheObject($this->object); /* @todo this is hacky, the above schould alredy do this */
                }
                else{
                    // Use validation to throw an error
                }

                $result = $this->cacheService->getObject($this->object->getId());
                break;
            case 'PUT':

                // We dont have an id on a PUT so die
                if(!isset($this->id)) {
                    return new Response('','400');
                }

                //if($validation = $this->object->validate($this->content) && $this->object->hydrate($content, true)){
                if($this->object->hydrate($this->content, true)){ // This should be an unsafe hydration
                    $this->entityManager->persist($this->object);
                    $this->cacheService->cacheObject($this->object); /* @todo this is hacky, the above schould alredy do this */
                }
                else{
                    // Use validation to throw an error
                }

                $result = $this->cacheService->getObject($this->object->getId());
                break;
            case 'PATCH':

                // We dont have an id on a PATCH so die
                if(!isset($this->id)) {
                    return new Response('','400');
                }

                //if($this->object->hydrate($this->content) && $validation = $this->object->validate()) {
                if($this->object->hydrate($this->content)) {
                    $this->entityManager->persist($this->object);
                    $this->cacheService->cacheObject($this->object); /* @todo this is hacky, the above schould alredy do this */

                }
                else{
                    // Use validation to throw an error
                }

                $result = $this->cacheService->getObject($this->object->getId());
                break;
            case 'DELETE':

                // We dont have an id on a DELETE so die
                if(!isset($this->id)) {
                    return new Response('','400');
                }

                $this->entityManager->remove($this->object);
                $this->cacheService-removeObject($this->id); /* @todo this is hacky, the above schould alredy do this */
                $this->entityManager->flush();
                return new Response('Succesfully deleted object','202');
                break;
            default:
                break;
                return new Response('Unkown method'. $this->data['method'],'404');
        }

        $this->entityManager->flush();
        return $this->createResponse($result);
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
            if(!$this->object = $this->cacheService->getObject($data['id'])){
                // Throw not found
            };
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

        return $this->createResponse($this->object);
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
    public function createResponse($data): Response
    {
        if($data instanceof ObjectEntity){
            $data = $data->toArray();
        }
        else{
          //
        }

        return new Response(
        json_encode($data),
        200,
        ['content-type' => 'application/json']
        );
    }
}
