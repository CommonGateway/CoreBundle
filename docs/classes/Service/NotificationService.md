# CommonGateway\CoreBundle\Service\NotificationService  

Handles incoming notification api-calls by finding or creating a synchronization and synchronizing an object.





## Methods

| Name | Description |
|------|-------------|
|[__construct](#notificationservice__construct)|The constructor sets al needed variables.|
|[findSource](#notificationservicefindsource)|Tries to find a source using the url of the object a notification was created for.|
|[notificationHandler](#notificationservicenotificationhandler)|Handles incoming notification api-call and is responsible for generating a response.|




### NotificationService::__construct  

**Description**

```php
public __construct (\EntityManagerInterface $entityManager, \LoggerInterface $notificationLogger, \SynchronizationService $syncService, \GatewayResourceService $resourceService)
```

The constructor sets al needed variables. 

 

**Parameters**

* `(\EntityManagerInterface) $entityManager`
: The EntityManager.  
* `(\LoggerInterface) $notificationLogger`
: The notification logger.  
* `(\SynchronizationService) $syncService`
: The SynchronizationService.  
* `(\GatewayResourceService) $resourceService`
: The GatewayResourceService.  

**Return Values**

`void`


<hr />


### NotificationService::findSource  

**Description**

```php
public findSource (string $url)
```

Tries to find a source using the url of the object a notification was created for. 

 

**Parameters**

* `(string) $url`
: The url we are trying to find a matching Source for.  

**Return Values**

`\Source`

> The Source we found.


**Throws Exceptions**


`\Exception`
> If we did not find one Source we throw an exception.

<hr />


### NotificationService::notificationHandler  

**Description**

```php
public notificationHandler (array $data, array $configuration)
```

Handles incoming notification api-call and is responsible for generating a response. 

 

**Parameters**

* `(array) $data`
: The data from the call  
* `(array) $configuration`
: The configuration from the call  

**Return Values**

`array`

> A handler must ALWAYS return an array


<hr />

