<?php

namespace CommonGateway\CoreBundle\Service;

use Adbar\Dot;
use App\Entity\Log;
use App\Entity\ObjectEntity;
use App\Service\LogService;
use App\Service\ObjectEntityService;
use App\Service\ResponseService;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class RequestService
{
    private EntityManagerInterface $entityManager;
    private CacheService $cacheService;
    private array $configuration;
    private array $data;
    private ObjectEntity $object;
    private string $id;
    // todo: we might want to move or rewrite code instead of using these services here:
    private ResponseService $responseService;
    private ObjectEntityService $objectEntityService;
    private LogService $logService;
    private CallService $callService;

    /**
     * @param EntityManagerInterface                         $entityManager
     * @param \CommonGateway\CoreBundle\Service\CacheService $cacheService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CacheService $cacheService,
        ResponseService $responseService,
        ObjectEntityService $objectEntityService,
        LogService $logService,
        CallService $callService
    ) {
        $this->entityManager = $entityManager;
        $this->cacheService = $cacheService;
        $this->responseService = $responseService;
        $this->objectEntityService = $objectEntityService;
        $this->logService = $logService;
        $this->callService = $callService;
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
     * @param array $data          The data from the call
     * @param array $configuration The configuration from the call
     *
     * @return Response The data as returned bij the origanal source
     */
    public function proxyHandler(array $data, array $configuration): Response
    {
        $this->data = $data;
        $this->configuration = $configuration;

        // We only do proxing if the endpoint forces it
        if (!$proxy = $data['endpoint']->getProxy()) {
            // @todo throw error
        }

        // Get clean query paramters without all the symfony shizzle
        $query = $this->realRequestQueryAll($this->data['method']);
        $this->data['path'] = '/'.$data['path']['{route}'];
        // Make a guzzle call to the source bassed on the incomming call
        $result = $this->callService->call(
            $proxy,
            $this->data['path'],
            $this->data['method'],
            [
                'query'   => $query,
                'headers' => $this->data['headers'],
                'body'    => $this->data['crude_body'],
            ]
        );

        // Let create a responce from the guzle call
        $responce = new Response(
            $result->getBody()->getContents(),
            $result->getStatusCode(),
            $result->getHeaders()
        );
        // @todo the obove might need a try catch

        // And don so lets return what we have
        return $responce;
    }

    /**
     * Handles incomming requests and is responsible for generating a responce.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration from the call
     *
     * @return Response The modified data
     */
    public function requestHandler(array $data, array $configuration): Response
    {
        $this->data = $data;
        $this->configuration = $configuration;

        $filters = [];

        // haat aan de de _
        if (isset($this->data['querystring'])) {
//            $query = explode('&',$this->data['querystring']);
//            foreach ($query as $row) {
//                $row = explode('=', $row);
//                $key = $row[0];
//                $value = $row[1];
//                $filters[$key] = $value;
//            }
            $filters = $this->realRequestQueryAll($this->data['method']);
            unset($filters['_search']);
        }

        // Try to grap an id
        if (isset($this->data['path']['{id}'])) {
            $this->id = $this->data['path']['{id}'];
        } elseif (isset($this->data['path']['[id]'])) {
            $this->id = $this->data['path']['[id]'];
        } elseif (isset($this->data['query']['id'])) {
            $this->id = $this->data['query']['id'];
        } elseif (isset($this->data['path']['id'])) {
            $this->id = $this->data['path']['id'];
        } elseif (isset($this->data['path']['{uuid}'])) {
            $this->id = $this->data['path']['{uuid}'];
        } elseif (isset($this->data['query']['uuid'])) {
            $this->id = $this->data['query']['uuid'];
        }

        // If we have an ID we can get an entity to work with (except on gets we handle those from cache)
        if (isset($this->id) and $this->data['method'] != 'GET') {
            $this->object = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id'=>$this->id]);
        }

        // We might have some content
        if (isset($this->data['body'])) {
            $this->content = $this->data['body'];
        }

        // Bit os savety cleanup <- dit zou eigenlijk in de hydrator moeten gebeuren
        unset($this->content['id']);
        unset($this->content['_id']);
        unset($this->content['_self']); // todo: i don't think this does anything useful?
        unset($this->content['_schema']);

        // todo: make this a function, like eavService->getRequestExtend()
        if (isset($this->data['query']['extend'])) {
            $extend = $this->data['query']['extend'];

            // Lets deal with a comma seperated list
            if (!is_array($extend)) {
                $extend = explode(',', $extend);
            }

            $dot = new Dot();
            // Lets turn the from dor attat into an propper array
            foreach ($extend as $key => $value) {
                $dot->add($value, true);
            }

            $extend = $dot->all();
        }
        $metadataSelf = $extend['_self'] ?? [];

        /** controlleren of de gebruiker ingelogd is **/

        // All prepped so lets go
        switch ($this->data['method']) {
            case 'GET':
                // We have an id (so single object)
                if (isset($this->id)) {
                    $result = $this->cacheService->getObject($this->id);

                    // create log
                    $responseLog = new Response($this->content, 200, ['CoreBundle' => 'GetItem']);
                    $session = new Session();
                    $session->set('object', $this->id);
                    $this->logService->saveLog($this->logService->makeRequest(), $responseLog, 15, $this->content);
                } else {
                    // generic search
                    $search = null;
                    if (isset($this->data['query']['_search'])) {
                        $search = $this->data['query']['_search'];
                        unset($this->data['query']['_search']);
                    }

                    //$this->data['query']['_schema'] = $this->data['endpoint']->getEntities()->first()->getReference();
                    $result = $this->cacheService->searchObjects($search, $filters, $this->data['endpoint']->getEntities()->toArray());
                }
                break;
            case 'POST':
                // We have an id on a post so die
                if (isset($this->id)) {
                    return new Response('You can not POST to an (exsisting) id, consider using PUT or PATCH instead', '400');
                }

                // We need to know the type of object that the user is trying to post, so lets look that up
                if (count($this->data['endpoint']->getEntities())) {
                    // We can make more gueses do
                    $entity = $this->data['endpoint']->getEntities()->first();
                } else {
                    return new Response('No entity could be established for your post', '400');
                }

                $this->object = new ObjectEntity($entity);

                //if ($validation = $this->object->validate($this->content) && $this->object->hydrate($content, true)) {
                if ($this->object->hydrate($this->content, true)) {
                    $this->entityManager->persist($this->object);
                    $this->cacheService->cacheObject($this->object); /* @todo this is hacky, the above schould alredy do this */
                } else {
                    // Use validation to throw an error
                }

                $result = $this->cacheService->getObject($this->object->getId());
                break;
            case 'PUT':

                // We dont have an id on a PUT so die
                if (!isset($this->id)) {
                    return new Response('', '400');
                }

                //if ($validation = $this->object->validate($this->content) && $this->object->hydrate($content, true)) {
                if ($this->object->hydrate($this->content, true)) { // This should be an unsafe hydration
                    if (array_key_exists('@dateRead', $this->content) && $this->content['@dateRead'] == false) {
                        $this->objectEntityService->setUnread($this->object);
                    }
                    $this->entityManager->persist($this->object);
                    $this->cacheService->cacheObject($this->object); /* @todo this is hacky, the above schould alredy do this */
                } else {
                    // Use validation to throw an error
                }

                $result = $this->cacheService->getObject($this->object->getId());
                break;
            case 'PATCH':

                // We dont have an id on a PATCH so die
                if (!isset($this->id)) {
                    return new Response('', '400');
                }

                //if ($this->object->hydrate($this->content) && $validation = $this->object->validate()) {
                if ($this->object->hydrate($this->content)) {
                    $this->entityManager->persist($this->object);
                    $this->cacheService->cacheObject($this->object); /* @todo this is hacky, the above schould alredy do this */
                } else {
                    // Use validation to throw an error
                }

                $result = $this->cacheService->getObject($this->object->getId());
                break;
            case 'DELETE':

                // We dont have an id on a DELETE so die
                if (!isset($this->id)) {
                    return new Response('', '400');
                }

                $this->entityManager->remove($this->object);
                $this->cacheService - removeObject($this->id); /* @todo this is hacky, the above schould alredy do this */
                $this->entityManager->flush();

                return new Response('Succesfully deleted object', '202');
                break;
            default:
                break;

                return new Response('Unkown method'.$this->data['method'], '404');
        }

        $this->entityManager->flush();
        $this->handleMetadataSelf($result, $metadataSelf);

        return $this->createResponse($result);
    }

    /**
     * @TODO
     *
     * @param array $result
     * @param array $metadataSelf
     *
     * @return void
     */
    private function handleMetadataSelf(&$result, array $metadataSelf)
    {
        // todo: Adding type array before &$result will break this function ^^^
        if (empty($metadataSelf)) {
            return;
        }

        if (isset($result['results']) && $this->data['method'] === 'GET' && !isset($this->id)) {
            array_walk($result['results'], function (&$record) {
                $record = iterator_to_array($record);
            });
            foreach ($result['results'] as &$collectionItem) {
                $this->handleMetadataSelf($collectionItem, $metadataSelf);
            }

            return;
        }

        if (!isset($result['id']) || !Uuid::isValid($result['id'])) {
            return;
        }
        $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $result['id']]);

        if (!$objectEntity instanceof ObjectEntity) {
            return;
        }
        if ($this->data['method'] === 'GET' && isset($this->id)) {
            $metadataSelf['dateRead'] = 'getItem';
        }
        $this->responseService->xCommongatewayMetadata = $metadataSelf;
        $resultMetadataSelf = (array) $result['_self'];
        $this->responseService->addToMetadata($resultMetadataSelf, 'dateRead', $objectEntity);
        $result['_self'] = $resultMetadataSelf;
    }

    /**
     * @param array $data          The data from the call
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
        if (array_key_exists('id', $this->data)) {
            $this->id = $data['id'];
            if (!$this->object = $this->cacheService->getObject($data['id'])) {
                // Throw not found
            }
        }

        switch ($method) {
            case 'GET':
                break;
            case 'PUT':

                if ($validation = $this->object->validate($content) && $this->object->hydrate($content, true)) {
                    $this->entityManager->persist($this->object);
                } else {
                    // Use validation to throw an error
                }
                break;
            case 'PATCH':
                if ($this->object->hydrate($content) && $validation = $this->object->validate()) {
                    $this->entityManager->persist($this->object);
                } else {
                    // Use validation to throw an error
                }
                break;
            case 'DELETE':
                $this->entityManager->remove($this->object);

                return new Response('', '202');
                break;
            default:
                break;
        }

        $this->entityManager->flush();

        return $this->createResponse($this->object);
    }

    /**
     * This function searches all the objectEntities and formats the data.
     *
     * @param array $data          The data from the call
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
                'entity'       => $objectEntity->getEntity()->toSchema(null),
                'objectEntity' => $objectEntity->toArray(),
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
     * Creating the responce object.
     *
     * @param $data
     *
     * @return \CommonGateway\CoreBundle\Service\Response
     */
    public function createResponse($data): Response
    {
        if ($data instanceof ObjectEntity) {
            $data = $data->toArray();
        } else {
            //
        }

        return new Response(
            json_encode($data),
            200,
            ['content-type' => 'application/json']
        );
    }
}
