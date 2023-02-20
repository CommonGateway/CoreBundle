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
use Exception;
use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Handles incoming request from endpoints or controllers that relate to the gateways object structure (eav).
 *
 * @Author Wilco Louwerse <wilco@conduction.nl>, Robert Zondervan <robert@conduction.nl>, Ruben van der Linde <ruben@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class RequestService
{
    /**
     * @var EntityManagerInterface The entity manager.
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var CacheService The cache service.
     */
    private CacheService $cacheService;

    /**
     * @var array The configuration array. The configuration from an Action.
     */
    private array $configuration;

    /**
     * @var array The data array. The data from an api-call.
     */
    private array $data;

    /**
     * @var ObjectEntity An ObjectEntity used during the current api-call.
     */
    private ObjectEntity $object;

    /**
     * @var string An id of an ObjectEntity used during the current api-call.
     */
    private string $id;

    /**
     * @var mixed Todo: clean this mess up, why is this not a local variable? and why has it no type?
     */
    private $schema;

    /**
     * @var ResponseService The response service.
     */
    private ResponseService $responseService;

    /**
     * @var ObjectEntityService The ObjectEntity service (deprecated).
     */
    private ObjectEntityService $objectEntityService;

    /**
     * @var LoggerInterface The logger interface.
     */
    private LoggerInterface $logger;

    /**
     * @var CallService The call service.
     */
    private CallService $callService;

    /**
     * @var Security Symfony security.
     */
    private Security $security;

    /**
     * @var EventDispatcherInterface The event dispatcher.
     */
    private EventDispatcherInterface $eventDispatcher;

    /**
     * @var SerializerInterface The serializer.
     */
    private SerializerInterface $serializer;

    /**
     * @param EntityManagerInterface   $entityManager       The entity manager.
     * @param CacheService             $cacheService        The cache service.
     * @param ResponseService          $responseService     The response service.
     * @param ObjectEntityService      $objectEntityService The ObjectEntity service (deprecated).
     * @param LoggerInterface          $requestLogger       The logger interface.
     * @param CallService              $callService         The call service.
     * @param Security                 $security            Symfony security.
     * @param EventDispatcherInterface $eventDispatcher     The event dispatcher.
     * @param SerializerInterface      $serializer          The serializer.
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CacheService $cacheService,
        ResponseService $responseService,
        ObjectEntityService $objectEntityService,
        LoggerInterface $requestLogger,
        CallService $callService,
        Security $security,
        EventDispatcherInterface $eventDispatcher,
        SerializerInterface $serializer
    ) {
        $this->entityManager = $entityManager;
        $this->cacheService = $cacheService;
        $this->responseService = $responseService;
        $this->objectEntityService = $objectEntityService;
        $this->logger = $requestLogger;
        $this->callService = $callService;
        $this->security = $security;
        $this->eventDispatcher = $eventDispatcher;
        $this->serializer = $serializer;
    }//end __construct()

    /**
     * A function to replace Request->query->all() because Request->query->all() will replace some characters with an underscore.
     * This function will not.
     *
     * @param string $method The method of the Request.
     *
     * @return array An array with all query parameters.
     */
    public function realRequestQueryAll(string $method = 'get'): array
    {
        $vars = [];
        if (strtolower($method) === 'get' && empty($this->data['querystring']) === true) {
            return $vars;
        }

        // We allow the acces to super globals here (as a design decion) becouse this funtions is a wrapper for super globals.
        if (isset($_SERVER['QUERY_STRING']) === true) {
            $pairs = explode('&', $_SERVER['QUERY_STRING']);
            foreach ($pairs as $pair) {
                $nv = explode('=', $pair);
                $name = urldecode($nv[0]);
                $value = '';
                if (count($nv) === 2) {
                    $value = urldecode($nv[1]);
                }

                $this->recursiveRequestQueryKey($vars, $name, explode('[', $name)[0], $value);
            }

            return $vars;
        }

        return [];
    }//end realRequestQueryAll()

    /**
     * This function adds a single query param to the given $vars array. ?$name=$value
     * Will check if request query $name has [...] inside the parameter, like this: ?queryParam[$nameKey]=$value.
     * Works recursive, so in case we have ?queryParam[$nameKey][$anotherNameKey][etc][etc]=$value.
     * Also checks for queryParams ending on [] like: ?queryParam[$nameKey][] (or just ?queryParam[]), if this is the case
     * this function will add given value to an array of [queryParam][$nameKey][] = $value or [queryParam][] = $value.
     * If none of the above this function will just add [queryParam] = $value to $vars.
     *
     * @param array  $vars    The vars array we are going to store the query parameter in.
     * @param string $name    The full $name of the query param, like this: ?$name=$value.
     * @param string $nameKey The full $name of the query param, unless it contains [] like: ?queryParam[$nameKey]=$value.
     * @param string $value   The full $value of the query param, like this: ?$name=$value.
     *
     * @return void This function doesn't return anything.
     */
    private function recursiveRequestQueryKey(array &$vars, string $name, string $nameKey, string $value)
    {
        $matchesCount = preg_match('/(\[[^[\]]*])/', $name, $matches);
        if ($matchesCount > 0) {
            $key = $matches[0];
            $name = str_replace($key, '', $name);
            $key = trim($key, '[]');
            if (empty($key) === false) {
                $vars[$nameKey] = ($vars[$nameKey] ?? []);
                $this->recursiveRequestQueryKey($vars[$nameKey], $name, $key, $value);
            } else {
                $vars[$nameKey][] = $value;
            }
        } else {
            $vars[$nameKey] = $value;
        }
    }//end recursiveRequestQueryKey()

    /**
     * Get the ID from given parameters.
     *
     * @return string|false Return id if we can find it in the $this->data array, else returns false.
     */
    public function getId()
    {
        // Try to grap an id.
        if (isset($this->data['path']['{id}']) === true) {
            return $this->data['path']['{id}'];
        } elseif (isset($this->data['path']['[id]']) === true) {
            return $this->data['path']['[id]'];
        } elseif (isset($this->data['query']['id']) === true) {
            return $this->data['query']['id'];
        } elseif (isset($this->data['path']['id']) === true) {
            return$this->data['path']['id'];
        } elseif (isset($this->data['path']['{uuid}']) === true) {
            return $this->data['path']['{uuid}'];
        } elseif (isset($this->data['query']['uuid']) === true) {
            return$this->data['query']['uuid'];
        } elseif (isset($this->content['id']) === true) {
            // The id might also be passed trough the object itself.
            return $this->content['id'];
        } elseif (isset($this->content['uuid']) === true) {
            return $this->content['uuid'];
        }

        return false;
    }//end getId()

    /**
     * Get the schema from given parameters returns false if no schema could be established.
     *
     * @param array $parameters The parameters to get a schema from.
     *
     * @return Entity|false Return Entity if we can find it, else returns false.
     */
    public function getSchema(array $parameters)
    {

        // If we have an object this is easy.
        if (isset($this->object) === true) {
            return $this->object->getEntity();
        }

        // Pull the id or reference from the content.
        if (isset($this->content['_self']['schema']['id']) === true) {
            $id = $this->content['_self']['schema']['id'];
        }

        if (isset($this->content['_self']['schema']['ref']) === true) {
            $reference = $this->content['_self']['schema']['ref'];
        }

        if (isset($this->content['_self']['schema']['reference']) === true) {
            $reference = $this->content['_self']['schema']['reference'];
        }

        // In normal securmtances we expect a all to com form an endpoint so...
        if (isset($parameters['endpoint']) === true) {
            // The endpoint contains exactly one schema.
            if (count($this->data['endpoint']->getEntities()) === 1) {
                return $this->data['endpoint']->getEntities()->first();
            }

            // The endpoint contains multiple schema's.
            if (count($this->data['endpoint']->getEntities()) >= 1) {
                // Todo: so right now if we don't have an id or ref and multpile options we "guese" the first, it that smart?
                $criteria = Criteria::create()->orderBy(['date_created' => Criteria::DESC]);
                if (isset($id) === true) {
                    $criteria->where(['id' => $id]);
                }

                if (isset($reference) === true) {
                    $criteria->where(['reference' => $reference]);
                }

                return $this->data['endpoint']->getEntities()->matching($criteria)->first();
            }

            // The  endpoint contains no schema's so there is no limit we don't need to do anything.
        }//end if

        // We only end up here if there is no endpoint or an unlimited endpoint.
        if (isset($id) === true) {
            return $this->entityManager->getRepository('App:Entity')->findOneBy(['id' => $id]);
        }

        if (isset($reference) === true) {
            return $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $reference]);
        }

        // There is no way to establish an schema so.
        return false;
    }//end getSchema()

    /**
     * Handles a proxy.
     *
     * @param array $data          The data from the call.
     * @param array $configuration The configuration from the call.
     *
     * @return Response The data as returned bij the origanal source.
     */
    public function proxyHandler(array $data, array $configuration): Response
    {
        $this->data = $data;
        $this->configuration = $configuration;

        // We only do proxing if the endpoint forces it.
        if ($data['endpoint'] instanceof Endpoint === false || $proxy = $data['endpoint']->getProxy() === false) {
            $message = !$data['endpoint'] instanceof Endpoint ?
                "No Endpoint in data['endpoint']" :
                "This Endpoint has no Proxy: {$data['endpoint']->getName()}";

            return new Response(
                json_encode(['Message' => $message]),
                Response::HTTP_NOT_FOUND,
                ['content-type' => 'application/json']
            );
        }

        if ($proxy instanceof Source && $proxy->getIsEnabled() === false) {
            return new Response(
                json_encode(['Message' => "This Source is not enabled: {$proxy->getName()}"]),
                Response::HTTP_OK,
                ['content-type' => 'application/json']
            );
        }

        // Get clean query paramters without all the symfony shizzle.
        $query = $this->realRequestQueryAll($this->data['method']);
        $this->data['path'] = '/'.$data['path']['{route}'];

        // Make a guzzle call to the source bassed on the incomming call.
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

        // Let create a responce from the guzle call.
        $responce = new Response(
            $result->getBody()->getContents(),
            $result->getStatusCode(),
            $result->getHeaders()
        );

        // @todo the above might need a try catch
        // And don so let's return what we have.
        return $responce;
    }//end proxyHandler()

    /**
     * Get a scopes array for the current user (or of the anonymus if no user s logged in).
     *
     * @return array An array of scopes.
     */
    public function getScopes(): ?array
    {
        if ($user = $this->security->getUser() === true) {
            return $user->getScopes();
        } else {
            $anonymousSecurityGroup = $this->entityManager->getRepository('App:SecurityGroup')->findOneBy(['anonymous' => true]);
            if ($anonymousSecurityGroup === true) {
                return $anonymousSecurityGroup->getScopes();
            }
        }

        // Lets play it save.
        return [];
    }//end getScopes()

    /**
     * Handles incoming requests and is responsible for generating a response.
     *
     * @param array $data          The data from the call.
     * @param array $configuration The configuration from the call.
     *
     * @throws Exception A basic Exception.
     *
     * @return Response The modified data.
     */
    public function requestHandler(array $data, array $configuration): Response
    {
        $this->data = $data;
        $this->configuration = $configuration;

        $filters = [];

        // Haat aan de de _.
        if (isset($this->data['querystring']) === true) {
            $filters = $this->realRequestQueryAll($this->data['method']);
        }

        // Get the ID.
        $this->id = $this->getId($this->data);

        // If we have an ID we can get an entity to work with (except on gets we handle those from cache).
        if (isset($this->id) === true && empty($this->id) === false && $this->data['method'] !== 'GET') {
            $this->object = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $this->id]);
        }

        // Lets pas the part variables to filters.
        // Todo: this is hacky.
        foreach ($this->data['path'] as $key => $value) {
            if (strpos($key, '{') !== false) {
                if ($key !== '{id}') {
                    $filters['_search'] = $value;
                }
            }
        }

        // We might have some content.
        if (isset($this->data['body']) === true) {
            $this->content = $this->data['body'];
        }

        // Get the schema.
        $this->schema = $this->getSchema($this->data);

        // Bit os safety cleanup <- dit zou eigenlijk in de hydrator moeten gebeuren.
        // Todo: i don't think this does anything useful? Please add a comment why we do this here at all?
        unset($this->content['id']);
        unset($this->content['_id']);
        unset($this->content['_self']);
        unset($this->content['_schema']);

        // Todo: make this a function, like eavService->getRequestExtend().
        if (isset($this->data['query']['extend']) === true) {
            $extend = $this->data['query']['extend'];

            // Let's deal with a comma seperated list.
            if (is_array($extend) === false) {
                $extend = explode(',', $extend);
            }

            $dot = new Dot();
            // Let's turn the dot array into a proper array.
            foreach ($extend as $key => $value) {
                $dot->add($value, true);
            }

            $extend = $dot->all();
        }

        // Todo: controlleren of de gebruiker ingelogd is.
        $metadataSelf = ($extend['_self'] ?? []);

        // Make a list of schema's that are allowed for this endpoint.
        $allowedSchemas['id'] = [];
        $allowedSchemas['name'] = [];
        if (isset($this->data['endpoint']) === true) {
            foreach ($this->data['endpoint']->getEntities() as $entity) {
                $allowedSchemas['id'][] = $entity->getId()->toString();
                $allowedSchemas['name'][] = $entity->getName();
            }
        }

        // Security.
        $scopes = $this->getScopes();
        foreach ($allowedSchemas['id'] as $schema) {
            if (isset($scopes[$schema][$this->data['method']]) === false) {
                // THROW SECURITY ERROR AND EXIT.
            }
        }

        // All prepped so let's go.
        // Todo: split these into functions?
        switch ($this->data['method']) {
            case 'GET':
                // We have an id (so single object).
                if (isset($this->id) === true && empty($this->id) === false) {
                    $result = $this->cacheService->getItemFromCache($this->id);

                    // If we do not have an object we throw an 404.
                    if ($result === false) {
                        return new Response($this->serializer->serialize(
                            [
                                'message' => 'Could not find an object with id '.$this->id,
                                'type'    => 'Bad Request',
                                'path'    => implode(', ', $allowedSchemas['name']),
                                'data'    => ['id' => $this->id],
                            ],
                            'json'
                        ), Response::HTTP_NOT_FOUND);
                    }

                    // Let's see if the found result is allowed for this endpoint.
                    if (isset($this->data['endpoint']) === true && in_array($result['_self']['schema']['id'], $allowedSchemas['id']) === false) {
                        return new Response('Object is not supported by this endpoint', '406');
                    }

                    $session = new Session();
                    $session->set('object', $this->id);

                // Todo: This log is needed so we know an user has 'read' this object
                    // $this->logService->saveLog($this->logService->makeRequest(), $responseLog, 15, is_array($this->content) ? json_encode($this->content) : $this->content);
                } else {
                    // $this->data['query']['_schema'] = $this->data['endpoint']->getEntities()->first()->getReference();
                    $result = $this->cacheService->getCollectionFromCache(null, $filters, $allowedSchemas['id']);
                }//end if
                break;
            case 'POST':
                $eventType = 'commongateway.object.create';

                // We have an id on a post so die.
                if (isset($this->id) === true && empty($this->id) === false) {
                    return new Response('You can not POST to an (exsisting) id, consider using PUT or PATCH instead', '400');
                }

                // We need to know the type of object that the user is trying to post, so let's look that up.
                if (isset($this->schema) === false || empty($this->schema) === true) {
                    return new Response('No schema could be established for your request', '400');
                }

                // Lets see if the found result is allowd for this endpoint.
                if (isset($this->data['endpoint']) === true && in_array($this->schema->getId(), $allowedSchemas['id']) === false) {
                    return new Response('Object is not supported by this endpoint', '406');
                }

                $this->object = new ObjectEntity($this->schema);
                $this->object->setOwner($this->security->getUser()->getUserIdentifier());

                // If ($validation = $this->object->validate($this->content) && $this->object->hydrate($content, true)) {
                if ($this->object->hydrate($this->content, true) === true) {
                    $this->entityManager->persist($this->object);
                } else {
                    // Use validation to throw an error.
                }

                $result = $this->cacheService->getItemFromCache($this->object->getId());
                break;
            case 'PUT':
                $eventType = 'commongateway.object.update';

                // We don't have an id on a PUT so die.
                if (isset($this->id) === false) {
                    return new Response('No id could be established for your request', '400');
                }

                // We need to know the type of object that the user is trying to post, so let's look that up.
                if (isset($this->schema) === false || empty($this->schema) === true) {
                    return new Response('No schema could be established for your request', '400');
                }

                // Let's see if the found result is allowed for this endpoint.
                if (isset($this->data['endpoint']) === true && in_array($this->schema->getId(), $allowedSchemas['id']) === false) {
                    return new Response('Object is not supported by this endpoint', '406');
                }

                // If ($validation = $this->object->validate($this->content) && $this->object->hydrate($content, true)) {
                if ($this->object->hydrate($this->content, true) === true) {
                    // This should be an unsafe hydration.
                    if (array_key_exists('@dateRead', $this->content) && $this->content['@dateRead'] == false) {
                        $this->objectEntityService->setUnread($this->object);
                    }
                    $this->entityManager->persist($this->object);
                    $this->entityManager->flush();
                } else {
                    // Use validation to throw an error.
                }

                $result = $this->cacheService->getItemFromCache($this->object->getId());
                break;
            case 'PATCH':
                $eventType = 'commongateway.object.update';

                // We don't have an id on a PUT so die.
                if (isset($this->id) === false) {
                    return new Response('No id could be established for your request', '400');
                }

                // We need to know the type of object that the user is trying to post, so let's look that up.
                if (isset($this->schema) === false || empty($this->schema) === true) {
                    return new Response('No schema could be established for your request', '400');
                }

                // Lets see if the found result is allowd for this endpoint.
                if (isset($this->data['endpoint']) === true && in_array($this->schema->getId(), $allowedSchemas['id']) === false) {
                    return new Response('Object is not supported by this endpoint', '406');
                }

                // If ($this->object->hydrate($this->content) && $validation = $this->object->validate()) {
                if ($this->object->hydrate($this->content) === true) {
                    if (array_key_exists('@dateRead', $this->content) && $this->content['@dateRead'] == false) {
                        $this->objectEntityService->setUnread($this->object);
                    }
                    $this->entityManager->persist($this->object);
                    $this->entityManager->flush();
                } else {
                    // Use validation to throw an error.
                }

                $result = $this->cacheService->getItemFromCache($this->object->getId());
                break;
            case 'DELETE':

                // We don't have an id on a PUT so die.
                if (isset($this->id) === false) {
                    return new Response('No id could be established for your request', '400');
                }

                // We need to know the type of object that the user is trying to post, so let's look that up.
                if (isset($this->schema) === false || empty($this->schema) === true) {
                    return new Response('No schema could be established for your request', '400');
                }

                // Lets see if the found result is allowd for this endpoint.
                if (isset($this->data['endpoint']) === true && in_array($this->schema->getId(), $allowedSchemas['id']) === false) {
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

        if (isset($eventType) === true && isset($result) === true) {
            $event = new ActionEvent($eventType, ['response' => $result, 'entity' => ($this->object->getEntity()->getReference() ?? $this->object->getEntity()->getId()->toString())]);
            $this->eventDispatcher->dispatch($event, $event->getType());

            // If we have a response return that.
            if ($event->getData()['response'] === true) {
                return new Response(json_encode($event->getData()['response']));
            }
        }

        $this->handleMetadataSelf($result, $metadataSelf);

        // TODO: Removed this so embedded keeps working for all accept types (for projects like KISS & OC)
        // TODO: find another way to get this working as expected for Roxit.
//        $result = $this->shouldWeUnsetEmbedded($result, $this->data['headers']['accept'] ?? null, $isCollection ?? false);

        return $this->createResponse($result);
    }//end requestHandler()

    /**
     * If embedded should be shown or not.
     *
     * @param object|array $result       fetched result.
     * @param ?array       $accept       accept header.
     * @param ?bool        $isCollection Is the request a collection
     *
     * @return array|null The result array.
     */
    public function shouldWeUnsetEmbedded($result = null, ?array $accept, ?bool $isCollection = false)
    {
        if (isset($result) === true &&
            (isset($accept) === true &&
                in_array('application/json+ld', $accept) === false &&
                in_array('application/ld+json', $accept) === false)
            ||
            isset($accept) === false
        ) {
            if (isset($isCollection) === true && isset($result['results']) === true) {
                foreach ($result['results'] as $key => $item) {
                    $result['results'][$key] = $this->checkEmbedded($item);
                }
            } else {
                $result = $this->checkEmbedded($result);
            }
        }

        return $result;
    }//end shouldWeUnsetEmbedded()

    /**
     * If embedded should be shown or not.
     *
     * @param object|array $result fetched result.
     *
     * @return array|null The result array.
     */
    public function checkEmbedded($result)
    {
        if (isset($result->embedded) === true) {
            unset($result->embedded);
        }

        return $result;
    }//end checkEmbedded()

    /**
     * This function adds metadata to the result of an api-call, after we already got the data from the cache.
     * This function is specifically for adding metadata to the _self array of the result of one specific Object,
     * or for an array of objects if $result has the key 'result' and $this->data has 'method' === 'GET' (a get collection api call).
     *
     * @param array $result       The result array.
     * @param array $metadataSelf If query param _self.anything is used everything after _self is added to an array, this array.
     *
     * @return void This function doesn't return anything.
     */
    private function handleMetadataSelf(&$result, array $metadataSelf)
    {
        // Todo: Adding type array before &$result will break this function ^^^.
        if (empty($metadataSelf) === true) {
            return;
        }

        // Todo: $this->id is sometimes empty, it should never be an empty string, for now just check if it is empty or not.
        // Handle a get collection situation.
        if (isset($result['results']) === true && $this->data['method'] === 'GET' && empty($this->id) === true) {
            array_walk(
                $result['results'],
                function (&$record) {
                    $record = iterator_to_array($record);
                }
            );
            // Do handleMetadataSelf() for each object, recursively.
            foreach ($result['results'] as &$collectionItem) {
                $this->handleMetadataSelf($collectionItem, $metadataSelf);
            }

            return;
        }

        if (empty($result['id']) === true || Uuid::isValid($result['id']) === false) {
            return;
        }

        $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $result['id']]);

        if ($objectEntity instanceof ObjectEntity === false) {
            return;
        }

        if ($this->data['method'] === 'GET' && empty($this->id) === false) {
            $metadataSelf['dateRead'] = 'getItem';
        }
        $this->responseService->xCommongatewayMetadata = $metadataSelf;
        $resultMetadataSelf = (array) $result['_self'];
        $this->responseService->addToMetadata($resultMetadataSelf, 'dateRead', $objectEntity);
        $result['_self'] = $resultMetadataSelf;
    }//end handleMetadataSelf()

    /**
     * Handle an item request call: GET, PUT, PATCH or DELETE an item (read: 'object)'.
     *
     * @param array $data          The data from the call.
     * @param array $configuration The configuration from the call.
     *
     * @return array The modified data.
     */
    public function itemRequestHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        $method = $this->data['request']->getMethod();
        $content = $this->data['request']->getContent();

        // Lets see if we have an object.
        if (array_key_exists('id', $this->data) === true) {
            $this->id = $data['id'];
            if ($this->object = $this->cacheService->getItemFromCache($data['id']) === false) {
                // Throw not found.
            }
        }

        switch ($method) {
            case 'GET':
                break;
            case 'PUT':
                $validation = $this->object->validate($content);
                if ($validation === true) {
                    $this->object->hydrate($content, true);
                    $this->entityManager->persist($this->object);
                } else {
                    // Use validation to throw an error.
                }
                break;
            case 'PATCH':
                $this->object->hydrate($content);
                $validation = $this->object->validate();
                if ($validation === true) {
                    $this->entityManager->persist($this->object);
                } else {
                    // Use validation to throw an error.
                }
                break;
            case 'DELETE':
                $this->entityManager->remove($this->object);

                return new Response('', '202');
            default:
                break;
        }

        $this->entityManager->flush();

        return $this->createResponse($this->object);
    }//end itemRequestHandler()

    /**
     * This function searches all the objectEntities and formats the data.
     *
     * @param array $data          The data from the call.
     * @param array $configuration The configuration from the call.
     *
     * @return array The modified data.
     */
    public function searchRequestHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        $objectEntities = $this->entityManager->getRepository('App:ObjectEntity')->findAll();

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
    }//end searchRequestHandler()

    /**
     * Creating the response object.
     *
     * @param mixed $data The data
     *
     * @return Response A Response.
     */
    public function createResponse($data): Response
    {
        if ($data instanceof ObjectEntity) {
            $data = $data->toArray();
        } else {
        }

        return new Response(
            json_encode($data),
            200,
            ['content-type' => 'application/json']
        );
    }//end createResponse()
}//end class
