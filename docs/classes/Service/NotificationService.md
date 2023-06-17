# CommonGateway\CoreBundle\Service\NotificationService  

Handles incoming notification api-calls by finding or creating a synchronization and synchronizing an object.





## Methods

| Name | Description |
|------|-------------|
|[__construct](#notificationservice__construct)|The constructor sets al needed variables.|
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

