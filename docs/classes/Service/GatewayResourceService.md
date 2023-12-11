# CommonGateway\CoreBundle\Service\GatewayResourceService  







## Methods

| Name | Description |
|------|-------------|
|[__construct](#gatewayresourceservice__construct)|The constructor sets al needed variables.|
|[findSourcesForUrl](#gatewayresourceservicefindsourcesforurl)|Find all sources that have a location that match the specified url.|
|[getAction](#gatewayresourceservicegetaction)|Get an action by reference.|
|[getEndpoint](#gatewayresourceservicegetendpoint)|Get a endpoint by reference.|
|[getMapping](#gatewayresourceservicegetmapping)|Get a mapping by reference.|
|[getObject](#gatewayresourceservicegetobject)|Get a object by identifier.|
|[getSchema](#gatewayresourceservicegetschema)|Get a schema by reference.|
|[getSource](#gatewayresourceservicegetsource)|Get a source by reference.|




### GatewayResourceService::__construct  

**Description**

```php
public __construct (\EntityManagerInterface $entityManager, \LoggerInterface $pluginLogger)
```

The constructor sets al needed variables. 

 

**Parameters**

* `(\EntityManagerInterface) $entityManager`
* `(\LoggerInterface) $pluginLogger`

**Return Values**

`void`


<hr />


### GatewayResourceService::findSourcesForUrl  

**Description**

```php
public findSourcesForUrl (string $url, string $pluginName)
```

Find all sources that have a location that match the specified url. 

Todo: we should use a mongoDB filter instead of this, sources should exist in MongoDB.  
Todo: for future reference, there is a function with very similar BL in the CustomerInteractionBundle->CustomerInteractionService->getSource(), we could/should merge that code with this function. 

**Parameters**

* `(string) $url`
: The url we are trying to find a matching source for.  
* `(string) $pluginName`
: The name of the plugin that requests these resources.  

**Return Values**

`array|null`




<hr />


### GatewayResourceService::getAction  

**Description**

```php
public getAction (string $reference, string $pluginName)
```

Get an action by reference. 

 

**Parameters**

* `(string) $reference`
: The reference to look for  
* `(string) $pluginName`
: The name of the plugin that requests the resource.  

**Return Values**

`\Action|null`




<hr />


### GatewayResourceService::getEndpoint  

**Description**

```php
public getEndpoint (string $reference, string $pluginName)
```

Get a endpoint by reference. 

 

**Parameters**

* `(string) $reference`
: The location to look for.  
* `(string) $pluginName`
: The name of the plugin that requests the resource.  

**Return Values**

`\Endpoint|null`




<hr />


### GatewayResourceService::getMapping  

**Description**

```php
public getMapping (string $reference, string $pluginName)
```

Get a mapping by reference. 

 

**Parameters**

* `(string) $reference`
: The reference to look for.  
* `(string) $pluginName`
: The name of the plugin that requests the resource.  

**Return Values**

`\Mapping|null`




<hr />


### GatewayResourceService::getObject  

**Description**

```php
public getObject (string $id)
```

Get a object by identifier. 

 

**Parameters**

* `(string) $id`
: The id to look for.  

**Return Values**

`\ObjectEntity|null`




<hr />


### GatewayResourceService::getSchema  

**Description**

```php
public getSchema (string $reference, string $pluginName)
```

Get a schema by reference. 

 

**Parameters**

* `(string) $reference`
: The reference to look for.  
* `(string) $pluginName`
: The name of the plugin that requests the resource.  

**Return Values**

`\Entity|null`




<hr />


### GatewayResourceService::getSource  

**Description**

```php
public getSource (string $reference, string $pluginName)
```

Get a source by reference. 

 

**Parameters**

* `(string) $reference`
: The reference to look for.  
* `(string) $pluginName`
: The name of the plugin that requests the resource.  

**Return Values**

`\Source|null`




<hr />

