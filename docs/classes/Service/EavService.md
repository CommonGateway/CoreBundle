# CommonGateway\CoreBundle\Service\EavService  







## Methods

| Name | Description |
|------|-------------|
|[__construct](#eavservice__construct)|The constructor sets al needed variables.|
|[checkAttributeforEntity](#eavservicecheckattributeforentity)|Checks an attribute to see if a schema for its reference has become available.|
|[checkEntityforAttribute](#eavservicecheckentityforattribute)|Checks an entity to see if there are anny attributes waiting for it.|
|[deleteAllObjects](#eavservicedeleteallobjects)|Removes all object entities from the database (should obviously not be used in production).|




### EavService::__construct  

**Description**

```php
public __construct (\EntityManagerInterface $entityManager)
```

The constructor sets al needed variables. 

 

**Parameters**

* `(\EntityManagerInterface) $entityManager`

**Return Values**

`void`


<hr />


### EavService::checkAttributeforEntity  

**Description**

```php
public checkAttributeforEntity (\Attribute $attribute)
```

Checks an attribute to see if a schema for its reference has become available. 

 

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

Checks an entity to see if there are anny attributes waiting for it. 

 

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

