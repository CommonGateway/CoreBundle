# CommonGateway\CoreBundle\Service\MappingService

The mapping service handles the mapping (or transformation) of array A (input) to array B (output).

More information on how to write your own mappings can be found at [/docs/Mappings.md](/docs/Mappings.md).

## Methods

| Name | Description |
|------|-------------|
|[\_\_construct](#mappingservice__construct)|Setting up the base class with required services.|
|[coordinateStringToArray](#mappingservicecoordinatestringtoarray)|Converts a coordinate string to an array of coordinates.|
|[mapping](#mappingservicemapping)|Maps (transforms) an array (input) to a different array (output).|
|[setStyle](#mappingservicesetstyle)|Set symfony style in order to output to the console.|

### MappingService::\_\_construct

**Description**

```php
public __construct (\Environment $twig)
```

Setting up the base class with required services.

**Parameters**

*   `(\Environment) $twig`

**Return Values**

`void`

<hr />

### MappingService::coordinateStringToArray

**Description**

```php
public coordinateStringToArray (string $coordinates)
```

Converts a coordinate string to an array of coordinates.

**Parameters**

*   `(string) $coordinates`
    : A string containing coordinates.

**Return Values**

`array`

> An array of coordinates.

<hr />

### MappingService::mapping

**Description**

```php
public mapping (\Mapping $mappingObject, array $input, bool $list)
```

Maps (transforms) an array (input) to a different array (output).

**Parameters**

*   `(\Mapping) $mappingObject`
    : The mapping object that forms the recipe for the mapping
*   `(array) $input`
    : The array that need to be mapped (transformed) otherwise known as input
*   `(bool) $list`
    : Wheter we want a list instead of a sngle item

**Return Values**

`array`

> The result (output) of the mapping process

**Throws Exceptions**

`\LoaderError|\SyntaxError`

> Twig Exceptions

<hr />

### MappingService::setStyle

**Description**

```php
public setStyle (\SymfonyStyle $io)
```

Set symfony style in order to output to the console.

**Parameters**

*   `(\SymfonyStyle) $io`

**Return Values**

`self`

<hr />
