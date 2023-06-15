# CommonGateway\CoreBundle\Service\ComposerService

## Methods

| Name | Description |
|------|-------------|
|[audit](#composerserviceaudit)|Search for a given term.|
|[getAll](#composerservicegetall)|Show al packages installed trough composer.|
|[getLockFile](#composerservicegetlockfile)|Gets all installed plugins from the lock file.|
|[getSingle](#composerservicegetsingle)|Show a single package installed trough composer.|
|[remove](#composerserviceremove)|Show a single package installed trough composer.|
|[require](#composerservicerequire)|Show a single package installed trough composer.|
|[search](#composerservicesearch)|Search for a given term.|
|[upgrade](#composerserviceupgrade)|Show a single package installed trough composer.|

### ComposerService::audit

**Description**

```php
public audit (array $options)
```

Search for a given term.

See https://getcomposer.org/doc/03-cli.md#show-info for a full list of al options and there function

**Parameters**

*   `(array) $options`

**Return Values**

`array`

<hr />

### ComposerService::getAll

**Description**

```php
public getAll (array $options)
```

Show al packages installed trough composer.

See https://getcomposer.org/doc/03-cli.md#show-info for a full list of al options and there function

**Parameters**

*   `(array) $options`

**Return Values**

`array`

<hr />

### ComposerService::getLockFile

**Description**

```php
public getLockFile (void)
```

Gets all installed plugins from the lock file.

**Parameters**

`This function has no parameters.`

**Return Values**

`array`



<hr />

### ComposerService::getSingle

**Description**

```php
public getSingle (string $package, array $options)
```

Show a single package installed trough composer.

See https://getcomposer.org/doc/03-cli.md#show-info for a full list of al options and there function

**Parameters**

*   `(string) $package`
*   `(array) $options`

**Return Values**

`array`

<hr />

### ComposerService::remove

**Description**

```php
public remove (string $package, array $options)
```

Show a single package installed trough composer.

See https://getcomposer.org/doc/03-cli.md#show-info for a full list of al options and there function

**Parameters**

*   `(string) $package`
*   `(array) $options`

**Return Values**

`array`

<hr />

### ComposerService::require

**Description**

```php
public require (string $package, array $options)
```

Show a single package installed trough composer.

See https://getcomposer.org/doc/03-cli.md#show-info for a full list of al options and there function

**Parameters**

*   `(string) $package`
*   `(array) $options`

**Return Values**

`array`

<hr />

### ComposerService::search

**Description**

```php
public search (string|null $search, array $options)
```

Search for a given term.

See https://getcomposer.org/doc/03-cli.md#show-info for a full list of al options and there function

**Parameters**

*   `(string|null) $search`
*   `(array) $options`

**Return Values**

`array`

<hr />

### ComposerService::upgrade

**Description**

```php
public upgrade (string $package, array $options)
```

Show a single package installed trough composer.

See https://getcomposer.org/doc/03-cli.md#show-info for a full list of al options and there function

**Parameters**

*   `(string) $package`
*   `(array) $options`

**Return Values**

`array`

<hr />
