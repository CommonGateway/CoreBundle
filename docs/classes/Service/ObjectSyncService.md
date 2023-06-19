# CommonGateway\CoreBundle\Service\ObjectSyncService  

This service handles calls on the ZZ endpoint (or in other words abstract routing).





## Methods

| Name | Description |
|------|-------------|
|[__construct](#objectsyncservice__construct)|The constructor sets al needed variables.|
|[objectSyncHandler](#objectsyncserviceobjectsynchandler)|Synchronise the object to the source.|




### ObjectSyncService::__construct  

**Description**

```php
public __construct (\EntityManagerInterface $entityManager, \SynchronizationService $syncService, \CallService $callService, \GatewayResourceService $resourceService, \LoggerInterface $objectSyncLogger)
```

The constructor sets al needed variables. 

 

**Parameters**

* `(\EntityManagerInterface) $entityManager`
: The enitymanger  
* `(\SynchronizationService) $syncService`
: The synchronisation service  
* `(\CallService) $callService`
: The call service  
* `(\GatewayResourceService) $resourceService`
: The resource service  
* `(\LoggerInterface) $objectSyncLogger`
: The logger interface  

**Return Values**

`void`


<hr />


### ObjectSyncService::objectSyncHandler  

**Description**

```php
public objectSyncHandler (array $data)
```

Synchronise the object to the source. 

 

**Parameters**

* `(array) $data`
: A data arry containing a source, a schema and an object.  

**Return Values**

`array`

> The path array for a proxy endpoint.


**Throws Exceptions**


`\Exception`


<hr />

