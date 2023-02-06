# CommonGateway\CoreBundle\Service\CacheService  

Service to call external sources.

This service provides a guzzle wrapper to work with sources in the common gateway.  





## Methods

| Name | Description |
|------|-------------|
|[__construct](#cacheservice__construct)|Setting up the base class with required services.|
|[cacheEndpoint](#cacheservicecacheendpoint)|Put a single endpoint into the cache.|
|[cacheObject](#cacheservicecacheobject)|Put a single object into the cache.|
|[cacheShema](#cacheservicecacheshema)|Put a single schema into the cache.|
|[cleanup](#cacheservicecleanup)|Remove non-exisitng items from the cashe.|
|[getEndpoint](#cacheservicegetendpoint)|Get a single endpoint from the cache.|
|[getEndpoints](#cacheservicegetendpoints)||
|[getObject](#cacheservicegetobject)|Get a single object from the cache.|
|[getSchema](#cacheservicegetschema)|Get a single schema from the cache.|
|[removeEndpoint](#cacheserviceremoveendpoint)|Removes an endpoint from the cache.|
|[removeObject](#cacheserviceremoveobject)|Removes an object from the cache.|
|[removeSchema](#cacheserviceremoveschema)|Removes an Schema from the cache.|
|[searchObjects](#cacheservicesearchobjects)|Searches the object store for objects containing the search string.|
|[setPagination](#cacheservicesetpagination)|Decides the pagination values.|
|[warmup](#cacheservicewarmup)|Throws all available objects into the cache.|




### CacheService::__construct  

**Description**

```php
public __construct (\EntityManagerInterface $entityManager, \CacheInterface $cache, \ParameterBagInterface $parameters, \SerializerInterface $serializer, \LoggerInterface $cacheLogger)
```

Setting up the base class with required services. 

 

**Parameters**

* `(\EntityManagerInterface) $entityManager`
: The EntityManagerInterface  
* `(\CacheInterface) $cache`
: The CacheInterface  
* `(\ParameterBagInterface) $parameters`
: The ParameterBagInterface  
* `(\SerializerInterface) $serializer`
: The SerializerInterface  
* `(\LoggerInterface) $cacheLogger`
: The LoggerInterface  

**Return Values**

`void`


<hr />


### CacheService::cacheEndpoint  

**Description**

```php
public cacheEndpoint (\Endpoint $endpoint)
```

Put a single endpoint into the cache. 

 

**Parameters**

* `(\Endpoint) $endpoint`
: The endpoint  

**Return Values**

`\Endpoint`




<hr />


### CacheService::cacheObject  

**Description**

```php
public cacheObject (\ObjectEntity $objectEntity)
```

Put a single object into the cache. 

 

**Parameters**

* `(\ObjectEntity) $objectEntity`
: ObjectEntity  

**Return Values**

`\ObjectEntity`




<hr />


### CacheService::cacheShema  

**Description**

```php
public cacheShema (\Entity $entity)
```

Put a single schema into the cache. 

 

**Parameters**

* `(\Entity) $entity`
: The Entity  

**Return Values**

`\Entity`




<hr />


### CacheService::cleanup  

**Description**

```php
public cleanup (void)
```

Remove non-exisitng items from the cashe. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`




<hr />


### CacheService::getEndpoint  

**Description**

```php
public getEndpoint (\Uuid $id)
```

Get a single endpoint from the cache. 

 

**Parameters**

* `(\Uuid) $id`
: The uuid of the endpoint  

**Return Values**

`array|null`




<hr />


### CacheService::getEndpoints  

**Description**

```php
 getEndpoints (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### CacheService::getObject  

**Description**

```php
public getObject (string $id)
```

Get a single object from the cache. 

 

**Parameters**

* `(string) $id`
: The id of the object  

**Return Values**

`array|null`




<hr />


### CacheService::getSchema  

**Description**

```php
public getSchema (\Uuid $id)
```

Get a single schema from the cache. 

 

**Parameters**

* `(\Uuid) $id`
: The uuid of the schema  

**Return Values**

`array|null`




<hr />


### CacheService::removeEndpoint  

**Description**

```php
public removeEndpoint (\Endpoint $endpoint)
```

Removes an endpoint from the cache. 

 

**Parameters**

* `(\Endpoint) $endpoint`
: The endpoint  

**Return Values**

`void`




<hr />


### CacheService::removeObject  

**Description**

```php
public removeObject (\ObjectEntity $object)
```

Removes an object from the cache. 

 

**Parameters**

* `(\ObjectEntity) $object`
: ObjectEntity  

**Return Values**

`void`




<hr />


### CacheService::removeSchema  

**Description**

```php
public removeSchema (\Entity $entity)
```

Removes an Schema from the cache. 

 

**Parameters**

* `(\Entity) $entity`
: The entity  

**Return Values**

`void`




<hr />


### CacheService::searchObjects  

**Description**

```php
public searchObjects (string $search, array $filter, array $entities)
```

Searches the object store for objects containing the search string. 

 

**Parameters**

* `(string) $search`
: a string to search for within the given context  
* `(array) $filter`
: an array of dot.notation filters for wich to search with  
* `(array) $entities`
: schemas to limit te search to  

**Return Values**

`array|null`




**Throws Exceptions**


`\Exception`


<hr />


### CacheService::setPagination  

**Description**

```php
public setPagination (int $limit, int $start, array $filters)
```

Decides the pagination values. 

 

**Parameters**

* `(int) $limit`
: The resulting limit  
* `(int) $start`
: The resulting start value  
* `(array) $filters`
: The filters  

**Return Values**

`array`




<hr />


### CacheService::warmup  

**Description**

```php
public warmup (void)
```

Throws all available objects into the cache. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`




<hr />

