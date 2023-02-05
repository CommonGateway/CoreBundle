# CommonGateway\CoreBundle\Service\InstallationService  







## Methods

| Name | Description |
|------|-------------|
|[__construct](#installationservice__construct)||
|[composerupdate](#installationservicecomposerupdate)|Updates all commonground bundles on the common gateway installation.|
|[handleAction](#installationservicehandleaction)||
|[handleData](#installationservicehandledata)||
|[handleInstaller](#installationservicehandleinstaller)||
|[handleMapping](#installationservicehandlemapping)||
|[handleSchema](#installationservicehandleschema)||
|[install](#installationserviceinstall)|Performs installation actions on a common Gataway bundle.|
|[setStyle](#installationservicesetstyle)|Set symfony style in order to output to the console.|
|[uninstall](#installationserviceuninstall)||
|[update](#installationserviceupdate)||
|[validateJsonMapping](#installationservicevalidatejsonmapping)|Perform a very basic check to see if a schema file is a valid json-schema file.|
|[validateJsonSchema](#installationservicevalidatejsonschema)|Performce a very basic check to see if a schema file is a valid json-schema file.|
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


### InstallationService::composerupdate  

**Description**

```php
public composerupdate (array $config)
```

Updates all commonground bundles on the common gateway installation. 

 

**Parameters**

* `(array) $config`

**Return Values**

`int`




<hr />


### InstallationService::handleAction  

**Description**

```php
 handleAction (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### InstallationService::handleData  

**Description**

```php
 handleData (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### InstallationService::handleInstaller  

**Description**

```php
 handleInstaller (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### InstallationService::handleMapping  

**Description**

```php
 handleMapping (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### InstallationService::handleSchema  

**Description**

```php
 handleSchema (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### InstallationService::install  

**Description**

```php
public install (string $bundle, array $config)
```

Performs installation actions on a common Gataway bundle. 

 

**Parameters**

* `(string) $bundle`
: The bundle name that you want to install  
* `(array) $config`
: Optional configuration  

**Return Values**

`int`




<hr />


### InstallationService::setStyle  

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


### InstallationService::uninstall  

**Description**

```php
 uninstall (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### InstallationService::update  

**Description**

```php
 update (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### InstallationService::validateJsonMapping  

**Description**

```php
public validateJsonMapping (array $schema)
```

Perform a very basic check to see if a schema file is a valid json-schema file. 

 

**Parameters**

* `(array) $schema`

**Return Values**

`bool`




<hr />


### InstallationService::validateJsonSchema  

**Description**

```php
public validateJsonSchema (array $schema)
```

Performce a very basic check to see if a schema file is a valid json-schema file. 

 

**Parameters**

* `(array) $schema`

**Return Values**

`bool`




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

