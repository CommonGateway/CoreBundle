# CommonGateway\CoreBundle\Service\ReadUnreadService  

This service manages reading if an ObjectEntity is read/unread and marking an ObjectEntity as read/unread.





## Methods

| Name | Description |
|------|-------------|
|[__construct](#readunreadservice__construct)||
|[addDateRead](#readunreadserviceadddateread)|Adds dateRead to a response, specifically the given metadata array, using the given ObjectEntity to determine the correct dateRead.|
|[removeUnreads](#readunreadserviceremoveunreads)|After a successful get item call we want to remove unread objects for the logged-in user, this function removes all unread objects for the current user + given object.|
|[setDateRead](#readunreadservicesetdateread)|Marks the given ObjectEntity for the current user as read, by creating an Audit Trail.|
|[setUnread](#readunreadservicesetunread)|Checks if there exists an unread object for the given ObjectEntity + current UserId. If not, create one.|




### ReadUnreadService::__construct  

**Description**

```php
 __construct (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ReadUnreadService::addDateRead  

**Description**

```php
public addDateRead (array $metadata, \ObjectEntity $objectEntity, bool $getItem)
```

Adds dateRead to a response, specifically the given metadata array, using the given ObjectEntity to determine the correct dateRead. 

 

**Parameters**

* `(array) $metadata`
: The metadata of the ObjectEntity we are adding dateRead to.  
* `(\ObjectEntity) $objectEntity`
: The ObjectEntity we are adding the last date read for.  
* `(bool) $getItem`
: If the call done was a get item call we always want to set dateRead to now.  

**Return Values**

`void`




<hr />


### ReadUnreadService::removeUnreads  

**Description**

```php
public removeUnreads (\ObjectEntity $objectEntity)
```

After a successful get item call we want to remove unread objects for the logged-in user, this function removes all unread objects for the current user + given object. 

 

**Parameters**

* `(\ObjectEntity) $objectEntity`
: The ObjectEntity we are removing Unread objects for.  

**Return Values**

`void`




<hr />


### ReadUnreadService::setDateRead  

**Description**

```php
public setDateRead (\AuditTrailService $auditTrailService, string $identification)
```

Marks the given ObjectEntity for the current user as read, by creating an Audit Trail. 

Currently, already/also automatically done in the AuditTrailService after a Get Item call. 

**Parameters**

* `(\AuditTrailService) $auditTrailService`
: The Audit Trail service. Do not set this service in the constructor,  
because this will create a construct loop with AuditTrailService!  
* `(string) $identification`
: The identification of the ObjectEntity we are setting / updating the last dateRead for.  

**Return Values**

`void`




<hr />


### ReadUnreadService::setUnread  

**Description**

```php
public setUnread (\ObjectEntity $objectEntity)
```

Checks if there exists an unread object for the given ObjectEntity + current UserId. If not, create one. 

 

**Parameters**

* `(\ObjectEntity) $objectEntity`
: The ObjectEntity we are creating an Unread object for.  

**Return Values**

`void`




<hr />

