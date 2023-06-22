# CommonGateway\CoreBundle\Service\FileSystemCreateService

## Methods

| Name | Description |
|------|-------------|
|[\_\_construct](#filesystemcreateservice__construct)|The class constructor.|
|[createZipFileFromContent](#filesystemcreateservicecreatezipfilefromcontent)|Writes a zip file in the local filesystem.|
|[openFtpFilesystem](#filesystemcreateserviceopenftpfilesystem)|Connects to a Filesystem.|
|[openZipFilesystem](#filesystemcreateserviceopenzipfilesystem)|Opens a zip filesystem.|
|[removeZipFile](#filesystemcreateserviceremovezipfile)|Removes a zip file from the local filesystem.|

### FileSystemCreateService::\_\_construct

**Description**

```php
public __construct (void)
```

The class constructor.

**Parameters**

`This function has no parameters.`

**Return Values**

`void`

<hr />

### FileSystemCreateService::createZipFileFromContent

**Description**

```php
public createZipFileFromContent (string $content)
```

Writes a zip file in the local filesystem.

**Parameters**

*   `(string) $content`
    : The string contents of the zip file.

**Return Values**

`string`

<hr />

### FileSystemCreateService::openFtpFilesystem

**Description**

```php
public openFtpFilesystem (\Source $source)
```

Connects to a Filesystem.

**Parameters**

*   `(\Source) $source`
    : The Filesystem source to connect to.

**Return Values**

`\Filesystem`

> The Filesystem Operator.

**Throws Exceptions**

`\Exception`

<hr />

### FileSystemCreateService::openZipFilesystem

**Description**

```php
public openZipFilesystem (string $filename)
```

Opens a zip filesystem.

**Parameters**

*   `(string) $filename`
    : The Filename of the zip file.

**Return Values**

`\Filesystem`

> The Filesystem Operator.

**Throws Exceptions**

`\Exception`

<hr />

### FileSystemCreateService::removeZipFile

**Description**

```php
public removeZipFile (string $filename)
```

Removes a zip file from the local filesystem.

**Parameters**

*   `(string) $filename`
    : The file to delete.

**Return Values**

`void`

<hr />
