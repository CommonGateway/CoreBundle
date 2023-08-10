# CommonGateway\CoreBundle\Service\SynchronizationService  

The synchronization service handles the fetching and sending of data or objects to and from sources (Source/Gateway objects).





## Methods

| Name | Description |
|------|-------------|
|[__construct](#synchronizationservice__construct)|Setting up the base class with required services.|
|[setStyle](#synchronizationservicesetstyle)|Set symfony style in order to output to the console.|
|[synchronizeTemp](#synchronizationservicesynchronizetemp)|Temporary function as replacement of the $this->oldSyncService->synchronize() function.|




### SynchronizationService::__construct  

**Description**

```php
public __construct (\Environment $twig., \LoggerInterface $actionLogger., \SynchronizationService $syncService, \CallService $callService.)
```

Setting up the base class with required services. 

 

**Parameters**

* `(\Environment) $twig.`
* `(\LoggerInterface) $actionLogger.`
* `(\SynchronizationService) $syncService`
: Old one from the gateway.  
* `(\CallService) $callService.`

**Return Values**

`void`


<hr />


### SynchronizationService::setStyle  

**Description**

```php
public setStyle (\SymfonyStyle $style)
```

Set symfony style in order to output to the console. 

 

**Parameters**

* `(\SymfonyStyle) $style`

**Return Values**

`self`




<hr />


### SynchronizationService::synchronizeTemp  

**Description**

```php
public synchronizeTemp (\Synchronization|null $synchronization, array $objectArray, \ObjectEntity $objectEntity, \Schema $schema, string $location, string|null $idLocation, string|null $method)
```

Temporary function as replacement of the $this->oldSyncService->synchronize() function. 

Because currently synchronize function can only pull from a source and not push to a source. 

**Parameters**

* `(\Synchronization|null) $synchronization`
: The synchronization we are going to synchronize.  
* `(array) $objectArray`
: The object data we are going to synchronize.  
* `(\ObjectEntity) $objectEntity`
: The objectEntity which data we are going to synchronize.  
* `(\Schema) $schema`
: The schema the object we are going to send belongs to.  
* `(string) $location`
: The path/endpoint we send the request to.  
* `(string|null) $idLocation`
: The location of the id in the response body.  
* `(string|null) $method`
: The request method PUT or POST.  

**Return Values**

`array`

> The response body of the outgoing call, or an empty array on error.


**Throws Exceptions**


`\Exception`


<hr />

