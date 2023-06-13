# CommonGateway\CoreBundle\Service\FileSystemHandleService  







## Methods

| Name | Description |
|------|-------------|
|[__construct](#filesystemhandleservice__construct)|The class constructor.|
|[call](#filesystemhandleservicecall)|Calls a Filesystem source according to given configuration.|
|[decodeFile](#filesystemhandleservicedecodefile)|Decodes a file content using a given format, default = json_decode.|
|[getContentFromAllFiles](#filesystemhandleservicegetcontentfromallfiles)|Returns the contents of all files in a filesystem.|
|[getFileContents](#filesystemhandleservicegetfilecontents)|Gets the content of a file from a specific file on a filesystem.|




### FileSystemHandleService::__construct  

**Description**

```php
public __construct (\EntityManagerInterface $entityManager, \MappingService $mappingService, \LoggerInterface $callLogger, \FileSystemCreateService $fscService)
```

The class constructor. 

 

**Parameters**

* `(\EntityManagerInterface) $entityManager`
: The entity manager.  
* `(\MappingService) $mappingService`
: The mapping service.  
* `(\LoggerInterface) $callLogger`
: The call logger.  
* `(\FileSystemCreateService) $fscService`
: The file system create service  

**Return Values**

`void`


<hr />


### FileSystemHandleService::call  

**Description**

```php
public call (\Source $source, string $location, array $config)
```

Calls a Filesystem source according to given configuration. 

 

**Parameters**

* `(\Source) $source`
: The Filesystem source to call.  
* `(string) $location`
: The (file) location on the Filesystem source to call.  
* `(array) $config`
: The additional configuration to call the Filesystem source.  

**Return Values**

`array`

> The decoded response array of the call.


<hr />


### FileSystemHandleService::decodeFile  

**Description**

```php
public decodeFile (string|null $content, string $location, string|null $format)
```

Decodes a file content using a given format, default = json_decode. 

 

**Parameters**

* `(string|null) $content`
: The content to decode.  
* `(string) $location`
: The (file) location to get a format from if no format is given.  
* `(string|null) $format`
: The format to use when decoding the file content.  

**Return Values**

`array`

> The decoded file content.


**Throws Exceptions**


`\Exception`


<hr />


### FileSystemHandleService::getContentFromAllFiles  

**Description**

```php
public getContentFromAllFiles (\Filesystem $filesystem)
```

Returns the contents of all files in a filesystem. 

 

**Parameters**

* `(\Filesystem) $filesystem`
: The local filesystem.  

**Return Values**

`array`




**Throws Exceptions**


`\Exception`


<hr />


### FileSystemHandleService::getFileContents  

**Description**

```php
public getFileContents (\Filesystem $filesystem, string $location)
```

Gets the content of a file from a specific file on a filesystem. 

 

**Parameters**

* `(\Filesystem) $filesystem`
: The filesystem to get a file from.  
* `(string) $location`
: The location of the file to get.  

**Return Values**

`string|null`

> The file content or null.


**Throws Exceptions**


`\FilesystemException`


<hr />

