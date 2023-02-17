# CommonGateway\CoreBundle\Service\InstallationService  

The installation service is used to install plugins (or actually symfony bundles) on the gateway.





## Methods

| Name | Description |
|------|-------------|
|[__construct](#installationservice__construct)||
|[install](#installationserviceinstall)|Installs the files from a bundle.|
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
: Optional config (ignored on this function)  

**Return Values**

`bool`

> The result of the installation


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

