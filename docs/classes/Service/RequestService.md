# CommonGateway\CoreBundle\Service\RequestService  

Handles incoming request from endpoints or controllers that relate to the gateways object structure (eav).





## Methods

| Name | Description |
|------|-------------|
|[__construct](#requestservice__construct)|The constructor sets al needed variables.|
|[checkEmbedded](#requestservicecheckembedded)|If embedded should be shown or not.|
|[createResponse](#requestservicecreateresponse)|Creating the response object.|
|[federationProxyHandler](#requestservicefederationproxyhandler)|Runs a federated request to a multitude of proxies and aggregrates the results.|
|[getFederationConfig](#requestservicegetfederationconfig)|Update configuration from federation query parameters, sets timeout and http_errors, unsets the query parameters.|
|[getFederationSources](#requestservicegetfederationsources)|Takes the config array and includes or excludes sources for federated requests based upon query parameters.|
|[getId](#requestservicegetid)|Get the ID from given parameters.|
|[getSchema](#requestservicegetschema)|Get the schema from given parameters returns false if no schema could be established.|
|[getScopes](#requestservicegetscopes)|Get a scopes array for the current user (or of the anonymus if no user s logged in).|
|[proxyHandler](#requestserviceproxyhandler)|Handles a proxy Endpoint.|
|[proxyRequestHandler](#requestserviceproxyrequesthandler)|Determines the proxy source from configuration, then use proxy handler to proxy the request.|
|[realRequestQueryAll](#requestservicerealrequestqueryall)|A function to replace Request->query->all() because Request->query->all() will replace some characters with an underscore.|
|[requestHandler](#requestservicerequesthandler)|Handles incoming requests and is responsible for generating a response.|
|[serializeData](#requestserviceserializedata)|Determines the right content type and serializes the data accordingly.|
|[shouldWeUnsetEmbedded](#requestserviceshouldweunsetembedded)|Handle the Application Endpoint Configuration for embedded. If embedded should be shown or not.|
|[useRelayRating](#requestserviceuserelayrating)|Checks if the query parameter to relay rating is set and if so, return the value while unsetting the query parameter.|




### RequestService::__construct  

**Description**

```php
public __construct (\EntityManagerInterface $entityManager, \GatewayResourceService $resourceService, \MappingService $mappingService, \ValidationService $validationService, \FileSystemHandleService $fileSystemService, \CacheService $cacheService, \ReadUnreadService $readUnreadService, \SynchronizationService $syncService, \CallService $callService, \Security $security, \EventDispatcherInterface $eventDispatcher, \SerializerInterface $serializer, \SessionInterface $session, \LoggerInterface $requestLogger, \DownloadService $downloadService)
```

The constructor sets al needed variables. 

 

**Parameters**

* `(\EntityManagerInterface) $entityManager`
: The entity manager  
* `(\GatewayResourceService) $resourceService`
: The resource service  
* `(\MappingService) $mappingService`
: The mapping service  
* `(\ValidationService) $validationService`
: The validation service  
* `(\FileSystemHandleService) $fileSystemService`
: The file system service  
* `(\CacheService) $cacheService`
: The cache service  
* `(\ReadUnreadService) $readUnreadService`
: The read unread service  
* `(\SynchronizationService) $syncService`
: The SynchronizationService.  
* `(\CallService) $callService`
: The call service  
* `(\Security) $security`
: Security  
* `(\EventDispatcherInterface) $eventDispatcher`
: Event dispatcher  
* `(\SerializerInterface) $serializer`
: The serializer  
* `(\SessionInterface) $session`
: The current session  
* `(\LoggerInterface) $requestLogger`
: The logger interface  
* `(\DownloadService) $downloadService`
: The download service  

**Return Values**

`void`


<hr />


### RequestService::checkEmbedded  

**Description**

```php
public checkEmbedded (object|array $result)
```

If embedded should be shown or not. 

 

**Parameters**

* `(object|array) $result`
: fetched result  

**Return Values**

`array|null`




<hr />


### RequestService::createResponse  

**Description**

```php
public createResponse ( $data)
```

Creating the response object. 

 

**Parameters**

* `() $data`

**Return Values**

`\Response`




<hr />


### RequestService::federationProxyHandler  

**Description**

```php
public federationProxyHandler (\Collection $proxies, string $path, array $config)
```

Runs a federated request to a multitude of proxies and aggregrates the results. 

 

**Parameters**

* `(\Collection) $proxies`
: The proxies to send the request to.  
* `(string) $path`
: The path to send the request to.  
* `(array) $config`
: The call configuration.  

**Return Values**

`\Response`

> The resulting response.


**Throws Exceptions**


`\Exception`


<hr />


### RequestService::getFederationConfig  

**Description**

```php
public getFederationConfig (array $config)
```

Update configuration from federation query parameters, sets timeout and http_errors, unsets the query parameters. 

 

**Parameters**

* `(array) $config`
: The original call configuration including the federation query parameters.  

**Return Values**

`array`

> The updated call configuration.


<hr />


### RequestService::getFederationSources  

**Description**

```php
public getFederationSources (array $config, \Collection $proxies)
```

Takes the config array and includes or excludes sources for federated requests based upon query parameters. 

 

**Parameters**

* `(array) $config`
: The call configuration.  
* `(\Collection) $proxies`
: The full list of proxies configured for the endpoint.  

**Return Values**

`\Collection`

> The list of proxies that remains after including or excluding sources.


**Throws Exceptions**


`\Exception`
> Thrown when both include and exclude query parameters are given.

<hr />


### RequestService::getId  

**Description**

```php
public getId (void)
```

Get the ID from given parameters. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`string|false`




<hr />


### RequestService::getSchema  

**Description**

```php
public getSchema (array $parameters)
```

Get the schema from given parameters returns false if no schema could be established. 

 

**Parameters**

* `(array) $parameters`

**Return Values**

`\Entity|false`




<hr />


### RequestService::getScopes  

**Description**

```php
public getScopes (void)
```

Get a scopes array for the current user (or of the anonymus if no user s logged in). 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`array`




<hr />


### RequestService::proxyHandler  

**Description**

```php
public proxyHandler (array $data, array $configuration)
```

Handles a proxy Endpoint. 

todo: we want to merge proxyHandler() and requestHandler() code at some point. 

**Parameters**

* `(array) $data`
: The data from the call  
* `(array) $configuration`
: The configuration from the call  

**Return Values**

`\Response`

> The data as returned bij the original source


<hr />


### RequestService::proxyRequestHandler  

**Description**

```php
public proxyRequestHandler (array $parameters, array $configuration)
```

Determines the proxy source from configuration, then use proxy handler to proxy the request. 

 

**Parameters**

* `(array) $parameters`
: The parameters of the request.  
* `(array) $configuration`
: The configuration of the action.  

**Return Values**

`array`

> The result of the proxy.


<hr />


### RequestService::realRequestQueryAll  

**Description**

```php
public realRequestQueryAll (string|null $queryString)
```

A function to replace Request->query->all() because Request->query->all() will replace some characters with an underscore. 

This function will not. 

**Parameters**

* `(string|null) $queryString`
: A queryString from a request if we want to give it to this function instead of using global var $_SERVER.  

**Return Values**

`array`

> An array with all query parameters.


<hr />


### RequestService::requestHandler  

**Description**

```php
public requestHandler (array $data, array $configuration)
```

Handles incoming requests and is responsible for generating a response. 

todo: we want to merge requestHandler() and proxyHandler() code at some point. 

**Parameters**

* `(array) $data`
: The data from the call  
* `(array) $configuration`
: The configuration from the call  

**Return Values**

`\Response`

> The modified data


**Throws Exceptions**


`\Exception`


<hr />


### RequestService::serializeData  

**Description**

```php
public serializeData (array $data, mixed $contentType)
```

Determines the right content type and serializes the data accordingly. 

 

**Parameters**

* `(array) $data`
: The data to serialize.  
* `(mixed) $contentType`
: The content type to determine.  

**Return Values**

`string`

> The serialized data.


<hr />


### RequestService::shouldWeUnsetEmbedded  

**Description**

```php
public shouldWeUnsetEmbedded (object|array $result, array $embeddedConfig)
```

Handle the Application Endpoint Configuration for embedded. If embedded should be shown or not. 

Configuration Example: ['global']['out']['embedded']['unset'] = true  
Configuration Example 2: ['global']['out']['embedded']['unset']['except'] = ['application/json+ld', 'application/ld+json']. 

**Parameters**

* `(object|array) $result`
: fetched result  
* `(array) $embeddedConfig`
: Application configuration ['out']['embedded']  

**Return Values**

`array|null`

> The updated result.


<hr />


### RequestService::useRelayRating  

**Description**

```php
public useRelayRating (array $config)
```

Checks if the query parameter to relay rating is set and if so, return the value while unsetting the query parameter. 

 

**Parameters**

* `(array) $config`
: The call configuration.  

**Return Values**

`bool`




<hr />

