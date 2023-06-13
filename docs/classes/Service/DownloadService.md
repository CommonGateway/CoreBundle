# CommonGateway\CoreBundle\Service\DownloadService  

Handles incoming notification api-calls by finding or creating a synchronization and synchronizing an object.





## Methods

| Name | Description |
|------|-------------|
|[__construct](#downloadservice__construct)|The constructor sets al needed variables.|
|[downloadPdf](#downloadservicedownloadpdf)|Downloads a pdf.|
|[render](#downloadservicerender)|Renders a pdf.|




### DownloadService::__construct  

**Description**

```php
public __construct (\EntityManagerInterface $entityManager, \LoggerInterface $requestLogger, \Environment $twig)
```

The constructor sets al needed variables. 

 

**Parameters**

* `(\EntityManagerInterface) $entityManager`
: The EntityManager  
* `(\LoggerInterface) $requestLogger`
: The Logger  
* `(\Environment) $twig`
: Twig  

**Return Values**

`void`


<hr />


### DownloadService::downloadPdf  

**Description**

```php
public downloadPdf (array $data)
```

Downloads a pdf. 

 

**Parameters**

* `(array) $data`
: The data to render for this pdf.  

**Return Values**

`string`

> The pdf as string output.


<hr />


### DownloadService::render  

**Description**

```php
public render (array $data)
```

Renders a pdf. 

 

**Parameters**

* `(array) $data`
: The data to render.  

**Return Values**

`string`

> The content rendered.


<hr />

