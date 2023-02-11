# CommonGateway\CoreBundle\Service\InstallationService  







## Methods

| Name | Description |
|------|-------------|
|[__construct](#installationservice__construct)||
|[addToObjects](#installationserviceaddtoobjects)|Adds an object to the objects stack if it is vallid|
|[composerupdate](#installationservicecomposerupdate)|Updates all commonground bundles on the common gateway installation.|
|[handleInstaller](#installationservicehandleinstaller)|Specifcially handles the installation file|
|[handleObject](#installationservicehandleobject)|Create an object bases on an type and a schema (the object as an array)|
|[install](#installationserviceinstall)|Installs the files from a bundle|
|[readDirectory](#installationservicereaddirectory)|This function read a folder to find other folders or json objects|
|[readfile](#installationservicereadfile)|This function read a folder to find other folders or json objects|
|[validateObjects](#installationservicevalidateobjects)|Validates the objects in the EAV setup.|
|[validateSchemas](#installationservicevalidateschemas)|Validates the schemas in the EAV setup.|
|[validateValues](#installationservicevalidatevalues)|Validates the objects in the EAV setup.|




### InstallationService::__construct  

**Description**

```php
 __construct (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### InstallationService::addToObjects  

**Description**

```php
public addToObjects (array $schema)
```

Adds an object to the objects stack if it is vallid 

 

**Parameters**

* `(array) $schema`
: The schema  

**Return Values**

`bool|array`

> The file contents, or false if content could not be establisched


<hr />


### InstallationService::composerupdate  

**Description**

```php
public composerupdate (array $config)
```

Updates all commonground bundles on the common gateway installation. 

 

**Parameters**

* `(array) $config`
: The (optional) configuration  

**Return Values**

`int`




<hr />


### InstallationService::handleInstaller  

**Description**

```php
public handleInstaller ( $file)
```

Specifcially handles the installation file 

 

**Parameters**

* `() $file`
: The installation file  

**Return Values**

`bool`




<hr />


### InstallationService::handleObject  

**Description**

```php
public handleObject (string $type, array $schema)
```

Create an object bases on an type and a schema (the object as an array) 

This function breaks complexity rules, but since a switch is the most effective way of doing it a design decicion was made to allow it 

**Parameters**

* `(string) $type`
: The type of the object  
* `(array) $schema`
: The object as an array  

**Return Values**

`bool|object`




<hr />


### InstallationService::install  

**Description**

```php
public install (string $bundle, array $config)
```

Installs the files from a bundle 

 

**Parameters**

* `(string) $bundle`
: The bundle  
* `(array) $config`
: Optional config  

**Return Values**

`bool`

> The result of the installation


<hr />


### InstallationService::readDirectory  

**Description**

```php
public readDirectory (string $location)
```

This function read a folder to find other folders or json objects 

 

**Parameters**

* `(string) $location`
: The location of the folder  

**Return Values**

`bool`

> Whether or not the function was succefully executed


<hr />


### InstallationService::readfile  

**Description**

```php
public readfile (\File $file)
```

This function read a folder to find other folders or json objects 

 

**Parameters**

* `(\File) $file`
: The file location  

**Return Values**

`bool|array`

> The file contents, or false if content could not be establisched


<hr />


### InstallationService::validateObjects  

**Description**

```php
public validateObjects (void)
```

Validates the objects in the EAV setup. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`




<hr />


### InstallationService::validateSchemas  

**Description**

```php
public validateSchemas (void)
```

Validates the schemas in the EAV setup. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`




<hr />


### InstallationService::validateValues  

**Description**

```php
public validateValues (void)
```

Validates the objects in the EAV setup. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`




<hr />

