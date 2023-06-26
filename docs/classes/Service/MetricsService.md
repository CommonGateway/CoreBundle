# CommonGateway\CoreBundle\Service\MetricsService  

Creates arrays for prometheus.





## Methods

| Name | Description |
|------|-------------|
|[__construct](#metricsservice__construct)|The constructor sets al needed variables.|
|[getAll](#metricsservicegetall)|Search for a given term.|
|[getErrors](#metricsservicegeterrors)|Get metrics concerning errors.|
|[getObjects](#metricsservicegetobjects)|Get metrics concerning objects.|
|[getPlugins](#metricsservicegetplugins)|Get metrics concerning plugins.|




### MetricsService::__construct  

**Description**

```php
public __construct (\ComposerService $composerService, \EntityManagerInterface $entityManager, \ParameterBagInterface $parameters, \Client|null $client)
```

The constructor sets al needed variables. 

 

**Parameters**

* `(\ComposerService) $composerService`
: The Composer service  
* `(\EntityManagerInterface) $entityManager`
: The entity manager  
* `(\ParameterBagInterface) $parameters`
: The Parameter bag  
* `(\Client|null) $client`
: The mongodb client  

**Return Values**

`void`


<hr />


### MetricsService::getAll  

**Description**

```php
public getAll (void)
```

Search for a given term. 

See https://getcomposer.org/doc/03-cli.md#show-info for a full list of al options and there function 

**Parameters**

`This function has no parameters.`

**Return Values**

`array`




<hr />


### MetricsService::getErrors  

**Description**

```php
public getErrors (void)
```

Get metrics concerning errors. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`array`




<hr />


### MetricsService::getObjects  

**Description**

```php
public getObjects (void)
```

Get metrics concerning objects. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`array`




<hr />


### MetricsService::getPlugins  

**Description**

```php
public getPlugins (void)
```

Get metrics concerning plugins. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`array`




<hr />

