<?php

namespace CommonGateway\CoreBundle\Service;

use Adbar\Dot;
use App\Entity\Application;
use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\Gateway as Source;
use App\Entity\ObjectEntity;
use App\Event\ActionEvent;
use App\Exception\GatewayException;
use App\Service\LogService;
use App\Service\ObjectEntityService;
use App\Service\ResponseService;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Handles incoming request from endpoints or controllers that relate to the gateways object structure (eav).
 *
 * @Author Ruben van der Linde <ruben@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>, Robert Zondervan <robert@conduction.nl>, Barry Brands <barry@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
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
     * @var GatewayResourceService
     */
    private GatewayResourceService $resourceService;

    /**
     * @var MappingService
     */
    private MappingService $mappingService;

    /**
     * @var ValidationService
     */
    private ValidationService $validationService;

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
    private string $identification;

    /**
     * @var $schema
     */
    private $schema;
    // @Todo: cast to Entity|Boolean in php 8.
    // @Todo: we might want to move or rewrite code instead of using these services here:

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
     * @var SessionInterface
     */
    private SessionInterface $session;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var DownloadService
     */
    private DownloadService $downloadService;

    /**
     * The constructor sets al needed variables.
     *
     * @param EntityManagerInterface   $entityManager
     * @param GatewayResourceService   $resourceService
     * @param MappingService           $mappingService
     * @param ValidationService        $validationService
     * @param CacheService             $cacheService
     * @param ResponseService          $responseService
     * @param ObjectEntityService      $objectEntityService
     * @param LogService               $logService
     * @param CallService              $callService
     * @param Security                 $security
     * @param EventDispatcherInterface $eventDispatcher
     * @param SerializerInterface      $serializer
     * @param SessionInterface         $session
     * @param LoggerInterface          $requestLogger
     * @param DownloadService          $downloadService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        GatewayResourceService $resourceService,
        MappingService $mappingService,
        ValidationService $validationService,
        CacheService $cacheService,
        ResponseService $responseService,
        ObjectEntityService $objectEntityService,
        LogService $logService,
        CallService $callService,
        Security $security,
        EventDispatcherInterface $eventDispatcher,
        SerializerInterface $serializer,
        SessionInterface $session,
        LoggerInterface $requestLogger,
        DownloadService $downloadService
    ) {
        $this->entityManager       = $entityManager;
        $this->cacheService        = $cacheService;
        $this->resourceService     = $resourceService;
        $this->mappingService      = $mappingService;
        $this->validationService   = $validationService;
        $this->responseService     = $responseService;
        $this->objectEntityService = $objectEntityService;
        $this->logService          = $logService;
        $this->callService         = $callService;
        $this->security            = $security;
        $this->eventDispatcher     = $eventDispatcher;
        $this->serializer          = $serializer;
        $this->session             = $session;
        $this->logger              = $requestLogger;
        $this->downloadService     = $downloadService;

    }//end __construct()

    /**
     * Determines the right content type and serializes the data accordingly.
     *
     * @param array $data        The data to serialize.
     * @param mixed $contentType The content type to determine.
     *
     * @return string The serialized data.
     */
    public function serializeData(array $data, &$contentType): string
    {
        $accept = $this->data['accept'];

        if (isset($this->data['endpoint']) === true) {
            $endpoint = $this->data['endpoint'];
        }

        $xmlEncoder = new XmlEncoder([]);

        // @TODO: Create hal and ld encoding.
        switch ($accept) {
        case 'pdf':
            $content = $this->downloadService->downloadPdf($data);
            break;
        case 'xml':
            $content = $xmlEncoder->encode($data, 'xml');
            break;
        case 'jsonld':
        case 'jsonhal':
        case 'json':
        default:
            $content = \Safe\json_encode($data);
        }

        // @TODO: Preparation for checking if accept header is allowed. We probably should be doing this in the EndpointService instead?
        // if ($endpoint instanceof Endpoint
        // && empty($endpoint->getContentTypes()) === false
        // && in_array($accept, $endpoint->getContentTypes()) === false
        // ) {
        // throw new NotAcceptableHttpException('The content type is not accepted for this endpoint');
        // }
        if (isset($this->data['headers']['accept']) === true && $this->data['headers']['accept'][0] !== '*/*') {
            $contentType = $this->data['headers']['accept'][0];
        } else if ($endpoint instanceof Endpoint && $endpoint->getDefaultContentType() !== null) {
            $contentType = $endpoint->getDefaultContentType();
        } else if (isset($this->data['headers']['accept']) === true && $this->data['headers']['accept'][0] === '*/*') {
            $contentType = 'application/json';
        }

        return $content;

    }//end serializeData()

    /**
     * A function to replace Request->query->all() because Request->query->all() will replace some characters with an underscore.
     * This function will not.
     *
     * @param string|null $queryString A queryString from a request if we want to give it to this function instead of using global var $_SERVER.
     *
     * @return array An array with all query parameters.
     */
    public function realRequestQueryAll(?string $queryString = ''): array
    {
        $vars = [];

        if (empty($queryString) === true && empty($this->data['querystring']) === false) {
            $queryString = $this->data['querystring'];
        }

        if (empty($queryString) === true && empty($_SERVER['QUERY_STRING']) === true) {
            return $vars;
        }

        if (empty($queryString) === true && isset($_SERVER['QUERY_STRING']) === true) {
            $queryString = $_SERVER['QUERY_STRING'];
        }

        $pairs = explode('&', $queryString);
        foreach ($pairs as $pair) {
            $nv    = explode('=', $pair);
            $name  = urldecode($nv[0]);
            $value = '';
            if (count($nv) == 2) {
                $value = urldecode($nv[1]);
            }

            $this->recursiveRequestQueryKey($vars, $name, explode('[', $name)[0], $value);
        }//end foreach

        return $vars;

    }//end realRequestQueryAll()

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
            $key  = $matches[0];
            $name = str_replace($key, '', $name);
            $key  = trim($key, '[]');
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
     * Gets the schemas related to this endpoint.
     *
     * @return array All necessary info from the schemas related to this endpoint.
     */
    private function getAllowedSchemas(): array
    {
        $allowedSchemas = [
            'id'        => [],
            'name'      => [],
            'reference' => [],
        ];

        if (isset($this->data['endpoint']) === true) {
            foreach ($this->data['endpoint']->getEntities() as $entity) {
                $allowedSchemas['id'][]        = $entity->getId()->toString();
                $allowedSchemas['name'][]      = $entity->getName();
                $allowedSchemas['reference'][] = $entity->getReference();
            }
        }

        return $allowedSchemas;

    }//end getAllowedSchemas()

    /**
     * This function checks if the requesting user has the needed scopes to access the requested endpoint.
     *
     * @param array $references Schema references which we checks scopes for.
     *
     * @return null|Response A 403 response if the requested user does not have the needed scopes.
     */
    private function checkUserScopes(array $references, string $type = 'schemas'): ?Response
    {
        $userHasScope  = false;
        $scopes        = $this->getScopes();
        $loopedSchemas = [];
        foreach ($references as $reference) {
            $schemaScope     = "$type.$reference.{$this->data['method']}";
            $loopedSchemas[] = $schemaScope;
            if (isset($scopes[$schemaScope]) === true) {
                // If true the user is authorized.
                return null;
            }
        }

        // If the user doesn't have the normal scope and doesn't have the admin scope, return a 403 forbidden.
        if ($userHasScope === false && isset($scopes["admin.{$this->data['method']}"]) === false) {
            $implodeString = implode(', ', $loopedSchemas);
            $this->logger->error("Authentication failed. You do not have any of the required scopes for this endpoint. ($implodeString)");
            return new Response(
                $this->serializer->serialize(
                    [
                        'message' => "Authentication failed. You do not have any of the required scopes for this endpoint.",
                        'scopes'  => ['anyOf' => $loopedSchemas],
                    ],
                    'json'
                ),
                Response::HTTP_FORBIDDEN,
                ['Content-type' => $this->data['endpoint']->getDefaultContentType()]
            );
        }//end if

        return null;

    }//end checkUserScopes()

    /**
     * Get the ID from given parameters.
     *
     * @return string|false
     */
    public function getId()
    {
        // Try to grab an id.
        if (isset($this->data['path']['{id}']) === true) {
            return $this->data['path']['{id}'];
        }

        if (isset($this->data['path']['[id]']) === true) {
            return $this->data['path']['[id]'];
        }

        if (isset($this->data['query']['id']) === true) {
            return $this->data['query']['id'];
        }

        if (isset($this->data['path']['id']) === true) {
            return$this->data['path']['id'];
        }

        if (isset($this->data['path']['{uuid}']) === true) {
            return $this->data['path']['{uuid}'];
        }

        if (isset($this->data['query']['uuid']) === true) {
            return$this->data['query']['uuid'];
        }

        if (isset($this->content['id']) === true) {
            // the id might also be passed through the object itself.
            return $this->content['id'];
        }

        if (isset($this->content['uuid']) === true) {
            return $this->content['uuid'];
        }

        return false;

    }//end getId()

    /**
     * Get the schema from given parameters returns false if no schema could be established.
     *
     * @param array $parameters
     *
     * @return Entity|false
     */
    public function getSchema(array $parameters)
    {
        // If we have an object this is easy.
        if (isset($this->object) === true) {
            return $this->object->getEntity();
        }

        // Pull the id or reference from the content.
        if (isset($this->content['_self']['schema']['id']) === true) {
            $identification = $this->content['_self']['schema']['id'];
        }

        if (isset($this->content['_self']['schema']['ref']) === true) {
            $reference = $this->content['_self']['schema']['ref'];
        }

        if (isset($this->content['_self']['schema']['reference']) === true) {
            $reference = $this->content['_self']['schema']['reference'];
        }

        // In normal circumstances we expect a all to com form an endpoint so...
        if (isset($parameters['endpoint']) === true) {
            // The endpoint contains exactly one schema
            if (count($this->data['endpoint']->getEntities()) == 1) {
                return $this->data['endpoint']->getEntities()->first();
            }

            // The endpoint contains multiple schema's
            if (count($this->data['endpoint']->getEntities()) >= 1) {
                // todo: so right now if we dont have an id or ref and multiple options we "guess" the first, it that smart?
                $criteria = Criteria::create()->orderBy(['date_created' => Criteria::DESC]);
                if (isset($identification) === true) {
                    $criteria->where(['id' => $identification]);
                }

                if (isset($reference) === true) {
                    $criteria->where(['reference' => $reference]);
                }

                return $this->data['endpoint']->getEntities()->matching($criteria)->first();
            }
        }//end if

        // We only end up here if there is no endpoint or an unlimited endpoint.
        if (isset($identification) === true) {
            return $this->entityManager->getRepository('App:Entity')->findOneBy(['id' => $identification]);
        }

        if (isset($reference) === true) {
            return $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $reference]);
        }

        // There is no way to establish an schema so.
        return false;

    }//end getSchema()

    /**
     * @param array $data          The data from the call
     * @param array $configuration The configuration from the call
     *
     * @return Response The data as returned bij the origanal source
     */
    public function proxyHandler(array $data, array $configuration, ?Source $proxy = null): Response
    {
        $this->data          = $data;
        $this->configuration = $configuration;

        // If we already have a proxy, we can skip these checks.
        if ($proxy instanceof Source === false) {
            // We only do proxying if the endpoint forces it, and we do not have a proxy.
            if ($data['endpoint'] instanceof Endpoint === false || ($proxy = $data['endpoint']->getProxy()) === null) {
                $message = !$data['endpoint'] instanceof Endpoint ? "No Endpoint in data['endpoint']" : "This Endpoint has no Proxy: {$data['endpoint']->getName()}";

                return new Response(
                    $this->serializeData(['Message' => $message], $contentType),
                    Response::HTTP_NOT_FOUND,
                    ['Content-type' => $contentType]
                );
            }//end if

            if ($proxy instanceof Source && ($proxy->getIsEnabled() === null || $proxy->getIsEnabled() === false)) {
                return new Response(
                    $this->serializeData(['message' => "This Source is not enabled: {$proxy->getName()}"], $contentType),
                    Response::HTTP_OK,
                    // This should be ok, so we can disable Sources without creating error responses?
                    ['Content-type' => $contentType]
                );
            }
        }//end if

        $securityResponse = $this->checkUserScopes([$proxy->getReference()], 'sources');
        if ($securityResponse instanceof Response === true) {
            return $securityResponse;
        }

        // Work around the _ with a custom function for getting clean query parameters from a request
        $this->data['query'] = $this->realRequestQueryAll();
        if (isset($data['path']['{route}']) === true) {
            $this->data['path'] = '/'.$data['path']['{route}'];
        } else {
            $this->data['path'] = '';
        }

        unset($this->data['headers']['authorization']);
        // Make a guzzle call to the source based on the incoming call.
        try {
            $result = $this->callService->call(
                $proxy,
                $this->data['path'],
                $this->data['method'],
                [
                    'query'   => $this->data['query'],
                    'headers' => $this->data['headers'],
                    'body'    => $this->data['crude_body'],
                ]
            );

            // Let create a response from the guzzle call.
            $response = new Response(
                $result->getBody()->getContents(),
                $result->getStatusCode(),
                $result->getHeaders()
            );
        } catch (Exception $exception) {
            $statusCode = ($exception->getCode() ?? 500);
            if (method_exists(get_class($exception), 'getResponse') === true && $exception->getResponse() !== null) {
                $body       = $exception->getResponse()->getBody()->getContents();
                $statusCode = $exception->getResponse()->getStatusCode();
                $headers    = $exception->getResponse()->getHeaders();
            }

            $content  = $this->serializeData(
                [
                    'Message' => $exception->getMessage(),
                    'Body'    => ($body ?? "Can\'t get a response & body for this type of Exception: ").get_class($exception),
                ],
                $contentType
            );
            $response = new Response($content, $statusCode, ($headers ?? ['Content-Type' => $contentType]));
        }//end try

        // And don so let's return what we have.
        return $response;

    }//end proxyHandler()

    /**
     * Get a scopes array for the current user (or of the anonymus if no user s logged in).
     *
     * @return array
     */
    public function getScopes(): ?array
    {
        // If we have a user, return the user his scopes.
        $user = $this->security->getUser();
        if (isset($user) === true && $user->getRoles() !== null) {
            $scopes = [];
            foreach ($user->getRoles() as $role) {
                $scopes[str_replace('ROLE_', '', $role)] = true;
            }

            return $scopes;
        }//end if

        // If we don't have a user, return the anonymous security group its scopes.
        $anonymousSecurityGroup = $this->entityManager->getRepository('App:SecurityGroup')->findOneBy(['anonymous' => true]);
        if ($anonymousSecurityGroup !== null) {
            return $anonymousSecurityGroup->getScopes();
        }

        // If we don't have a user or anonymous security group, return an empty array (this will result in a 403 response in the checkUserScopes function).
        return [];

    }//end getScopes()

    /**
     * Handles incomming requests and is responsible for generating a response.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration from the call
     *
     * @throws Exception
     *
     * @return Response The modified data
     */
    public function requestHandler(array $data, array $configuration): Response
    {
        $this->data          = $data;
        $this->configuration = $configuration;

        $filters = [];

        // Get application configuration in and out for current endpoint/global if this is set on current application.
        if ($this->session->get('application') !== null) {
            $appEndpointConfig = $this->getAppEndpointConfig();
        }

        // Work around the _ with a custom function for getting clean query parameters from a request
        $filters = $this->realRequestQueryAll();

        // Handle mapping for query parameters
        if (isset($appEndpointConfig['in']['query']) === true) {
            $filters = $this->queryAppEndpointConfig($filters, $appEndpointConfig['in']['query']);
        }

        // Get the ID.
        $this->identification = $this->getId();

        // If we have an ID we can get an Object to work with (except on gets we handle those from cache).
        if (isset($this->identification) === true && empty($this->identification) === false && $this->data['method'] != 'GET') {
            $this->object = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $this->identification]);
        }

        // Let's pas the part variables to filters.
        // todo: this is hacky.
        foreach ($this->data['path'] as $key => $value) {
            if (strpos($key, '{') !== false) {
                if ($key !== '{id}') {
                    // @todo unused code below, remove?
                    // $keyExplodedFilter  = explode('{', $key);
                    // $keyFilter          = explode('}', $keyExplodedFilter[1]);
                    $filters['_search'] = $value;
                }
            }
        }//end foreach

        // We might have some content.
        if (isset($this->data['body']) === true) {
            $this->content = $this->data['body'];
        }

        // Get the schema.
        $this->schema = $this->getSchema($this->data);

        if ($this->schema !== false) {
            $this->session->set('schema', $this->schema->getId()->toString());
        }

        // Bit os safety cleanup <- dit zou eigenlijk in de hydrator moeten gebeuren.
        // unset($this->content['id']);
        unset($this->content['_id']);
        // todo: i don't think this does anything useful?
        unset($this->content['_self']);
        unset($this->content['_schema']);

        // todo: make this a function, like eavService->getRequestExtend()
        if (isset($this->data['query']['extend']) === true) {
            $extend = $this->data['query']['extend'];

            // Let's deal with a comma seperated list.
            if (is_array($extend) === false) {
                $extend = explode(',', $extend);
            }

            $dot = new Dot();
            // Let's turn the from dor attat into an propper array.
            foreach ($extend as $key => $value) {
                $dot->add($value, true);
            }

            $extend = $dot->all();
        }//end if

        $metadataSelf = ($extend['_self'] ?? []);

        // Make a list of schema's that are allowed for this endpoint.
        $allowedSchemas = $this->getAllowedSchemas();

        // Check if the user has the needed scopes.
        $securityResponse = $this->checkUserScopes($allowedSchemas['reference']);
        if ($securityResponse instanceof Response === true) {
            return $securityResponse;
        }

        // All prepped so let's go.
        // todo: split these into functions?
        switch ($this->data['method']) {
        case 'GET':
            // We have an id (so single object).
            if (isset($this->identification) === true && empty($this->identification) === false) {
                $this->session->set('object', $this->identification);
                $result = $this->cacheService->getObject($this->identification);

                // check endpoint throws foreach and set the eventtype.
                // use event dispatcher.
                // If we do not have an object we throw an 404.
                if ($result === null) {
                    return new Response(
                        $this->serializer->serialize(
                            [
                                'message' => 'Could not find an object with id '.$this->identification,
                                'type'    => 'Bad Request',
                                'path'    => implode(', ', $allowedSchemas['name']),
                                'data'    => ['id' => $this->identification],
                            ],
                            'json'
                        ),
                        Response::HTTP_NOT_FOUND
                    );
                }

                // Let's see if the found result is allowed for this endpoint.
                if (isset($this->data['endpoint']) && in_array($result['_self']['schema']['id'], $allowedSchemas['id']) === false) {
                    return new Response('Object is not supported by this endpoint', '406', ['Content-type' => $this->data['endpoint']->getDefaultContentType()]);
                }

                // create log.
                // todo if $this->content is array and not string/null, cause someone could do a get item call with a body...
                $responseLog = new Response(is_string($this->content) === true || is_null($this->content) === true ? $this->content : null, 200, ['CoreBundle' => 'GetItem']);
                $session     = new Session();
                $session->set('object', $this->identification);

                // todo: This log is needed so we know an user has 'read' this object.
                $this->logService->saveLog($this->logService->makeRequest(), $responseLog, 15, is_array($this->content) === true ? json_encode($this->content) : $this->content);
            } else {
                // $this->data['query']['_schema'] = $this->data['endpoint']->getEntities()->first()->getReference();
                $result = $this->cacheService->searchObjects(null, $filters, $allowedSchemas['id']);
            }//end if
            break;
        case 'POST':
            $eventType = 'commongateway.object.create';

            // We have an id on a post so die
            if (isset($this->identification) === true && empty($this->identification) === false) {
                $this->session->set('object', $this->identification);
                $this->logger->error('You can not POST to an (existing) id, consider using PUT or PATCH instead');

                return new Response('You can not POST to an (existing) id, consider using PUT or PATCH instead', '400', ['Content-type' => $this->data['endpoint']->getDefaultContentType()]);
            }

            // We need to know the type of object that the user is trying to post, so let's look that up.
            if ($this->schema instanceof Entity === false) {
                $this->logger->error('No schema could be established for your request');

                return new Response('No schema could be established for your request', '400', ['Content-type' => $this->data['endpoint']->getDefaultContentType()]);
            }

            // Let's see if the found result is allowed for this endpoint.
            if (isset($this->data['endpoint']) === true && in_array($this->schema->getId(), $allowedSchemas['id']) === false) {
                $this->logger->error('Object is not supported by this endpoint');

                return new Response('Object is not supported by this endpoint', '406', ['Content-type' => $this->data['endpoint']->getDefaultContentType()]);
            }

            // Let's see if we have a body.
            if (isset($this->content) === false || empty($this->content) === true) {
                $this->logger->error('The body of your request is empty');

                return new Response('The body of your request is empty', '400', ['Content-type' => $this->data['endpoint']->getDefaultContentType()]);
            }

            $this->object = new ObjectEntity($this->schema);

            if ($this->security->getUser() !== null) {
                $this->object->setOwner($this->security->getUser()->getUserIdentifier());
            }

            $this->logger->debug('Hydrating object');
            // if ($validation = $this->object->validate($this->content) && $this->object->hydrate($content, true)) {
            $validationErrors = $this->validationService->validateData($this->content, $this->schema, 'POST');
            if ($validationErrors === null && $this->object->hydrate($this->content, true)) {
                if ($this->schema->getPersist() === true) {
                    $this->entityManager->persist($this->object);
                    $this->entityManager->flush();
                    $this->session->set('object', $this->object->getId()->toString());
                    // @todo this is hacky, the above should already do this
                    $this->cacheService->cacheObject($this->object);
                    $this->entityManager->flush();
                } else {
                    $this->entityManager->persist($this->object);
                    $this->session->set('object', $this->object->getId()->toString());
                    // @todo this is hacky, the above should already do this
                    $this->cacheService->cacheObject($this->object);
                }
            } else if ($validationErrors !== null) {
                $result = [
                    "Message" => 'Validation errors',
                    'data'    => $validationErrors,
                    'path'    => $this->data['pathRaw'],
                ];
                break;
            }//end if

            $result = $this->cacheService->getObject($this->object->getId()->toString());
            break;
        case 'PUT':
            $eventType = 'commongateway.object.update';

            // We dont have an id on a PUT so die.
            if (isset($this->identification) === false) {
                $this->logger->error('No id could be established for your request');

                return new Response('No id could be established for your request', '400', ['Content-type' => $this->data['endpoint']->getDefaultContentType()]);
            }

            $this->session->set('object', $this->identification);

            // We need to know the type of object that the user is trying to post, so let's look that up.
            if ($this->schema instanceof Entity === false) {
                $this->logger->error('No schema could be established for your request');

                return new Response('No schema could be established for your request', '400', ['Content-type' => $this->data['endpoint']->getDefaultContentType()]);
            }

            // Let's see if the found result is allowd for this endpoint.
            if (isset($this->data['endpoint']) === true && in_array($this->schema->getId(), $allowedSchemas['id']) === false) {
                $this->logger->error('Object is not supported by this endpoint');

                return new Response('Object is not supported by this endpoint', '406', ['Content-type' => $this->data['endpoint']->getDefaultContentType()]);
            }

            // Let's see if we have a body.
            if (isset($this->content) === false || empty($this->content) === true) {
                $this->logger->error('The body of your request is empty');

                return new Response('The body of your request is empty', '400', ['Content-type' => $this->data['endpoint']->getDefaultContentType()]);
            }

            $this->object = $this->entityManager->find('App:ObjectEntity', $this->identification);

            // if ($validation = $this->object->validate($this->content) && $this->object->hydrate($content, true)) {
            $this->logger->debug('updating object '.$this->identification);

            if ($this->object->getLock() === null
                || $this->object->getLock() !== null
                && key_exists('lock', $this->content) === true
                && $this->object->getLock() === $this->content['lock']
            ) {
                $validationErrors = $this->validationService->validateData($this->content, $this->schema, 'PUT');
                if ($validationErrors === null && $this->object->hydrate($this->content, true)) {
                    // This should be an unsafe hydration.
                    if (array_key_exists('@dateRead', $this->content) === true && $this->content['@dateRead'] == false) {
                        $this->objectEntityService->setUnread($this->object);
                    }

                    if ($this->schema->getPersist() === true) {
                        $this->entityManager->persist($this->object);
                        $this->entityManager->flush();
                        $this->cacheService->cacheObject($this->object);
                        $this->entityManager->flush();
                    }
                } else if ($validationErrors !== null) {
                    $result = [
                        "Message" => 'Validation errors',
                        'data'    => $validationErrors,
                        'path'    => $this->data['pathRaw'],
                    ];
                    break;
                }
            }//end if

            $result = $this->cacheService->getObject($this->object->getId());
            break;
        case 'PATCH':
            $eventType = 'commongateway.object.update';

            // We dont have an id on a PATCH so die.
            if (isset($this->identification) === true) {
                $this->logger->error('No id could be established for your request');

                return new Response('No id could be established for your request', '400', ['Content-type' => $this->data['endpoint']->getDefaultContentType()]);
            }

            $this->session->set('object', $this->identification);

            // We need to know the type of object that the user is trying to post, so let's look that up.
            if ($this->schema instanceof Entity === false) {
                $this->logger->error('No schema could be established for your request');

                return new Response('No schema could be established for your request', '400', ['Content-type' => $this->data['endpoint']->getDefaultContentType()]);
            }

            // Let's see if the found result is allowd for this endpoint.
            if (isset($this->data['endpoint']) === true && in_array($this->schema->getId(), $allowedSchemas['id']) === false) {
                $this->logger->error('Object is not supported by this endpoint');

                return new Response('Object is not supported by this endpoint', '406', ['Content-type' => $this->data['endpoint']->getDefaultContentType()]);
            }

            // Let's see if we have a body.
            if (isset($this->content) === false || empty($this->content) === true) {
                $this->logger->error('The body of your request is empty');

                return new Response('The body of your request is empty', '400', ['Content-type' => $this->data['endpoint']->getDefaultContentType()]);
            }

            $this->object = $this->entityManager->find('App:ObjectEntity', $this->identification);

            // if ($this->object->hydrate($this->content) && $validation = $this->object->validate()) {
            $this->logger->debug('updating object '.$this->identification);

            if ($this->object->getLock() === null
                || $this->object->getLock() !== null
                && key_exists('lock', $this->content)
                && $this->object->getLock() === $this->content['lock']
            ) {
                $validationErrors = $this->validationService->validateData($this->content, $this->schema, 'PATCH');
                if ($validationErrors === null && $this->object->hydrate($this->content)) {
                    if (array_key_exists('@dateRead', $this->content) && $this->content['@dateRead'] == false) {
                        $this->objectEntityService->setUnread($this->object);
                    }

                    if ($this->schema->getPersist() === true) {
                        $this->entityManager->persist($this->object);
                        $this->entityManager->flush();
                        $this->cacheService->cacheObject($this->object);
                        $this->entityManager->flush();
                    }
                } else if ($validationErrors !== null) {
                    $result = [
                        "Message" => 'Validation errors',
                        'data'    => $validationErrors,
                        'path'    => $this->data['pathRaw'],
                    ];
                    break;
                }
            }//end if

            $result = $this->cacheService->getObject($this->object->getId());
            break;
        case 'DELETE':

            // We dont have an id on a PUT so die.
            if (isset($this->identification) === false) {
                $this->logger->error('No id could be established for your request');

                return new Response('No id could be established for your request', '400', ['Content-type' => $this->data['endpoint']->getDefaultContentType()]);
            }

            $this->session->set('object', $this->identification);

            // We need to know the type of object that the user is trying to post, so let's look that up.
            if ($this->schema instanceof Entity === false) {
                $this->logger->error('No schema could be established for your request');

                return new Response('No schema could be established for your request', '400', ['Content-type' => $this->data['endpoint']->getDefaultContentType()]);
            }

            // Let's see if the found result is allowd for this endpoint.
            if (isset($this->data['endpoint']) === true && in_array($this->schema->getId(), $allowedSchemas['id']) === false) {
                $this->logger->error('Object is not supported by this endpoint');

                return new Response('Object is not supported by this endpoint', '406', ['Content-type' => $this->data['endpoint']->getDefaultContentType()]);
            }

            $this->entityManager->remove($this->object);
            $this->entityManager->flush();
            $this->logger->info('Succesfully deleted object');

            return new Response('', '204', ['Content-type' => $this->data['endpoint']->getDefaultContentType()]);
        default:
            $this->logger->error('Unkown method'.$this->data['method']);

            return new Response('Unkown method'.$this->data['method'], '404', ['Content-type' => $this->data['endpoint']->getDefaultContentType()]);
        }//end switch

        // Handle _self metadata, includes adding dateRead
        $this->handleMetadataSelf($result, $metadataSelf);

        // Handle application configuration out for embedded if we need to do this for the current application and current endpoint.
        if (isset($appEndpointConfig['out']['embedded']) === true) {
            $result = $this->shouldWeUnsetEmbedded($result, $appEndpointConfig['out']['embedded']);
        }

        if (isset($eventType) === true && isset($result) === true) {
            $event = new ActionEvent($eventType, ['response' => $result, 'entity' => ($this->object->getEntity()->getReference() ?? $this->object->getEntity()->getId()->toString()), 'parameters' => $this->data]);
            $this->eventDispatcher->dispatch($event, $event->getType());

            switch ($this->data['method']) {
            case 'POST':
                $code = Response::HTTP_CREATED;
                break;
            default:
                $code = Response::HTTP_OK;
                break;
            }

            if (isset($validationErrors)) {
                $code = Response::HTTP_BAD_REQUEST;
            }

            // If we have a response return that
            if ($event->getData()['response']) {
                return new Response($this->serializeData($event->getData()['response'], $contentType), $code, ['Content-type' => $contentType]);
            }
        }//end if

        return $this->createResponse($result);

    }//end requestHandler()

    /**
     * Gets the application configuration 'in' and/or 'out' for the current endpoint.
     *
     * @param string $endpointRef       The reference of the current endpoint
     * @param string $endpoint          The current endpoint path
     * @param string $applicationConfig An item of the configuration of the application
     *
     * @return array The 'in' and 'out' configuration of the Application for the current Endpoint.
     */
    private function getConfigInOutOrGlobal(string $endpointRef, string $endpoint, array $applicationConfig): array
    {
        $appEndpointConfig = [];

        foreach (['in', 'out'] as $type) {
            if (array_key_exists($endpointRef, $applicationConfig) === true && array_key_exists($type, $applicationConfig[$endpointRef])) {
                $appEndpointConfig[$type] = $applicationConfig[$endpointRef][$type];
                continue;
            }

            if (array_key_exists($endpoint, $applicationConfig) === true && array_key_exists($type, $applicationConfig[$endpoint])) {
                $appEndpointConfig[$type] = $applicationConfig[$endpoint][$type];
                continue;
            }

            if (array_key_exists('global', $applicationConfig) === true && array_key_exists($type, $applicationConfig['global'])) {
                // Do global last, so that we allow overwriting the global options for specific endpoints ^.
                $appEndpointConfig[$type] = $applicationConfig['global'][$type];
            }
        }

        return $appEndpointConfig;

    }//end getConfigInOutOrGlobal()

    /**
     * Gets the application configuration 'in' and/or 'out' for the current endpoint.
     * First checks if the current/active application has configuration.
     * If this is the case, check if the currently used endpoint or 'global' is present in this configuration for 'in' and/or 'out'.
     * Example: application->configuration['global']['out'].
     *
     * @return array The 'in' and 'out' configuration of the Application for the current Endpoint.
     */
    private function getAppEndpointConfig(): array
    {
        // @TODO set created application to the session
        $application = $this->entityManager->getRepository('App:Application')->findOneBy(['id' => $this->session->get('application')]);
        if ($application instanceof Application === false
            || $application->getConfiguration() === null
        ) {
            return [];
        }

        $endpointRef = isset($this->data['endpoint']) === true ? $this->data['endpoint']->getReference() : '/';
        $endpoint    = $this->getCurrentEndpoint();

        $appEndpointConfig = [];
        foreach ($application->getConfiguration() as $applicationConfig) {
            $appEndpointConfig = $this->getConfigInOutOrGlobal($endpointRef, $endpoint, $applicationConfig);
        }

        return $appEndpointConfig;

    }//end getAppEndpointConfig()

    /**
     * Gets the path (/endpoint) of the currently used Endpoint, using the path array of the current Endpoint.
     *
     * @return string The /endpoint string of the current Endpoint.
     */
    private function getCurrentEndpoint(): string
    {
        if (isset($this->data['endpoint']) === false) {
            return '/';
        }

        $pathArray = $this->data['endpoint']->getPath();

        // Remove ending id from path to get the core/main endpoint.
        // This way /endpoint without /id can be used in Application Configuration for all CRUD calls.
        if (end($pathArray) === 'id') {
            array_pop($pathArray);
        }

        return '/'.implode('/', $pathArray);

    }//end getCurrentEndpoint()

    /**
     * If embedded should be shown or not.
     * Handle the Application Endpoint configuration for query params. If filters/query should be changed in any way.
     *
     * @param array $filters     The filters/query used for the current api-call.
     * @param array $queryConfig Application configuration ['in']['query']
     *
     * @return array The updated filters/query used for the current api-call.
     */
    private function queryAppEndpointConfig(array $filters, array $queryConfig): array
    {
        // Check if there is a mapping key.
        if (key_exists('mapping', $queryConfig) === true) {
            // Find the mapping.
            $mapping = $this->resourceService->getMapping($queryConfig['mapping'], 'commongateway/corebundle');

            // Map the filters with the given mapping object.
            $filters = $this->mappingService->mapping($mapping, $filters);
        }

        return $filters;

    }//end queryAppEndpointConfig()

    /**
     * Handle the Application Endpoint Configuration for embedded. If embedded should be shown or not.
     * Configuration Example: ['global']['out']['embedded']['unset'] = true
     * Configuration Example 2: ['global']['out']['embedded']['unset']['except'] = ['application/json+ld', 'application/ld+json'].
     *
     * @param object|array $result         fetched result
     * @param array        $embeddedConfig Application configuration ['out']['embedded']
     *
     * @return array|null The updated result.
     */
    public function shouldWeUnsetEmbedded($result, array $embeddedConfig)
    {
        if (isset($embeddedConfig['unset']) === false) {
            return $result;
        }

        if (isset($result) === true
            && (isset($embeddedConfig['unset']['except']) === true && isset($this->data['headers']['accept']) === true
            && empty(array_intersect($embeddedConfig['unset']['except'], $this->data['headers']['accept'])) === true)
            || isset($this->data['headers']['accept']) === false
            || isset($embeddedConfig['unset']['except']) === false
        ) {
            if (isset($result['results']) === true) {
                foreach ($result['results'] as $key => $item) {
                    $result['results'][$key] = $this->checkEmbedded($item);
                }
            } else {
                $result = $this->checkEmbedded($result);
            }
        }//end if

        return $result;

    }//end shouldWeUnsetEmbedded()

    /**
     * If embedded should be shown or not.
     *
     * @param object|array $result fetched result
     *
     * @return array|null
     */
    public function checkEmbedded($result)
    {
        if (isset($result->embedded) === true) {
            unset($result->embedded);
        }

        if (isset($result['embedded']) === true) {
            unset($result['embedded']);
        }

        return $result;

    }//end checkEmbedded()

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
        // @todo: Adding type array before &$result will break this function ^^^.
        if (empty($metadataSelf) === true) {
            return;
        }

        // todo: $this->identification is sometimes empty, it should never be an empty string.
        if (isset($result['results']) === true && $this->data['method'] === 'GET' && empty($this->identification) === true) {
            array_walk(
                $result['results'],
                function (&$record) {
                    $record = iterator_to_array($record);
                }
            );
            foreach ($result['results'] as &$collectionItem) {
                $this->handleMetadataSelf($collectionItem, $metadataSelf);
            }

            return;
        }//end if

        if (empty($result['id']) === true || Uuid::isValid($result['id']) === false) {
            return;
        }

        $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $result['id']]);

        if ($objectEntity instanceof ObjectEntity === false) {
            return;
        }

        if ($this->data['method'] === 'GET' && empty($this->identification) === false) {
            $metadataSelf['dateRead'] = 'getItem';
        }

        $this->responseService->xCommongatewayMetadata = $metadataSelf;
        $resultMetadataSelf                            = (array) $result['_self'];
        $this->responseService->addToMetadata($resultMetadataSelf, 'dateRead', $objectEntity);
        $result['_self'] = $resultMetadataSelf;

    }//end handleMetadataSelf()

    /**
     * Determines the proxy source from configuration, then use proxy handler to proxy the request.
     *
     * @param array $parameters    The parameters of the request.
     * @param array $configuration The configuration of the action.
     *
     * @return array The result of the proxy.
     */
    public function proxyRequestHandler(array $parameters, array $configuration): array
    {
        $source = $this->entityManager->getRepository('App:Gateway')->findOneBy(['reference' => $configuration['source']]);

        return ['response' => $this->proxyHandler($parameters, $configuration, $source)];

    }//end proxyRequestHandler()

    /**
     * Creating the response object.
     *
     * @param $data
     *
     * @return Response
     */
    public function createResponse($data): Response
    {
        if ($data instanceof ObjectEntity) {
            $data = $data->toArray();
        }

        $content = $this->serializeData($data, $contentType);

        return new Response(
            $content,
            Response::HTTP_OK,
            ['Content-type' => $contentType]
        );

    }//end createResponse()
}//end class
