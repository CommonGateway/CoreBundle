# CommonGateway\CoreBundle\Service\SchemaService  

The schema service is used to validate schema's.





## Methods

| Name | Description |
|------|-------------|
|[__construct](#schemaservice__construct)||
|[hydrate](#schemaservicehydrate)|Handles forced id's on object entities.|
|[validateAttribute](#schemaservicevalidateattribute)|Validates a single attribute.|
|[validateObjects](#schemaservicevalidateobjects)|Validates the objects in the EAV setup.|
|[validateSchema](#schemaservicevalidateschema)|Validates a single schema.|
|[validateSchemas](#schemaservicevalidateschemas)|Validates the schemas in the EAV setup.|
|[validateValues](#schemaservicevalidatevalues)|Validates the objects in the EAV setup.|




### SchemaService::__construct  

**Description**

```php
 __construct (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### SchemaService::hydrate  

**Description**

```php
public hydrate (\ObjectEntity $objectEntity, array $hydrate)
```

Handles forced id's on object entities. 

 

**Parameters**

* `(\ObjectEntity) $objectEntity`
: The object entity on wich to force an id  
* `(array) $hydrate`
: The data to hydrate  

**Return Values**

`\ObjectEntity`

> The PERSISTED object entity on the forced id


<hr />


### SchemaService::validateAttribute  

**Description**

```php
public validateAttribute (\Attribute $attribute)
```

Validates a single attribute. 

 

**Parameters**

* `(\Attribute) $attribute`
: The attribute to validate  

**Return Values**

`bool`




<hr />


### SchemaService::validateObjects  

**Description**

```php
public validateObjects (void)
```

Validates the objects in the EAV setup. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`




<hr />


### SchemaService::validateSchema  

**Description**

```php
public validateSchema (\Entity $schema)
```

Validates a single schema. 

 

**Parameters**

* `(\Entity) $schema`
: The schema to validate  

**Return Values**

`bool`




<hr />


### SchemaService::validateSchemas  

**Description**

```php
public validateSchemas (void)
```

Validates the schemas in the EAV setup. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`




<hr />


### SchemaService::validateValues  

**Description**

```php
public validateValues (void)
```

Validates the objects in the EAV setup. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`




<hr />

