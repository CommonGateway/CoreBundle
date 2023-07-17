# CommonGateway\CoreBundle\Service\AuditTrailService  

This service manages the creation of Audit Trails.





## Methods

| Name | Description |
|------|-------------|
|[__construct](#audittrailservice__construct)||
|[createAuditTrail](#audittrailservicecreateaudittrail)|Passes the result of prePersist to preUpdate.|
|[test](#audittrailservicetest)|Test function TODO: REMOVE THIS!!!|




### AuditTrailService::__construct  

**Description**

```php
 __construct (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### AuditTrailService::createAuditTrail  

**Description**

```php
public createAuditTrail (\ObjectEntity $object, array $config)
```

Passes the result of prePersist to preUpdate. 

 

**Parameters**

* `(\ObjectEntity) $object`
* `(array) $config`

**Return Values**

`\AuditTrail|null`




<hr />


### AuditTrailService::test  

**Description**

```php
public test (void)
```

Test function TODO: REMOVE THIS!!! 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`array`




<hr />

