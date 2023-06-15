# CommonGateway\CoreBundle\Service\CacheService  

Service to call external sources.

This service provides a guzzle wrapper to work with sources in the common gateway.  





## Methods

| Name | Description |
|------|-------------|
|[__construct](#cacheservice__construct)||
|[cacheEndpoint](#cacheservicecacheendpoint)|Put a single endpoint into the cache.|
|[cacheObject](#cacheservicecacheobject)|Put a single object into the cache.|
|[cacheShema](#cacheservicecacheshema)|Put a single schema into the cache.|
|[cleanup](#cacheservicecleanup)|Remove non-existing items from the cache.|
|[getEndpoint](#cacheservicegetendpoint)|Get a single endpoint from the cache.|
|[getEndpoints](#cacheservicegetendpoints)||
|[getObject](#cacheservicegetobject)|Get a single object from the cache.|
|[getSchema](#cacheservicegetschema)|Get a single schema from the cache.|
|[handleResultPagination](#cacheservicehandleresultpagination)|Adds pagination variables to an array with the results we found with searchObjects().|
|[removeEndpoint](#cacheserviceremoveendpoint)|Removes an endpoint from the cache.|
|[removeObject](#cacheserviceremoveobject)|Removes an object from the cache.|
|[removeSchema](#cacheserviceremoveschema)|Removes an Schema from the cache.|
|[retrieveObjectsFromCache](#cacheserviceretrieveobjectsfromcache)||
|[searchObjects](#cacheservicesearchobjects)|Searches the object store for objects containing the search string.|
|[setPagination](#cacheservicesetpagination)|Decides the pagination values.|
|[setStyle](#cacheservicesetstyle)|Set symfony style in order to output to the console.|
|[warmup](#cacheservicewarmup)|Throws all available objects into the cache.|




### CacheService::__construct  

**Description**

```php
 __construct (void)
```

 

 

**Parameters**

`This function has no parameters.`

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

**Return Values**

`\Entity`




<hr />


### CacheService::cleanup  

**Description**

```php
public cleanup (void)
```

Remove non-existing items from the cache. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### CacheService::getEndpoint  

**Description**

```php
public getEndpoint (\Uuid $identification)
```

Get a single endpoint from the cache. 

 

**Parameters**

* `(\Uuid) $identification`

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
public getObject (string $identification)
```

Get a single object from the cache. 

 

**Parameters**

* `(string) $identification`

**Return Values**

`array|null`




<hr />


### CacheService::getSchema  

**Description**

```php
public getSchema (\Uuid $identification)
```

Get a single schema from the cache. 

 

**Parameters**

* `(\Uuid) $identification`

**Return Values**

`array|null`




<hr />


### CacheService::handleResultPagination  

**Description**

```php
public handleResultPagination (array $filter, array $results, int $total)
```

Adds pagination variables to an array with the results we found with searchObjects(). 

 

**Parameters**

* `(array) $filter`
* `(array) $results`
* `(int) $total`

**Return Values**

`array`

> the result with pagination.


<hr />


### CacheService::removeEndpoint  

**Description**

```php
public removeEndpoint (\Endpoint $endpoint)
```

Removes an endpoint from the cache. 

 

**Parameters**

* `(\Endpoint) $endpoint`

**Return Values**

`void`




<hr />


### CacheService::removeObject  

**Description**

```php
public removeObject (\ObjectEntity $objectEntity)
```

Removes an object from the cache. 

 

**Parameters**

* `(\ObjectEntity) $objectEntity`

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

**Return Values**

`void`




<hr />


### CacheService::retrieveObjectsFromCache  

**Description**

```php
 retrieveObjectsFromCache (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### CacheService::searchObjects  

**Description**

```php
public searchObjects (string|null $search, array $filter, array $entities)
```

Searches the object store for objects containing the search string. 

 

**Parameters**

* `(string|null) $search`
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


### CacheService::setStyle  

**Description**

```php
public setStyle (\SymfonyStyle $io)
```

Set symfony style in order to output to the console. 

 

**Parameters**

* `(\SymfonyStyle) $io`

**Return Values**

`self`




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

