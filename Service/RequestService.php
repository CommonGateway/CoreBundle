<?php

namespace CommonGateway\CoreBundle\Service;

use Adbar\Dot;
use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\Gateway as Source;
use App\Entity\Log;
use App\Entity\ObjectEntity;
use App\Event\ActionEvent;
use App\Service\LogService;
use App\Service\ObjectEntityService;
use App\Service\ResponseService;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Handles incomming request from endpoints or controllers that relate to the gateways object structure (eav).
 */
class RequestService
{
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var CacheService
     */
    private CacheService $cacheService;

    /**
     * @var array
     */
    private array $configuration;

    /**
     * @var array
     */
    private array $data;

    /**
     * @var ObjectEntity
     */
    private ObjectEntity $object;

    /**
     * @var string
     */
    private string $id;

    /**
     * @var
     */
    private $schema; // todo: cast to Entity|Boolean in php 8

    // todo: we might want to move or rewrite code instead of using these services here:

    /**
     * @var ResponseService
     */
    private ResponseService $responseService;

    /**
     * @var ObjectEntityService
     */
    private ObjectEntityService $objectEntityService;

    /**
     * @var LogService
     */
    private LogService $logService;

    /**
     * @var CallService
     */
    private CallService $callService;

    /**
     * @var Security
     */
    private Security $security;

