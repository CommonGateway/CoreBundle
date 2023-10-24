# CommonGateway\CoreBundle\Service\EndpointService  

This service handles calls on the ZZ endpoint (or in other words abstract routing).





## Methods

| Name | Description |
|------|-------------|
|[__construct](#endpointservice__construct)|The constructor sets al needed variables.|
|[decodeBody](#endpointservicedecodebody)|Decodes the body of the request based upon the content-type header, accept header or endpoint default.|
|[getAcceptType](#endpointservicegetaccepttype)|Gets the accept type based on the request.|
|[getEndpoint](#endpointservicegetendpoint)|Gets the endpoint based on the request.|
|[handleRequest](#endpointservicehandlerequest)|Handle the request afther it commes in through the ZZ controller.|
|[logRequestHeaders](#endpointservicelogrequestheaders)|This function logs the headers of the request and uses the endpoint->getLoggingConfig()['headers'] to unset the headers that don't need to be logged.|




### EndpointService::__construct  

**Description**

```php
public __construct (\EntityManagerInterface $entityManager, \SerializerInterface $serializer, \RequestService $requestService, \EventDispatcherInterface $eventDispatcher, \SessionInterface $session, \LoggerInterface $endpointLogger)
```

The constructor sets al needed variables. 

 

**Parameters**

* `(\EntityManagerInterface) $entityManager`
: The enitymanger  
* `(\SerializerInterface) $serializer`
: The serializer  
* `(\RequestService) $requestService`
: The request service  
* `(\EventDispatcherInterface) $eventDispatcher`
: The event dispatcher  
* `(\SessionInterface) $session`
: The current session  
* `(\LoggerInterface) $endpointLogger`
: The endpoint logger.  

**Return Values**

`void`


<hr />


### EndpointService::decodeBody  

**Description**

```php
public decodeBody (void)
```

Decodes the body of the request based upon the content-type header, accept header or endpoint default. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`array`




<hr />


### EndpointService::getAcceptType  

**Description**

```php
public getAcceptType (void)
```

Gets the accept type based on the request. 

This method breaks complexity rules but since a switch is the most efficent and performent way to do this we made a design decicion to allow it 

**Parameters**

`This function has no parameters.`

**Return Values**

`string`

> The accept type


<hr />


### EndpointService::getEndpoint  

**Description**

```php
public getEndpoint (void)
```

Gets the endpoint based on the request. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`\Endpoint`

> The found endpoint


**Throws Exceptions**


`\Exception`


<hr />


### EndpointService::handleRequest  

**Description**

```php
public handleRequest (\Request $request)
```

Handle the request afther it commes in through the ZZ controller. 

 

**Parameters**

* `(\Request) $request`
: The inbound request  

**Return Values**

`\Response`




**Throws Exceptions**


`\Exception`


<hr />


### EndpointService::logRequestHeaders  

**Description**

```php
public logRequestHeaders (void)
```

This function logs the headers of the request and uses the endpoint->getLoggingConfig()['headers'] to unset the headers that don't need to be logged. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`




<hr />

