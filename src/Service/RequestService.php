<?php

namespace CommonGateway\CoreBundle\Service;

use Adbar\Dot;
use App\Entity\Application;
use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\Gateway as Source;
use App\Entity\ObjectEntity;
use App\Entity\Mapping;
use App\Event\ActionEvent;
use App\Exception\GatewayException;
use App\Service\SynchronizationService;
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
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Serializer;

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
     * @var FileSystemHandleService The fileSystem service
     */
    private FileSystemHandleService $fileSystemService;

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

    /**
     * @var ReadUnreadService
     */
    private ReadUnreadService $readUnreadService;

    /**
     * @var SynchronizationService
     */
    private SynchronizationService $syncService;

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
     * @param EntityManagerInterface   $entityManager     The entity manager
     * @param GatewayResourceService   $resourceService   The resource service
     * @param MappingService           $mappingService    The mapping service
     * @param ValidationService        $validationService The validation service
     * @param FileSystemHandleService  $fileSystemService The file system service
     * @param CacheService             $cacheService      The cache service
     * @param ReadUnreadService        $readUnreadService The read unread service
     * @param SynchronizationService   $syncService       The SynchronizationService.
     * @param CallService              $callService       The call service
     * @param Security                 $security          Security
     * @param EventDispatcherInterface $eventDispatcher   Event dispatcher
     * @param SerializerInterface      $serializer        The serializer
     * @param SessionInterface         $session           The current session
     * @param LoggerInterface          $requestLogger     The logger interface
     * @param DownloadService          $downloadService   The download service
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        GatewayResourceService $resourceService,
        MappingService $mappingService,
        ValidationService $validationService,
        FileSystemHandleService $fileSystemService,
        CacheService $cacheService,
        ReadUnreadService $readUnreadService,
        SynchronizationService $syncService,
        CallService $callService,
        Security $security,
        EventDispatcherInterface $eventDispatcher,
        SerializerInterface $serializer,
        SessionInterface $session,
        LoggerInterface $requestLogger,
        DownloadService $downloadService
    ) {
        $this->entityManager     = $entityManager;
        $this->cacheService      = $cacheService;
        $this->resourceService   = $resourceService;
        $this->mappingService    = $mappingService;
        $this->validationService = $validationService;
        $this->fileSystemService = $fileSystemService;
        $this->readUnreadService = $readUnreadService;
        $this->syncService       = $syncService;
        $this->callService       = $callService;
        $this->security          = $security;
        $this->eventDispatcher   = $eventDispatcher;
        $this->serializer        = $serializer;
        $this->session           = $session;
        $this->logger            = $requestLogger;
        $this->downloadService   = $downloadService;

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
        $accept = 'json';
        if (isset($this->data['accept']) === true) {
            $accept = $this->data['accept'];
        }

        $endpoint = null;
        if (isset($this->data['endpoint']) === true) {
            $endpoint = $this->data['endpoint'];
        }

        $serializer = new Serializer([], [new XmlEncoder(), new CsvEncoder()]);

        // @TODO: Create hal and ld encoding.
        switch ($accept) {
        case 'pdf':
            $content = $this->downloadService->downloadPdf($data);
            break;
        case 'html':
            $content = $this->downloadService->downloadHtml($data);
            break;
        case 'docx':
            $content = $this->downloadService->downloadDocx($data);
            break;
        case 'xml':
        case 'csv':
            $content = $serializer->serialize($data, $accept);
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
     * Determines the right content type and unserializes the content accordingly.
     *
     * @param string $content     The content to unserialize.
     * @param string $contentType The content type to use.
     *
     * @return array The unserialized data.
     */
    private function unserializeData(string $content, string $contentType): array
    {
        $xmlEncoder = new XmlEncoder([]);

        if (str_contains($contentType, 'xml') === true) {
            return $xmlEncoder->decode($content, 'xml');
        }

        return \Safe\json_decode($content, true);

    }//end unserializeData()

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
        $scopes        = $this->getScopes();
        $loopedSchemas = [];
        foreach ($references as $reference) {
            $schemaScope     = "$type.$reference.{$this->data['method']}";
            $loopedSchemas[] = $schemaScope;
            if (in_array($schemaScope, $scopes) === true) {
                // If true the user is authorized.
                return null;
            }
        }

        // If the user doesn't have the normal scope and doesn't have the admin scope, return a 403 forbidden.
        if (in_array("admin.{$this->data['method']}", $scopes) === false) {
            $implodeString = implode(', ', $loopedSchemas);
            $this->logger->error("Authentication failed. You do not have any of the required scopes for this endpoint. ($implodeString)");
            return new Response(
                $this->serializeData(
                    [
                        'message' => "Authentication failed. You do not have any of the required scopes for this endpoint.",
                        'scopes'  => ['anyOf' => $loopedSchemas],
                    ],
                    $contentType
                ),
                Response::HTTP_FORBIDDEN,
                ['Content-type' => $contentType]
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

    private function proxyConfigBuilder(): array
    {
        if (strpos($this->data['headers']['content-type'][0],  'multipart/form-data') !== false) {
            $post = $this->data['post'];
            array_walk(
                $post,
                function (&$value, $key) {
                    $value = [
                        'name'     => $key,
                        'contents' => $value,
                    ];
                }
            );
            return [
                'query'     => $this->data['query'],
                'headers'   => $this->data['headers'],
                'multipart' => array_values($post),
            ];
        } else if (strpos($this->data['headers']['content-type'][0], 'application/x-www-form-urlencoded') !== false) {
            return [
                'query'     => $this->data['query'],
                'headers'   => $this->data['headers'],
                'form_data' => $this->data['post'],
            ];
        }//end if

        return [
            'query'   => $this->data['query'],
            'headers' => $this->data['headers'],
            'body'    => $this->data['crude_body'],
        ];

    }//end proxyConfigBuilder()

    /**
     * Handles a proxy Endpoint.
     * todo: we want to merge proxyHandler() and requestHandler() code at some point.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration from the call
     *
     * @return Response The data as returned bij the original source
     */
    public function proxyHandler(array $data, array $configuration, ?Source $proxy = null): Response
    {
        $this->data          = $data;
        $this->configuration = $configuration;

        // If we already have a proxy, we can skip these checks.
        if ($proxy instanceof Source === false) {
            $proxy = $data['endpoint']->getProxy();
            // We only do proxying if the endpoint forces it, and we do not have a proxy.
            if ($data['endpoint'] instanceof Endpoint === false || $proxy === null) {
                $message = !$data['endpoint'] instanceof Endpoint ? "No Endpoint in data['endpoint']" : "This Endpoint has no Proxy: {$data['endpoint']->getName()}";

                return new Response(
                    $this->serializeData(['message' => $message], $contentType),
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
        if (isset($this->data['query']['extend']) === true) {
            $extend = $this->data['query']['extend'];
            // Make sure we do not send this gateway specific query param to the proxy / Source.
            unset($this->data['query']['extend']);
        }

        // Make sure we set object to null in the session, for detecting the correct AuditTrails to create. Also used for DateRead to work correctly!
        $this->session->set('object', null);

        if (isset($data['path']['{route}']) === true && empty($data['path']['{route}']) === false) {
            $this->data['path'] = '/'.$data['path']['{route}'];
        } else {
            $this->data['path'] = '';
        }

        // Don't pass gateway authorization to the source.
        unset($this->data['headers']['authorization']);

        $url = \Safe\parse_url($proxy->getLocation());

        // Make a guzzle call to the source based on the incoming call.
        try {
            // Check if we are dealing with http, https or something else like a ftp (fileSystem).
            if (($url['scheme'] === 'http' || $url['scheme'] === 'https')) {
                $result = $this->callService->call(
                    $proxy,
                    $this->data['path'],
                    $this->data['method'],
                    $this->proxyConfigBuilder()
                );
            } else {
                $result = $this->fileSystemService->call($proxy, $this->data['path']);
                $result = new \GuzzleHttp\Psr7\Response(200, [], $this->serializer->serialize($result, 'json'));
            }//end if

            $contentType = 'application/json';
            if (isset($result->getHeaders()['content-type'][0]) === true) {
                $contentType = $result->getHeaders()['content-type'][0];
            }

            $resultContent = $this->unserializeData($result->getBody()->getContents(), $contentType);

            // Handle _self metadata, includes adding dateRead
            if (isset($extend) === true) {
                $this->data['query']['extend'] = $extend;
            }

            $this->handleMetadataSelf($resultContent, $proxy);

            $headers = $result->getHeaders();

            if (isset($headers['content-length']) === true) {
                unset($headers['content-length']);
            }

            if (isset($headers['Content-Length']) === true) {
                unset($headers['Content-Length']);
            }

            // Let create a response from the guzzle call.
            $response = new Response(
                $this->serializeData($resultContent, $contentType),
                $result->getStatusCode(),
                $headers
            );
        } catch (Exception $exception) {
            $statusCode = 500;
            if (array_key_exists($exception->getCode(), Response::$statusTexts) === true) {
                $statusCode = $exception->getCode();
            }

            if (method_exists(get_class($exception), 'getResponse') === true && $exception->getResponse() !== null) {
                $body       = $exception->getResponse()->getBody()->getContents();
                $statusCode = $exception->getResponse()->getStatusCode();
                $headers    = $exception->getResponse()->getHeaders();
            }

            // Catch weird statuscodes (like 0).
            if (array_key_exists($statusCode, Response::$statusTexts) === false) {
                $statusCode = 502;
            }

            $content  = $this->serializeData(
                [
                    'message' => $exception->getMessage(),
                    'body'    => ($body ?? "Can\'t get a response & body for this type of Exception: ").get_class($exception),
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
                $scopes[] = str_replace('ROLE_', '', $role);
            }

            return $scopes;
        }//end if

        // If we don't have a user, return the anonymous security group its scopes.
        $anonymousSecurityGroup = $this->entityManager->getRepository('App:SecurityGroup')->findOneBy(['anonymous' => true]);
        if ($anonymousSecurityGroup !== null) {
            $scopes = [];
            foreach ($anonymousSecurityGroup->getScopes() as $scope) {
                $scopes[] = $scope;
            }

            return $scopes;
        }

        // If we don't have a user or anonymous security group, return an empty array (this will result in a 403 response in the checkUserScopes function).
        return [];

    }//end getScopes()

    /**
     * Handles incoming requests and is responsible for generating a response.
     * todo: we want to merge requestHandler() and proxyHandler() code at some point.
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

        // Make a list of schema's that are allowed for this endpoint.
        $allowedSchemas = $this->getAllowedSchemas();

        // Check if the user has the needed scopes.
        $securityResponse = $this->checkUserScopes($allowedSchemas['reference']);
        if ($securityResponse instanceof Response === true) {
            return $securityResponse;
        }

        // Make sure we set object to null in the session, for detecting the correct AuditTrails to create. Also used for DateRead to work correctly!
        $this->session->set('object', null);

        // All prepped so let's go.
        // todo: split these into functions?
        switch ($this->data['method']) {
        case 'GET':
            // We have an id (so single object).
            if (isset($this->identification) === true && empty($this->identification) === false) {
                $this->session->set('object', $this->identification);
                $result = $this->cacheService->getObject($this->identification);

                if (isset($this->data['query']['versie']) === true) {
                    $auditTrails = $this->entityManager->getRepository('App:AuditTrail')->findBy(['resource' => $this->identification]);

                    foreach ($auditTrails as $auditTrail) {
                        if ($auditTrail->getAmendments() !== null
                            && isset($auditTrail->getAmendments()['old']['versie']) === true
                            && $auditTrail->getAmendments()['old']['versie'] === (int) $this->data['query']['versie']
                        ) {
                            $result = $auditTrail->getAmendments()['old'];
                        }
                    }
                }

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
                    "message" => 'Validation errors',
                    'data'    => $validationErrors,
                    'path'    => $this->data['pathRaw'] ?? null,
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
                        $this->readUnreadService->setUnread($this->object);
                    }

                    if ($this->schema->getPersist() === true) {
                        $this->entityManager->persist($this->object);
                        $this->entityManager->flush();
                        $this->cacheService->cacheObject($this->object);
                        $this->entityManager->flush();
                    }
                } else if ($validationErrors !== null) {
                    $result = [
                        "message" => 'Validation errors',
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
                        $this->readUnreadService->setUnread($this->object);
                    }

                    if ($this->schema->getPersist() === true) {
                        $this->entityManager->persist($this->object);
                        $this->entityManager->flush();
                        $this->cacheService->cacheObject($this->object);
                        $this->entityManager->flush();
                    }
                } else if ($validationErrors !== null) {
                    $result = [
                        "message" => 'Validation errors',
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

            return new Response('', '204', ['Content-type' => ($this->data['endpoint']->getDefaultContentType() ?? 'application/json')]);
        default:
            $this->logger->error('Unkown method'.$this->data['method']);

            return new Response('Unkown method'.$this->data['method'], '404', ['Content-type' => $this->data['endpoint']->getDefaultContentType()]);
        }//end switch

        // Handle _self metadata, includes adding dateRead
        $this->handleMetadataSelf($result);

        // Handle application configuration out for embedded if we need to do this for the current application and current endpoint.
        if (isset($appEndpointConfig['out']['embedded']) === true) {
            $result = $this->shouldWeUnsetEmbedded($result, $appEndpointConfig['out']['embedded']);
        }

        if (isset($appEndpointConfig) === true) {
            $result = $this->handleAppConfigOut($appEndpointConfig, $result);
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

            // If we have a response return that.
            if (isset($event->getData()['response']) === true && empty($event->getData()['response']) === false) {
                return new Response($this->serializeData($event->getData()['response'], $contentType), $code, ['Content-type' => $contentType]);
            }
        }//end if

        // Check download accept types.
        if (isset($this->data['headers']['accept'][0]) === true && in_array($this->data['headers']['accept'][0], ['text/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']) === true) {
            $result = $this->checkMappingFromHeaders($result);
            if (empty($this->identification) === false) {
                $result = [$result];
            } else {
                $result = $result['results'];
            }

            switch ($this->data['headers']['accept'][0]) {
            case 'text/csv':
                $dataAsString = $this->serializeData($result, $contentType);
                return $this->downloadService->downloadCSV($dataAsString);
            case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
                return $this->downloadService->downloadXLSX($result);
            }
        }//end if

        return $this->createResponse($result);

    }//end requestHandler()

    /**
     * Checks and maps headers if they contain valid mapping UUID.
     *
     * This method looks into the headers to find an 'x-mapping' key and checks if it contains
     * a valid UUID. If valid, it retrieves the corresponding mapping and updates the result.
     *
     * @param array $result The current result.
     *
     * @return array The updated result after potential mapping.
     */
    private function checkMappingFromHeaders(array $result): array
    {
        if (isset($this->data['headers']['x-mapping'][0]) === true) {
            if (Uuid::isValid($this->data['headers']['x-mapping'][0]) === true) {
                $mapping = $this->entityManager->getRepository('App:Mapping')->find($this->data['headers']['x-mapping'][0]);
            }
        }

        if (isset($mapping) === true) {
            $result = $this->mapResults($mapping, $result);
        }

        return $result;

    }//end checkMappingFromHeaders()

    /**
     * Maps the results using the provided mapping.
     *
     * This method checks the result for a 'results' key. If it exists, each object inside 'results'
     * gets mapped using the provided mapping. If not, the entire result gets mapped.
     *
     * @param Mapping $mapping The mapping each object needs to be mapped with.
     * @param array   $result  The current result.
     *
     * @return array The updated result after mapping.
     */
    private function mapResults(Mapping $mapping, array $result): array
    {
        if (isset($result['results']) === true) {
            foreach ($result['results'] as $key => $object) {
                $result['results'][$key] = $this->mappingService->mapping($mapping, $object);
            }
        } else {
            $result = $this->mappingService->mapping($mapping, $result);
        }

        return $result;

    }//end mapResults()

    /**
     * Handle output config of the endpoint.
     *
     * @param array $appEndpointConfig The application endpoint config.
     * @param array $result            The result so far.
     *
     * @return array The updated result.
     */
    public function handleAppConfigOut(array $appEndpointConfig, array $result): array
    {
        // We want to do more abstract functionality for output settings, keep in mind for the future.
        if (isset($appEndpointConfig['out']['body']['mapping']) === true) {
            $result = $this->mappingService->mapping($this->resourceService->getMapping($appEndpointConfig['out']['body']['mapping'], 'commongateway/corebundle'), $result);
        }

        return $result;

    }//end handleAppConfigOut()

    /**
     * Gets the application configuration 'in' and/or 'out' for the current endpoint.
     * Will first check for endpoint reference, then used endpoint (as string) and lastly for 'global' (all endpoints).
     *
     * @param string $endpointRef       The reference of the current endpoint
     * @param string $endpoint          The current endpoint path
     * @param array  $applicationConfig An item of the configuration of the application
     *
     * @return array The 'in' and 'out' configuration of the Application for the current Endpoint.
     */
    private function getAppConfigInOut(string $endpointRef, string $endpoint, array $applicationConfig): array
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

    }//end getAppConfigInOut()

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
            $appEndpointConfig = array_merge($this->getAppConfigInOut($endpointRef, $endpoint, $applicationConfig), $appEndpointConfig);
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
     * Add extra parameters to the _self metadata of an Object result. Such as dateRead.
     *
     * @param array       $result The result array containing one or multiple objects. Or a single object from a result array (recursion).
     * @param Source|null $proxy  In case we are dealing with a proxy endpoint, we need the Source in order to create a Synchronization and ObjectEntity.
     *
     * @return void
     */
    private function handleMetadataSelf(array &$result, ?Source $proxy = null)
    {
        // For now, we only allow this function to be used for dateRead when the extend dateRead query param is given.
        if (isset($this->data['query']['extend']) === false || in_array('_self.dateRead', $this->data['query']['extend']) === false) {
            return;
        }

        // Note: $this->identification is sometimes empty, it should never be an empty string.
        // Todo: make $result['results'] key 'results' configurable? for when using this for proxy endpoints. For now we just add 'results' with Source mapping.
        if (isset($result['results']) === true && $this->data['method'] === 'GET' && empty($this->identification) === true) {
            $this->metadataSelfResults($result, $proxy);

            return;
        }//end if

        $objectEntity = $this->metadataSelfObject($result, $proxy);
        if ($objectEntity === null) {
            return;
        }

        $getItem = false;
        if ($this->data['method'] === 'GET' && empty($this->identification) === false) {
            $getItem = true;
        }

        // This should only be possible for proxy endpoints
        if (isset($result['_self']) === false) {
            $result['_self'] = [];
        }

        // Deal with MongoDb objects
        if (is_array($result['_self']) === false) {
            $result['_self'] = iterator_to_array($result['_self']);
        }

        $result['_self'] = $this->readUnreadService->addDateRead($result['_self'], $objectEntity, $getItem);

    }//end handleMetadataSelf()

    /**
     * In case we are handling metadata self for an array of objects instead of one.
     *
     * @param array       $result The result array containing multiple objects.
     * @param Source|null $proxy  In case we are dealing with a proxy endpoint, we need the Source in order to create a Synchronization and ObjectEntity.
     *
     * @return void
     */
    private function metadataSelfResults(array &$result, ?Source $proxy)
    {
        array_walk(
            $result['results'],
            function (&$record) {
                if (is_array($record) === false) {
                    $record = iterator_to_array($record);
                }
            }
        );
        foreach ($result['results'] as &$collectionItem) {
            $this->handleMetadataSelf($collectionItem, $proxy);
        }

    }//end metadataSelfResults()

    /**
     * Handles getting an ObjectEntity to be used for handleMetadataSelf (includes adding dateRead).
     *
     * @param array       $result The result array of an ObjectEntity from MongoDB.
     * @param Source|null $proxy  A Source in case we are dealing with a proxy endpoint.
     *
     * @return ObjectEntity|null The ObjectEntity found or null.
     */
    private function metadataSelfObject(array &$result, ?Source $proxy): ?ObjectEntity
    {
        // Todo: make $result['_id'] key '_id' configurable? for when using this for proxy endpoints. For now we just add '_id' with Source mapping.
        if (empty($result['_id']) === true || ($proxy === null && Uuid::isValid($result['_id']) === false)) {
            return null;
        }

        if ($proxy === null) {
            // Note: $this->object is never set if method === 'GET'. And in case we have a Get Collection we have to use _id anyway.
            $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $result['_id']]);

            if ($objectEntity instanceof ObjectEntity === false) {
                return null;
            }

            return $objectEntity;
        }

        // Todo: a temporary way to be able to use this function for proxy endpoints, until we figured out a beter way how we can save proxy objects as ObjectEntity.
        // Todo: For now we just add '_self'.'schema'.'ref' with Source mapping
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $result['_self']['schema']['ref']]);
        if ($entity instanceof Entity === false) {
            return null;
        }

        $synchronization = $this->syncService->findSyncBySource($proxy, $entity, $result['_id']);
        $this->syncService->checkObjectEntity($synchronization);
        $objectEntity = $synchronization->getObject();
        // We could do a hydrate here, but will have negative impact on performance.
        $this->entityManager->flush();
        // We need to set this $result['id'], so we are able to get it from a GET collection response and use it to set/unset read/unread.
        $result['id'] = $objectEntity->getId()->toString();

        return $objectEntity;

    }//end metadataSelfObject()

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