    /**
     * @var EventDispatcherInterface
     */
    private EventDispatcherInterface $eventDispatcher;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @param EntityManagerInterface   $entityManager
     * @param CacheService             $cacheService
     * @param ResponseService          $responseService
     * @param ObjectEntityService      $objectEntityService
     * @param LogService               $logService
     * @param CallService              $callService
     * @param Security                 $security
     * @param EventDispatcherInterface $eventDispatcher
     * @param SerializerInterface      $serializer
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CacheService $cacheService,
        ResponseService $responseService,
        ObjectEntityService $objectEntityService,
        LogService $logService,
        CallService $callService,
        Security $security,
        EventDispatcherInterface $eventDispatcher,
        SerializerInterface $serializer
    ) {
        $this->entityManager = $entityManager;
        $this->cacheService = $cacheService;
        $this->responseService = $responseService;
        $this->objectEntityService = $objectEntityService;
        $this->logService = $logService;
        $this->callService = $callService;
        $this->security = $security;
        $this->eventDispatcher = $eventDispatcher;
        $this->serializer = $serializer;
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

        if (isset($_SERVER['QUERY_STRING'])) {
            $pairs = explode('&', $_SERVER['QUERY_STRING']);
            foreach ($pairs as $pair) {
                $nv = explode('=', $pair);
                $name = urldecode($nv[0]);
                $value = '';
                if (count($nv) == 2) {
                    $value = urldecode($nv[1]);
                }

                $this->recursiveRequestQueryKey($vars, $name, explode('[', $name)[0], $value);
            }

            return $vars;
        }

        return [];
    }

    /**
     * Get the ID from given parameters.
     *
     * @param array $object
     *
     * @return string|false
     */
    public function getId(array $object)
    {
        // Try to grap an id
        if (isset($this->data['path']['{id}'])) {
            return $this->data['path']['{id}'];
        } elseif (isset($this->data['path']['[id]'])) {
            return $this->data['path']['[id]'];
        } elseif (isset($this->data['query']['id'])) {
            return $this->data['query']['id'];
        } elseif (isset($this->data['path']['id'])) {
            return$this->data['path']['id'];
        } elseif (isset($this->data['path']['{uuid}'])) {
            return $this->data['path']['{uuid}'];
        } elseif (isset($this->data['query']['uuid'])) {
            return$this->data['query']['uuid'];
        } elseif (isset($this->content['id'])) { // the id might also be passed trough the object itself
            return $this->content['id'];
        } elseif (isset($this->content['uuid'])) {
            return $this->content['uuid'];
        }

        return false;
    }

    /**
     * Get the schema from given parameters returns false if no schema could be established.
     *
     * @param array $parameters
     *
     * @return Entity|false
     */
    public function getSchema(array $parameters)
    {

        // If we have an object this is easy
        if (isset($this->object)) {
            return $this->object->getEntity();
        }

        // Pull the id or reference from the content
        if (isset($this->content['_self']['schema']['id'])) {
            $id = $this->content['_self']['schema']['id'];
        }
        if (isset($this->content['_self']['schema']['ref'])) {
            $reference = $this->content['_self']['schema']['ref'];
        }
        if (isset($this->content['_self']['schema']['reference'])) {
            $reference = $this->content['_self']['schema']['reference'];
        }

        // In normal securmtances we expect a all to com form an endpoint so...
        if (isset($parameters['endpoint'])) {
            // The endpoint contains exactly one schema
            if (count($this->data['endpoint']->getEntities()) == 1) {
                return $this->data['endpoint']->getEntities()->first();
            }
            // The endpoint contains multiple schema's
            if (count($this->data['endpoint']->getEntities()) >= 1) {
                // todo: so right now if we dont have an id or ref and multpile options we "guese" the first, it that smart?
                $criteria = Criteria::create()->orderBy(['date_created' => Criteria::DESC]);
                if (isset($id)) {
                    $criteria->where(['id' => $id]);
                }
                if (isset($reference)) {
                    $criteria->where(['reference' => $reference]);
                }

                return $this->data['endpoint']->getEntities()->matching($criteria)->first();
            }
            // The  endpoint contains no schema's so there is no limit we dont need to do anything
        }

        // We only end up here if there is no endpoint or an unlimited endpoint
        if (isset($id)) {
            return $this->entityManager->getRepository('App:Entity')->findOneBy(['id' => $id]);
        }
        if (isset($reference)) {
            return $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $reference]);
        }
        // There is no way to establish an schema so
        else {
            return false;
        }
    }

    /**
     * This function adds a single query param to the given $vars array. ?$name=$value
     * Will check if request query $name has [...] inside the parameter, like this: ?queryParam[$nameKey]=$value.
     * Works recursive, so in case we have ?queryParam[$nameKey][$anotherNameKey][etc][etc]=$value.
     * Also checks for queryParams ending on [] like: ?queryParam[$nameKey][] (or just ?queryParam[]), if this is the case
     * this function will add given value to an array of [queryParam][$nameKey][] = $value or [queryParam][] = $value.
     * If none of the above this function will just add [queryParam] = $value to $vars.
     *
     * @param array  $vars    The vars array we are going to store the query parameter in
     * @param string $name    The full $name of the query param, like this: ?$name=$value
     * @param string $nameKey The full $name of the query param, unless it contains [] like: ?queryParam[$nameKey]=$value
     * @param string $value   The full $value of the query param, like this: ?$name=$value
     *
     * @return void
     */
    private function recursiveRequestQueryKey(array &$vars, string $name, string $nameKey, string $value)
    {
        $matchesCount = preg_match('/(\[[^[\]]*])/', $name, $matches);
        if ($matchesCount > 0) {
            $key = $matches[0];
            $name = str_replace($key, '', $name);
            $key = trim($key, '[]');
            if (!empty($key)) {
                $vars[$nameKey] = $vars[$nameKey] ?? [];
                $this->recursiveRequestQueryKey($vars[$nameKey], $name, $key, $value);
            } else {
                $vars[$nameKey][] = $value;
            }
        } else {
            $vars[$nameKey] = $value;
        }
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
        if (!$data['endpoint'] instanceof Endpoint || !$proxy = $data['endpoint']->getProxy()) {
            $message = !$data['endpoint'] instanceof Endpoint ?
                "No Endpoint in data['endpoint']" :
                "This Endpoint has no Proxy: {$data['endpoint']->getName()}";

            return new Response(
                json_encode(['Message' => $message]),
                Response::HTTP_NOT_FOUND,
                ['content-type' => 'application/json']
            );
        }

        if ($proxy instanceof Source && !$proxy->getIsEnabled()) {
            return new Response(
                json_encode(['Message' => "This Source is not enabled: {$proxy->getName()}"]),
                Response::HTTP_OK, // This should be ok so we can disable Sources without creating error responses?
                ['content-type' => 'application/json']
            );
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

        // @todo the above might need a try catch

        // And don so lets return what we have
        return $responce;
    }

    /**
     * Get a scopes array for the current user (or of the anonymus if no user s logged in).
     *
     * @return array
     */
    public function getScopes(): ?array
    {
        if ($user = $this->security->getUser()) {
            return $user->getScopes();
        } else {
            $anonymousSecurityGroup = $this->entityManager->getRepository('App:SecurityGroup')->findOneBy(['anonymous'=>true]);
            if ($anonymousSecurityGroup) {
                return $anonymousSecurityGroup->getScopes();
            }
        }

        // Lets play it save
        return [];
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
        }

        // Get the ID
        $this->id = $this->getId($this->data);

        // If we have an ID we can get an entity to work with (except on gets we handle those from cache)
        if (isset($this->id) && $this->id && $this->data['method'] != 'GET') {
            $this->object = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $this->id]);
        }

        // Lets pas the part variables to filters
        // todo: this is hacky
        foreach ($this->data['path'] as $key => $value) {
            if (strpos($key, '{') !== false) {
                if ($key !== '{id}') {
                    $keyExplodedFilter = explode('{', $key);
                    $keyFilter = explode('}', $keyExplodedFilter[1]);
                    $filters['_search'] = $value;
                }
            }
        }

        // We might have some content
        if (isset($this->data['body'])) {
            $this->content = $this->data['body'];
        }

        // Get the schema
        $this->schema = $this->getSchema($this->data);

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

        // todo: controlleren of de gebruiker ingelogd is

        // Make a list of schema's that are allowed for this endpoint
        $allowedSchemas['id'] = [];
        $allowedSchemas['name'] = [];
        if (isset($this->data['endpoint'])) {
            foreach ($this->data['endpoint']->getEntities() as $entity) {
                $allowedSchemas['id'][] = $entity->getId()->toString();
                $allowedSchemas['name'][] = $entity->getName();
            }
        }

        // Security
        $scopes = $this->getScopes();
        foreach ($allowedSchemas['id'] as $schema) {
            if (!isset($scopes[$schema][$this->data['method']])) {
                // THROW SECURITY ERROR AND EXIT
            }
        }

        // All prepped so lets go
        // todo: split these into functions?
        switch ($this->data['method']) {
            case 'GET':
                // We have an id (so single object)
                if (isset($this->id) && $this->id) {
                    $result = $this->cacheService->getObject($this->id);

                    // If we do not have an object we throw an 404
                    if (!$result) {
                        return new Response($this->serializer->serialize([
                            'message' => 'Could not find an object with id '.$this->id,
                            'type'    => 'Bad Request',
                            'path'    => implode(', ', $allowedSchemas['name']),
                            'data'    => ['id' => $this->id],
                        ], 'json'), Response::HTTP_NOT_FOUND);
                    }

                    // Lets see if the found result is allowd for this endpoint
                    if (isset($this->data['endpoint']) && !in_array($result['_self']['schema']['id'], $allowedSchemas['id'])) {
                        return new Response('Object is not supported by this endpoint', '406');
                    }

                    // create log
                    // todo if $this->content is array and not string/null, cause someone could do a get item call with a body...
                    $responseLog = new Response(is_string($this->content) || is_null($this->content) ? $this->content : null, 200, ['CoreBundle' => 'GetItem']);
                    $session = new Session();
                    $session->set('object', $this->id);

                    // todo: This log is needed so we know an user has 'read' this object
                    $this->logService->saveLog($this->logService->makeRequest(), $responseLog, 15, is_array($this->content) ? json_encode($this->content) : $this->content);
                } else {
                    //$this->data['query']['_schema'] = $this->data['endpoint']->getEntities()->first()->getReference();
                    $result = $this->cacheService->searchObjects(null, $filters, $allowedSchemas['id']);
                }
                break;
            case 'POST':
                $eventType = 'commongateway.object.create';

                // We have an id on a post so die
                if (isset($this->id) && $this->id) {
                    return new Response('You can not POST to an (exsisting) id, consider using PUT or PATCH instead', '400');
                }

                // We need to know the type of object that the user is trying to post, so lets look that up
                if (!isset($this->schema) || !$this->schema) {
                    return new Response('No schema could be established for your request', '400');
                }

                // Lets see if the found result is allowd for this endpoint
                if (isset($this->data['endpoint']) && !in_array($this->schema->getId(), $allowedSchemas['id'])) {
                    return new Response('Object is not supported by this endpoint', '406');
                }

                $this->object = new ObjectEntity($this->schema);
                $this->object->setOwner($this->security->getUser()->getUserIdentifier());

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
                $eventType = 'commongateway.object.update';

                // We dont have an id on a PUT so die
                if (!isset($this->id)) {
                    return new Response('No id could be established for your request', '400');
                }

                // We need to know the type of object that the user is trying to post, so lets look that up
                if (!isset($this->schema) || !$this->schema) {
                    return new Response('No schema could be established for your request', '400');
                }

                // Lets see if the found result is allowd for this endpoint
                if (isset($this->data['endpoint']) && !in_array($this->schema->getId(), $allowedSchemas['id'])) {
                    return new Response('Object is not supported by this endpoint', '406');
                }

                //if ($validation = $this->object->validate($this->content) && $this->object->hydrate($content, true)) {
                if ($this->object->hydrate($this->content, true)) { // This should be an unsafe hydration
                    if (array_key_exists('@dateRead', $this->content) && $this->content['@dateRead'] == false) {
                        $this->objectEntityService->setUnread($this->object);
                    }
                    $this->entityManager->persist($this->object);
                    $this->entityManager->flush();
                } else {
                    // Use validation to throw an error
                }

                $result = $this->cacheService->getObject($this->object->getId());
                break;
            case 'PATCH':
                $eventType = 'commongateway.object.update';

                // We dont have an id on a PUT so die
                if (!isset($this->id)) {
                    return new Response('No id could be established for your request', '400');
                }

                // We need to know the type of object that the user is trying to post, so lets look that up
                if (!isset($this->schema) || !$this->schema) {
                    return new Response('No schema could be established for your request', '400');
                }

                // Lets see if the found result is allowd for this endpoint
                if (isset($this->data['endpoint']) && !in_array($this->schema->getId(), $allowedSchemas['id'])) {
                    return new Response('Object is not supported by this endpoint', '406');
                }

                //if ($this->object->hydrate($this->content) && $validation = $this->object->validate()) {
                if ($this->object->hydrate($this->content)) {
                    if (array_key_exists('@dateRead', $this->content) && $this->content['@dateRead'] == false) {
                        $this->objectEntityService->setUnread($this->object);
                    }
                    $this->entityManager->persist($this->object);
                    $this->entityManager->flush();
                } else {
                    // Use validation to throw an error
                }

                $result = $this->cacheService->getObject($this->object->getId());
                break;
            case 'DELETE':

                // We dont have an id on a PUT so die
                if (!isset($this->id)) {
                    return new Response('No id could be established for your request', '400');
                }

                // We need to know the type of object that the user is trying to post, so lets look that up
                if (!isset($this->schema) || !$this->schema) {
                    return new Response('No schema could be established for your request', '400');
                }

                // Lets see if the found result is allowd for this endpoint
                if (isset($this->data['endpoint']) && !in_array($this->schema->getId(), $allowedSchemas['id'])) {
                    return new Response('Object is not supported by this endpoint', '406');
                }

                $this->entityManager->remove($this->object);
                //                $this->cacheService - removeObject($this->id); /* @todo this is hacky, the above schould alredy do this */
                $this->entityManager->flush();

                return new Response('Succesfully deleted object', '202');
                break;
            default:
                break;

                return new Response('Unkown method'.$this->data['method'], '404');
        }

        $this->entityManager->flush();

        if (isset($eventType) && isset($this->object)) {
            $event = new ActionEvent($eventType, ['response' => $this->object->toArray(), 'entity' => $this->object->getEntity()->getId()->toString()]);
            $this->eventDispatcher->dispatch($event, $event->getType());
        }

        $this->handleMetadataSelf($result, $metadataSelf);

        // TODO: Removed this so embedded keeps working for all accept types (for projects like KISS & OC)
        // TODO: find another way to get this working as expected for Roxit.
