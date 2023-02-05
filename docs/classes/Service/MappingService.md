# CommonGateway\CoreBundle\Service\MappingService  

The mapping service handles the mapping (or transformation) of array A (input) to array B (output).

More information on how to write your own mappings can be found at [/docs/mapping.md](/docs/mapping.md).  





## Methods

| Name | Description |
|------|-------------|
|[__construct](#mappingservice__construct)|Setting up the base class with required services.|
|[cast](#mappingservicecast)|Cast values to a specific type.|
|[mapping](#mappingservicemapping)|Maps (transforms) an array (input) to a different array (output).|




### MappingService::__construct  

**Description**

```php
public __construct (\Environment $twig, \SessionInterface $session, \LoggerInterface $mappingLogger)
```

Setting up the base class with required services. 

 

**Parameters**

* `(\Environment) $twig`
* `(\SessionInterface) $session`
* `(\LoggerInterface) $mappingLogger`

**Return Values**

`void`


<hr />


### MappingService::cast  

**Description**

```php
public cast (\Mapping $mappingObject, \Dot $dotArray)
```

Cast values to a specific type. 

 

**Parameters**

* `(\Mapping) $mappingObject`
: The mapping object used to map  
* `(\Dot) $dotArray`
: The current status of the mappings as a dot array  

**Return Values**

`\Dot`

> The status of the mapping afther casting has been applied


<hr />


### MappingService::mapping  

**Description**

```php
public mapping (\Mapping $mappingObject, array $input)
```

Maps (transforms) an array (input) to a different array (output). 

 

**Parameters**

* `(\Mapping) $mappingObject`
: The mapping object that forms the recipe for the mapping  
* `(array) $input`
: The array that need to be mapped (transformed) otherwise known as input  

**Return Values**

`array`

> The result (output) of the mapping process


<hr />

