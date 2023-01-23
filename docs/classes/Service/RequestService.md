# CommonGateway\CoreBundle\Service\RequestService  

Handles incomming request from endpoints or controllers that relate to the gateways object structure (eav).





## Methods

| Name | Description |
|------|-------------|
|[__construct](#requestservice__construct)||
|[checkEmbedded](#requestservicecheckembedded)|If embedded should be shown or not.|
|[createResponse](#requestservicecreateresponse)|Creating the responce object.|
|[getId](#requestservicegetid)|Get the ID from given parameters.|
|[getSchema](#requestservicegetschema)|Get the schema from given parameters returns false if no schema could be established.|
|[getScopes](#requestservicegetscopes)|Get a scopes array for the current user (or of the anonymus if no user s logged in)|
|[itemRequestHandler](#requestserviceitemrequesthandler)||
|[proxyHandler](#requestserviceproxyhandler)||
|[realRequestQueryAll](#requestservicerealrequestqueryall)|A function to replace Request->query->all() because Request->query->all() will replace some characters with an underscore.|
|[requestHandler](#requestservicerequesthandler)|Handles incomming requests and is responsible for generating a responce.|
|[searchRequestHandler](#requestservicesearchrequesthandler)|This function searches all the objectEntities and formats the data.|
|[shouldWeUnsetEmbedded](#requestserviceshouldweunsetembedded)|If embedded should be shown or not.|




### RequestService::__construct  

**Description**

```php
 __construct (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### RequestService::checkEmbedded  

**Description**

```php
public checkEmbedded (object|array $result)
```

If embedded should be shown or not. 

 

**Parameters**

* `(object|array) $result`
: fetched result  

**Return Values**

`array|null`




<hr />


### RequestService::createResponse  

**Description**

```php
public createResponse ( $data)
```

Creating the responce object. 

 

**Parameters**

* `() $data`

**Return Values**

`\CommonGateway\CoreBundle\Service\Response`




<hr />


### RequestService::getId  

**Description**

```php
public getId (array $object)
```

Get the ID from given parameters. 

 

**Parameters**

* `(array) $object`

**Return Values**

`string|false`




<hr />


### RequestService::getSchema  

**Description**

```php
public getSchema (array $parameters)
```

Get the schema from given parameters returns false if no schema could be established. 

 

**Parameters**

* `(array) $parameters`

**Return Values**

`\Entity|false`




<hr />


### RequestService::getScopes  

**Description**

```php
public getScopes (void)
```

Get a scopes array for the current user (or of the anonymus if no user s logged in) 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`array`




<hr />


### RequestService::itemRequestHandler  

**Description**

```php
 itemRequestHandler (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### RequestService::proxyHandler  

**Description**

```php
 proxyHandler (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### RequestService::realRequestQueryAll  

**Description**

```php
public realRequestQueryAll (string $method)
```

A function to replace Request->query->all() because Request->query->all() will replace some characters with an underscore. 

This function will not. 

**Parameters**

* `(string) $method`
: The method of the Request  

**Return Values**

`array`

> An array with all query parameters.


<hr />


### RequestService::requestHandler  

**Description**

```php
public requestHandler (array $data, array $configuration)
```

Handles incomming requests and is responsible for generating a responce. 

 

**Parameters**

* `(array) $data`
: The data from the call  
* `(array) $configuration`
: The configuration from the call  

**Return Values**

`\Response`

> The modified data


<hr />


### RequestService::searchRequestHandler  

**Description**

```php
public searchRequestHandler (array $data, array $configuration)
```

This function searches all the objectEntities and formats the data. 

 

**Parameters**

* `(array) $data`
: The data from the call  
* `(array) $configuration`
: The configuration from the call  

**Return Values**

`array`

> The modified data


<hr />


### RequestService::shouldWeUnsetEmbedded  

**Description**

```php
public shouldWeUnsetEmbedded (object|array $result, ?array $accept)
```

If embedded should be shown or not. 

 

**Parameters**

* `(object|array) $result`
: fetched result  
* `(?array) $accept`
: accept header  

**Return Values**

`array|null`




<hr />

