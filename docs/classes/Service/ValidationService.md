# CommonGateway\CoreBundle\Service\ValidationService  







## Methods

| Name | Description |
|------|-------------|
|[__construct](#validationservice__construct)|The constructor sets al needed variables.|
|[validateData](#validationservicevalidatedata)|Validates an array with data using the Validator for the given Entity.|




### ValidationService::__construct  

**Description**

```php
public __construct (\CacheInterface $cache)
```

The constructor sets al needed variables. 

 

**Parameters**

* `(\CacheInterface) $cache`

**Return Values**

`void`


<hr />


### ValidationService::validateData  

**Description**

```php
public validateData (array $data, \Entity $entity, string $method)
```

Validates an array with data using the Validator for the given Entity. 

 

**Parameters**

* `(array) $data`
: The data to validate.  
* `(\Entity) $entity`
: The entity used for validation.  
* `(string) $method`
: used to be able to use different validations for different methods.  

**Return Values**

`string[]|void`




**Throws Exceptions**


`\CacheException|\GatewayException|\InvalidArgumentException|\ComponentException`


<hr />

