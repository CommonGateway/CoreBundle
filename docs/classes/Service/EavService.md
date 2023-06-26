# CommonGateway\CoreBundle\Service\EavService  







## Methods

| Name | Description |
|------|-------------|
|[__construct](#eavservice__construct)||
|[checkAttributeforEntity](#eavservicecheckattributeforentity)|Checks an atribute to see if a schema for its reference has becomme available.|
|[checkEntityforAttribute](#eavservicecheckentityforattribute)|Checks an entity to see if there are anny atributtes waiting for it.|
|[deleteAllObjects](#eavservicedeleteallobjects)|Removes all object entities from the database (should obviously not be used in production).|




### EavService::__construct  

**Description**

```php
 __construct (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### EavService::checkAttributeforEntity  

**Description**

```php
public checkAttributeforEntity (\Attribute $attribute)
```

Checks an atribute to see if a schema for its reference has becomme available. 

 

**Parameters**

* `(\Attribute) $attribute`

**Return Values**

`\Attribute`




<hr />


### EavService::checkEntityforAttribute  

**Description**

```php
public checkEntityforAttribute (\Entity $entity)
```

Checks an entity to see if there are anny atributtes waiting for it. 

 

**Parameters**

* `(\Entity) $entity`

**Return Values**

`\Entity`




<hr />


### EavService::deleteAllObjects  

**Description**

```php
public deleteAllObjects (\Entity|null $entity)
```

Removes all object entities from the database (should obviously not be used in production). 

 

**Parameters**

* `(\Entity|null) $entity`
: An optional entity to remove all the objects from  

**Return Values**

`int`

> The amount of objects deleted.


<hr />

