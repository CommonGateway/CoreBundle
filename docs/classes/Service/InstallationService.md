# CommonGateway\CoreBundle\Service\InstallationService  

The installation service is used to install plugins (or actually symfony bundles) on the gateway.

This class breacks complixity,methods and coupling rules. This could be solved by devidng the class into smaller classes but that would deminisch the readbilly of the code as a whole. All the code in this class is only used in an installation context and it makes more sence to keep it together. Therefore a design decicion was made to keep al this code in one class.  





## Methods

| Name | Description |
|------|-------------|
|[__construct](#installationservice__construct)||
|[addActionConfiguration](#installationserviceaddactionconfiguration)|This function creates default configuration for the action.|
|[install](#installationserviceinstall)|Installs the files from a bundle.|
|[overrideConfig](#installationserviceoverrideconfig)|Overrides the default configuration of an Action. Will also set entity and source to id if a reference is given.|
|[update](#installationserviceupdate)|Updates all commonground bundles on the common gateway installation.|




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


### InstallationService::addActionConfiguration  

**Description**

```php
public addActionConfiguration (mixed $actionHandler)
```

This function creates default configuration for the action. 

 

**Parameters**

* `(mixed) $actionHandler`
: The actionHandler for witch the default configuration is set.  

**Return Values**

`array`




<hr />


### InstallationService::install  

**Description**

```php
public install (string $bundle, array $config)
```

Installs the files from a bundle. 

Based on the default action handler so schould supoprt a config parrameter even if we do not use it 

**Parameters**

* `(string) $bundle`
: The bundle  
* `(array) $config`
: Optional config (ignored on this function) //todo: remove this parameter?  

**Return Values**

`bool`

> The result of the installation


**Throws Exceptions**


`\Exception`


<hr />


### InstallationService::overrideConfig  

**Description**

```php
public overrideConfig (array $defaultConfig, array $overrides)
```

Overrides the default configuration of an Action. Will also set entity and source to id if a reference is given. 

 

**Parameters**

* `(array) $defaultConfig`
* `(array) $overrides`

**Return Values**

`array`




<hr />


### InstallationService::update  

**Description**

```php
public update (array $config)
```

Updates all commonground bundles on the common gateway installation. 

This functions serves as the jump of point for the `commengateway:plugins:update` command 

**Parameters**

* `(array) $config`
: The (optional) configuration  

**Return Values**

`int`




<hr />