//        $result = $this->shouldWeUnsetEmbedded($result, $this->data['headers']['accept'] ?? null, $isCollection ?? false);

        return $this->createResponse($result);
    }

    /**
     * If embedded should be shown or not.
     *
     * @param object|array $result fetched result
     * @param ?array       $accept accept header
     *
     * @return array|null
     */
    public function shouldWeUnsetEmbedded($result = null, ?array $accept, ?bool $isCollection = false)
    {
        if (
            isset($result) &&
            (isset($accept) &&
                !in_array('application/json+ld', $accept) &&
                !in_array('application/ld+json', $accept))
            ||
            !isset($accept)
        ) {
            if (isset($isCollection) && isset($result['results'])) {
                foreach ($result['results'] as $key => $item) {
                    $result['results'][$key] = $this->checkEmbedded($item);
                }
            } else {
                $result = $this->checkEmbedded($result);
            }
        }

        return $result;
    }

    /**
     * If embedded should be shown or not.
     *
     * @param object|array $result fetched result
     *
     * @return array|null
     */
    public function checkEmbedded($result)
    {
        if (isset($result->embedded)) {
            unset($result->embedded);
        }

        return $result;
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

        // todo: $this->id is sometimes empty, it should never be an empty string
        if (isset($result['results']) && $this->data['method'] === 'GET' && empty($this->id)) {
            array_walk($result['results'], function (&$record) {
                $record = iterator_to_array($record);
            });
            foreach ($result['results'] as &$collectionItem) {
                $this->handleMetadataSelf($collectionItem, $metadataSelf);
            }

            return;
        }

        if (empty($result['id']) || !Uuid::isValid($result['id'])) {
            return;
        }
        $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $result['id']]);

        if (!$objectEntity instanceof ObjectEntity) {
            return;
        }
        if ($this->data['method'] === 'GET' && !empty($this->id)) {
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
