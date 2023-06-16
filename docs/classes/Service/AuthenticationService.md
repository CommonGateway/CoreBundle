# CommonGateway\CoreBundle\Service\AuthenticationService  







## Methods

| Name | Description |
|------|-------------|
|[__construct](#authenticationservice__construct)|__construct|
|[checkHS256](#authenticationservicecheckhs256)|Decides if the provided JWT token is signed with the HS256 Algorithm.|
|[checkHeadersAndGetJWK](#authenticationservicecheckheadersandgetjwk)|Checks the algorithm of the JWT token and decides how to generate a JWK from the provided public key.|
|[checkRS256](#authenticationservicecheckrs256)|Decides if the provided JWT token is signed with the HS256 Algorithm.|
|[checkRS512](#authenticationservicecheckrs512)|Decides if the provided JWT token is signed with the RS512 Algorithm.|
|[convertRSAKeyToJWK](#authenticationserviceconvertrsakeytojwk)|Converts a string RSA key to a JWK via the filesystem.|
|[convertRSAtoJWK](#authenticationserviceconvertrsatojwk)|Converts an RSA private key to a JWK.|
|[createJwtToken](#authenticationservicecreatejwttoken)|Creates a JWT token to identify with on the application.|
|[getAlgorithm](#authenticationservicegetalgorithm)|Determines the algorithm for the JWT token to create from the source.|
|[getApplicationId](#authenticationservicegetapplicationid)|Gets an application id for a source.|
|[getAuthentication](#authenticationservicegetauthentication)|Gets the authentication values through various checks.|
|[getCertificate](#authenticationservicegetcertificate)|Writes the certificate and ssl keys to disk, returns the filenames.|
|[getHmacToken](#authenticationservicegethmactoken)|Gets a hmac token.|
|[getJWK](#authenticationservicegetjwk)|Gets a JWK for a source based on the algorithm of the source.|
|[getJwtPayload](#authenticationservicegetjwtpayload)|Creates the JWT payload to identify at an external source.|
|[getJwtToken](#authenticationservicegetjwttoken)|Create a JWT token from Component settings.|
|[getTokenFromUrl](#authenticationservicegettokenfromurl)|Checks from which type of auth we need to fetch a token from.|
|[removeFiles](#authenticationserviceremovefiles)|Removes certificates and private keys from disk if they are not necessary anymore.|
|[serializeUser](#authenticationserviceserializeuser)|Serializes a user to be used by the token authenticator.|
|[verifyJWTToken](#authenticationserviceverifyjwttoken)|Verifies the JWT token and returns the payload if the JWT token is valid.|




### AuthenticationService::__construct  

**Description**

```php
public __construct (void)
```

__construct 

 

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
: The token provided by the user.  

**Return Values**

`bool`

> Whether the token is in HS256 or not.


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


### AuthenticationService::checkRS256  

**Description**

```php
public checkRS256 (\JWT $token)
```

Decides if the provided JWT token is signed with the HS256 Algorithm. 

 

**Parameters**

* `(\JWT) $token`
: The token provided by the user.  

**Return Values**

`bool`

> Whether the token is in HS256 or not.


<hr />


### AuthenticationService::checkRS512  

**Description**

```php
public checkRS512 (\JWT $token)
```

Decides if the provided JWT token is signed with the RS512 Algorithm. 

 

**Parameters**

* `(\JWT) $token`
: The token provided by the user.  

**Return Values**

`bool`

> Whether the token is in HS256 or not.


<hr />


### AuthenticationService::convertRSAKeyToJWK  

**Description**

```php
public convertRSAKeyToJWK (string $key)
```

Converts a string RSA key to a JWK via the filesystem. 

 

**Parameters**

* `(string) $key`
: The key to load  

**Return Values**

`\JWK`

> The resulting Json Web Key


<hr />


### AuthenticationService::convertRSAtoJWK  

**Description**

```php
public convertRSAtoJWK (\Source $source)
```

Converts an RSA private key to a JWK. 

 

**Parameters**

* `(\Source) $source`

**Return Values**

`\JWK`

> The resulting Json Web Key


<hr />


### AuthenticationService::createJwtToken  

**Description**

```php
public createJwtToken (string $key, array $payload)
```

Creates a JWT token to identify with on the application. 

 

**Parameters**

* `(string) $key`
: The private key to create a JWT token with  
* `(array) $payload`
: The payload to create a JWT token with  

**Return Values**

`string`

> The resulting JWT token


<hr />


### AuthenticationService::getAlgorithm  

**Description**

```php
public getAlgorithm (\Source $source)
```

Determines the algorithm for the JWT token to create from the source. 

 

**Parameters**

* `(\Source) $source`
: The source to determine the algorithm for  

**Return Values**

`string`

> The algorithm to use


<hr />


### AuthenticationService::getApplicationId  

**Description**

```php
public getApplicationId (\Source $source)
```

Gets an application id for a source. 

 

**Parameters**

* `(\Source) $source`
: The source to dermine the application id for  

**Return Values**

`string`

> The application ID to use


<hr />


### AuthenticationService::getAuthentication  

**Description**

```php
public getAuthentication (void)
```

Gets the authentication values through various checks. 

 

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
public getHmacToken (void)
```

Gets a hmac token. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### AuthenticationService::getJWK  

**Description**

```php
public getJWK (string $algorithm, \Source $source)
```

Gets a JWK for a source based on the algorithm of the source. 

 

**Parameters**

* `(string) $algorithm`
* `(\Source) $source`

**Return Values**

`\JWK`

> The resulting Json Web Key


<hr />


### AuthenticationService::getJwtPayload  

**Description**

```php
public getJwtPayload (\Source $source)
```

Creates the JWT payload to identify at an external source. 

 

**Parameters**

* `(\Source) $source`
: The source to create a payload for  

**Return Values**

`string`

> The JWT payload to use


<hr />


### AuthenticationService::getJwtToken  

**Description**

```php
public getJwtToken (\Source $source)
```

Create a JWT token from Component settings. 

 

**Parameters**

* `(\Source) $source`
: The source to authenticate to  

**Return Values**

`string`

> The resulting JWT token


<hr />


### AuthenticationService::getTokenFromUrl  

**Description**

```php
public getTokenFromUrl (\Source $source, string $authType)
```

Checks from which type of auth we need to fetch a token from. 

 

**Parameters**

* `(\Source) $source`
* `(string) $authType`

**Return Values**

`string|null`

> Fetched JWT token.


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


### AuthenticationService::serializeUser  

**Description**

```php
public serializeUser (\User $user, \SessionInterface $session)
```

Serializes a user to be used by the token authenticator. 

 

**Parameters**

* `(\User) $user`
: The user to be serialized  
* `(\SessionInterface) $session`
: The session to use  

**Return Values**

`array`

> The serialized user


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

