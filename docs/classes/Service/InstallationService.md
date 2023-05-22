# CommonGateway\CoreBundle\Service\InstallationService  

The installation service is used to install plugins (or actually symfony bundles) on the gateway.

This class breaks complexity, methods and coupling rules. This could be solved by deviding the class into smaller classes but that would deminisch the readability of the code as a whole. All the code in this class is only used in an installation context, and it makes more sense to keep it together. Therefore, a design decision was made to keep al this code in one class.  





## Methods

| Name | Description |
|------|-------------|
|[__construct](#installationservice__construct)|The constructor sets al needed variables|
|[addActionConfiguration](#installationserviceaddactionconfiguration)|This function creates default configuration for the action.|
|[install](#installationserviceinstall)|Installs the files from a bundle.|
|[overrideConfig](#installationserviceoverrideconfig)|Overrides the default configuration of an Action. Will also set entity and source to id if a reference is given.|
|[update](#installationserviceupdate)|Updates all commonground bundles on the common gateway installation.|




### InstallationService::__construct  

**Description**

```php
public __construct (\ComposerService $composerService, \EntityManagerInterface $entityManager, \Kernel $kernel, \LoggerInterface $installationLogger, \SchemaService $schemaService, \CacheService $cacheService)
```

The constructor sets al needed variables 

 

**Parameters**

* `(\ComposerService) $composerService`
: The Composer service  
* `(\EntityManagerInterface) $entityManager`
: The entity manager  
* `(\Kernel) $kernel`
: The kernel  
* `(\LoggerInterface) $installationLogger`
: The logger for the installation channel.  
* `(\SchemaService) $schemaService`
: The schema service  
* `(\CacheService) $cacheService`
: The cache service  

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
: The bundle.  
* `(array) $config`
: Optional config.  

**Return Values**

`bool`

> The result of the installation.


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
public update (array $config, \SymfonyStyle|null $io)
```

Updates all commonground bundles on the common gateway installation. 

This functions serves as the jump of point for the `commengateway:plugins:update` command 

**Parameters**

* `(array) $config`
: The (optional) configuration  
* `(\SymfonyStyle|null) $io`
: In case we run update from the :initialize command and want cache:warmup to show IO messages.  

**Return Values**

`int`




**Throws Exceptions**


`\Exception`


<hr />

