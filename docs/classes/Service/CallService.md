# CommonGateway\CoreBundle\Service\CallService  

Service to call external sources.

This service provides a guzzle wrapper to work with sources in the common gateway.  





## Methods

| Name | Description |
|------|-------------|
|[__construct](#callservice__construct)||
|[call](#callservicecall)|Calls a source according to given configuration.|
|[decodeResponse](#callservicedecoderesponse)|Decodes a response based on the source it belongs to.|
|[getCertificate](#callservicegetcertificate)|Writes the certificate and ssl keys to disk, returns the filenames.|
|[removeFiles](#callserviceremovefiles)|Removes certificates and private keys from disk if they are not necessary anymore.|




### CallService::__construct  

**Description**

```php
 __construct (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### CallService::call  

**Description**

```php
public call (\Source $source, string $endpoint, string $method, array $config, bool $asynchronous)
```

Calls a source according to given configuration. 

 

**Parameters**

* `(\Source) $source`
: The source to call  
* `(string) $endpoint`
: The endpoint on the source to call  
* `(string) $method`
: The method on which to call the source  
* `(array) $config`
: The additional configuration to call the source  
* `(bool) $asynchronous`
: Whether or not to call the source asynchronously  

**Return Values**

`\Response`




<hr />


### CallService::decodeResponse  

**Description**

```php
public decodeResponse (\Source $source, \Response $response)
```

Decodes a response based on the source it belongs to. 

 

**Parameters**

* `(\Source) $source`
: The source that has been called  
* `(\Response) $response`
: The response to decode  

**Return Values**

`array`

> The decoded response


**Throws Exceptions**


`\Exception`
> Thrown if the response does not fit any supported content type

<hr />


### CallService::getCertificate  

**Description**

```php
public getCertificate (array $config)
```

Writes the certificate and ssl keys to disk, returns the filenames. 

 

**Parameters**

* `(array) $config`
: The configuration as stored in the source  

**Return Values**

`array`

> The overrides on the configuration with filenames instead of certificate contents


<hr />


### CallService::removeFiles  

**Description**

```php
public removeFiles (array $config)
```

Removes certificates and private keys from disk if they are not necessary anymore. 

 

**Parameters**

* `(array) $config`
: The configuration with filenames  

**Return Values**

`void`




<hr />

