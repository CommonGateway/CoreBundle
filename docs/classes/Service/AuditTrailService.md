# CommonGateway\CoreBundle\Service\AuditTrailService  

This service manages the creation of Audit Trails.





## Methods

| Name | Description |
|------|-------------|
|[__construct](#audittrailservice__construct)||
|[createAuditTrail](#audittrailservicecreateaudittrail)|Creates an Audit Trail for the given Object and the current user.|




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

Creates an Audit Trail for the given Object and the current user. 

 

**Parameters**

* `(\ObjectEntity) $object`
: An ObjectEntity to create an Audit Trail for.  
* `(array) $config`
: Extra configuration that should contain an 'action' (LIST, RETRIEVE, CREATE, UPDATE, PARTIAL_UPDATE, DELETE), a 'result' (HTTP status code) and if needed a 'new' and 'old' body.  

**Return Values**

`\AuditTrail|null`

> The created Audit Trail


<hr />

