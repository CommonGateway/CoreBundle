# CommonGateway\CoreBundle\Service\DownloadService  

Handles incoming notification api-calls by finding or creating a synchronization and synchronizing an object.





## Methods

| Name | Description |
|------|-------------|
|[__construct](#downloadservice__construct)|The constructor sets al needed variables.|
|[downloadCSV](#downloadservicedownloadcsv)|Generates a CSV response from a given CSV string.|
|[downloadDocx](#downloadservicedownloaddocx)|Downloads a docx.|
|[downloadHtml](#downloadservicedownloadhtml)|Downloads a html.|
|[downloadPdf](#downloadservicedownloadpdf)|Downloads a pdf.|
|[downloadXLSX](#downloadservicedownloadxlsx)|Generates an XLSX response from a given array of associative arrays.|
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


### DownloadService::downloadCSV  

**Description**

```php
public downloadCSV (string $csvString)
```

Generates a CSV response from a given CSV string. 

This method takes a CSV-formatted string and creates a downloadable CSV response.  
The client will be prompted to download the resulting file with the name "data.csv". 

**Parameters**

* `(string) $csvString`
: The CSV-formatted string to be returned as a downloadable file.  

**Return Values**

`\Response`

> A Symfony response object that serves the provided CSV string as a downloadable CSV file.


<hr />


### DownloadService::downloadDocx  

**Description**

```php
public downloadDocx (array $data)
```

Downloads a docx. 

The html that is added has to be whitout a <head><style></style></head> section. 

**Parameters**

* `(array) $data`
: The data to render for this docx.  

**Return Values**

`string`

> The docx as file output.


<hr />


### DownloadService::downloadHtml  

**Description**

```php
public downloadHtml (array $data)
```

Downloads a html. 

 

**Parameters**

* `(array) $data`
: The data to render for this html.  

**Return Values**

`string`

> The html as file output.


<hr />


### DownloadService::downloadPdf  

**Description**

```php
public downloadPdf (array $data, string|null $templateRef)
```

Downloads a pdf. 

 

**Parameters**

* `(array) $data`
: The data to render for this pdf.  
* `(string|null) $templateRef`
: The templateRef.  

**Return Values**

`string`

> The pdf as string output.


<hr />


### DownloadService::downloadXLSX  

**Description**

```php
public downloadXLSX (array $objects)
```

Generates an XLSX response from a given array of associative arrays. 

This method takes an array of associative arrays (potentially having nested arrays) and  
creates an XLSX spreadsheet with columns for each unique key (using dot notation for nested keys).  
The method then streams this spreadsheet as a downloadable XLSX file to the client. 

**Parameters**

* `(array) $objects`
: An array of associative arrays to convert into an XLSX file.  

**Return Values**

`\Response`

> A Symfony response object that allows the client to download the generated XLSX file.


<hr />


### DownloadService::render  

**Description**

```php
public render (array $data, string|null $templateRef)
```

Renders a pdf. 

 

**Parameters**

* `(array) $data`
: The data to render.  
* `(string|null) $templateRef`
: The templateRef.  

**Return Values**

`string`

> The content rendered.


<hr />

