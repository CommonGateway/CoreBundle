# CommonGateway\CoreBundle\Service\AuthenticationService  

The auhtentication service handled authentication.





## Methods

| Name | Description |
|------|-------------|
|[__construct](#authenticationservice__construct)||
|[checkHS256](#authenticationservicecheckhs256)|Decides if the provided JWT token is signed with the HS256 Algorithm.|
|[checkHeadersAndGetJWK](#authenticationservicecheckheadersandgetjwk)|Checks the algorithm of the JWT token and decides how to generate a JWK from the provided public key.|
|[checkRS512](#authenticationservicecheckrs512)|Decides if the provided JWT token is signed with the RS512 Algorithm.|
|[convertRSAtoJWK](#authenticationserviceconvertrsatojwk)||
|[getAlgorithm](#authenticationservicegetalgorithm)||
|[getApplicationId](#authenticationservicegetapplicationid)||
|[getAuthentication](#authenticationservicegetauthentication)||
|[getCertificate](#authenticationservicegetcertificate)|Writes the certificate and ssl keys to disk, returns the filenames.|
|[getHmacToken](#authenticationservicegethmactoken)||
|[getJWK](#authenticationservicegetjwk)||
|[getJwtPayload](#authenticationservicegetjwtpayload)||
|[getJwtToken](#authenticationservicegetjwttoken)|Create a JWT token from Component settings.|
|[getTokenFromUrl](#authenticationservicegettokenfromurl)||
|[removeFiles](#authenticationserviceremovefiles)|Removes certificates and private keys from disk if they are not necessary anymore.|
|[verifyJWTToken](#authenticationserviceverifyjwttoken)|Verifies the JWT token and returns the payload if the JWT token is valid.|




### AuthenticationService::__construct  

**Description**

```php
 __construct (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### AuthenticationService::checkHS256  

**Description**

```php
public checkHS256 (\JWT $token)
```

Decides if the provided JWT token is signed with the HS256 Algorithm. 

 

**Parameters**

* `(\JWT) $token`
: The token provided by the user  

**Return Values**

`bool`

> Whether the token is in HS256 or not


<hr />


### AuthenticationService::checkHeadersAndGetJWK  

**Description**

```php
public checkHeadersAndGetJWK (\JWT $token, string $publicKey)
```

Checks the algorithm of the JWT token and decides how to generate a JWK from the provided public key. 

 

**Parameters**

* `(\JWT) $token`
: The JWT token sent by the user  
* `(string) $publicKey`
: The public key provided by the application  

**Return Values**

`\JWK`

> The resulting JWK for verifying the JWT


<hr />


### AuthenticationService::checkRS512  

**Description**

```php
public checkRS512 (\JWT $token)
```

Decides if the provided JWT token is signed with the RS512 Algorithm. 

 

**Parameters**

* `(\JWT) $token`
: The token provided by the user  

**Return Values**

`bool`

> Whether the token is in HS256 or not


<hr />


### AuthenticationService::convertRSAtoJWK  

**Description**

```php
 convertRSAtoJWK (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### AuthenticationService::getAlgorithm  

**Description**

```php
 getAlgorithm (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### AuthenticationService::getApplicationId  

**Description**

```php
 getApplicationId (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### AuthenticationService::getAuthentication  

**Description**

```php
 getAuthentication (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### AuthenticationService::getCertificate  

**Description**

```php
public getCertificate (array $config)
```

Writes the certificate and ssl keys to disk, returns the filenames. 

 

**Parameters**

* `(array) $config`
: The configuration as stored in the source  

**Return Values**

`array`

> The overrides on the configuration with filenames instead of certificate contents


<hr />


### AuthenticationService::getHmacToken  

**Description**

```php
 getHmacToken (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### AuthenticationService::getJWK  

**Description**

```php
 getJWK (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### AuthenticationService::getJwtPayload  

**Description**

```php
 getJwtPayload (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### AuthenticationService::getJwtToken  

**Description**

```php
public getJwtToken (array $component, string $)
```

Create a JWT token from Component settings. 

 

**Parameters**

* `(array) $component`
: The code of the component  
* `(string) $`
: The JWT token  

**Return Values**

`void`


<hr />


### AuthenticationService::getTokenFromUrl  

**Description**

```php
 getTokenFromUrl (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### AuthenticationService::removeFiles  

**Description**

```php
public removeFiles (array $config)
```

Removes certificates and private keys from disk if they are not necessary anymore. 

 

**Parameters**

* `(array) $config`
: The configuration with filenames  

**Return Values**

`void`




<hr />


### AuthenticationService::verifyJWTToken  

**Description**

```php
public verifyJWTToken (string $token, string $publicKey)
```

Verifies the JWT token and returns the payload if the JWT token is valid. 

 

**Parameters**

* `(string) $token`
: The token to verify  
* `(string) $publicKey`
: The public key to verify the token to  

**Return Values**

`array`

> The payload of the token


**Throws Exceptions**


`\HttpException`
> Thrown when the token cannot be verified

<hr />

