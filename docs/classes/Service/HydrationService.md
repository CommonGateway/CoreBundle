# CommonGateway\CoreBundle\Service\HydrationService  

This class hydrates objects and sets synchronisations for (child/sub-)objects if applicable.





## Methods

| Name | Description |
|------|-------------|
|[__construct](#hydrationservice__construct)|The constructor of the service.|
|[searchAndReplaceSynchronizations](#hydrationservicesearchandreplacesynchronizations)|Recursively loop through an object, check if a synchronisation exists or create one (if necessary).|




### HydrationService::__construct  

**Description**

```php
public __construct (\SynchronizationService $syncService, \EntityManagerInterface $entityManager)
```

The constructor of the service. 

 

**Parameters**

* `(\SynchronizationService) $syncService`
* `(\EntityManagerInterface) $entityManager`

**Return Values**

`void`


<hr />


### HydrationService::searchAndReplaceSynchronizations  

**Description**

```php
public searchAndReplaceSynchronizations (array $object, \Source $source, \Entity $entity, bool $unsafeHydrate, bool $returnSynchronization)
```

Recursively loop through an object, check if a synchronisation exists or create one (if necessary). 

 

**Parameters**

* `(array) $object`
: The object array ready for hydration.  
* `(\Source) $source`
: The source the objects need to be connected to.  
* `(\Entity) $entity`
: The entity of the (sub)object.  
* `(bool) $unsafeHydrate`
: If we should hydrate unsafely or not (when true it will unset non given properties).  
* `(bool) $returnSynchronization`
: If we should return the Synchronization of the main object instead of the ObjectEntity/array.  

**Return Values**

`array|\ObjectEntity|\Synchronization`

> The resulting object, Synchronization or array.


<hr />

