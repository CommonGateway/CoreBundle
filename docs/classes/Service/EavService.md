# CommonGateway\CoreBundle\Service\EavService  







## Methods

| Name | Description |
|------|-------------|
|[__construct](#eavservice__construct)||
|[checkAttributeforEntity](#eavservicecheckattributeforentity)|Checks an atribute to see if a schema for its reference has becomme available.|
|[checkEntityforAttribute](#eavservicecheckentityforattribute)|Checks an entity to see if there are anny atributtes waiting for it.|




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
: The Attribute  

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
: The Entity  

**Return Values**

`\Entity`




<hr />

