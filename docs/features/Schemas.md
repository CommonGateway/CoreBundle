# Schemas

> **Warning**
> This file is maintained at the Conduction [Google Drive](https://docs.google.com/document/d/1T1EGcUsA9Old1zvoBRRhE5qLO_UHbSvhrwJ3F74xYso/edit). Please make any suggestions or alterations there.

Schemas are the core of the Common Gateway’s data layer. They define and model objects and set the conditions  for objects. Each object in the gateway always belongs to ONE schema. Schemas follow the [JSON schema](https://json-schema.org/) standard and are therefore interchangeable with [OAS3](https://swagger.io/specification/) schemas. For the Dutch governmental ecosystem this means that a schema adheres to the `overige objecten standaard` description of an `object type`. In action to this we extend schema’s with metadata.

In a more traditional way, schema’s can be viewed as the “tables” of the data layer as they store data in a predefined way. However,  unlike tables, the data is stored as objects.
Where each data set, that would normally be a row, becomes an object. The main difference between tables and objects is that objects are multidimensional(a value can be another object)  and  table rows are flat (each column containing one value). An object can contain an array, objects or arrays of objects. Objects present us with a vastly superior way of serving data.

An example object could be

```json

{
  "id": 1,
  "name": "doggie",
  "status": "available"
}
```

Schema's define objects by giving us the properties they contain and conditional validations for the value of each property.

## Creating or updating a schema

Schema’s can be modeled from the schema page in the Admin UI or through the `/admin/schema` endpoint. To create a new schema go to “Schemas” in the menu, and press “Add schema”. Just fill in the name “PET and hit save (a schema needs to be created before you can add properties). After the schema is created you are automatically redirected to the edit page of that schema.

## Adding properties to a schema

Go to the properties tab and press “Add property” to add a property to your schema. When adding a property

## Adding objects

After adding properties to a schema

## Downloading schema’s

## Uploading schema’s

You can upload a schema in the Gateway UI by pressing the upload button in the top right corner. Schemas might also be uploaded by plugins or collections.  When a schema is uploaded the following things will happen:

The Gateway will look in its schema library if a version of that schema is already present. It does so based on the schema ID.
Based on that result the Gateway will handle the schema accordingly:
If no matching schema is found the gateway will create a new schema
If a matching schema is found the gateway wil compare versions and decide what to do:
The old schema has no set version and the new schema has no set version -> The gateway will update the old schema with the new schema
The old schema has no set version and the new schema has a set version -> The gateway will update the old schema with the new schema
The old schema has a set version number and the new schema has a higher set version number -> The gateway will update the old schema with the new schema
The old schema has a set version number and the new schema has a lower set version number -> The gateway does not update the schema
The old schema has a set version number and the new schema does not have a set version number -> The gateway does not update the schema

Lets take a look at an example. We have a weather plugin that contains a weather schema.

## Properties

An entity consists of the following properties that can be configured

| Property | Required | Description |
|--------|-------------| -------------|
|name| yes |An unique name for this entity|
|description| no | The description for this entity that will be shown in de API documentation|
|source| no |The source where this entity resides|
|endpoint| yes if an source is provided | The endpoint within the source that this entity should be posted to as an object|
|route| no | The route this entity can be found easier, should be a path |
|extend| no | Whether or not the properties of the original object are automatically included|

### Properties

Properties represent variables on objects. In the following object from the petstore api id, name, and status are properties.

```json

{
  "id": 1,
  "name": "doggie",
  "status": "available"
}
```

that you want to communicate to underlying sources. In a normal setup and attribute should at least apply the same restrictions as the underlying property (e.g. required) to prevent errors when pushing the entity to its source. It can however provide additional validations to a property, for example the source AIU might simply require the property ‘email’  to be a unique string, but you could set the form to ‘email’ causing the input to be validated as an ISO compatible email address.

#### Properties

| Property | Required | Description |
|--------|-------------| -------------|
|name| yes  |`string` An name for this attribute. MUST be unique on an entity level and MAY NOT be ‘id’,’file’,‘files’, ’search’,’fields’,’start’,’page’,’limit’,’extend’ or ’organization’|
|description| no | The description for this attribute  that will be shown in de API documentation|
|type|  yes |`string` See [types](#Types)|
|format|  no |`string` See [formats](#Formats)|
|validations|  no |`array of strings` See [validations](#Validations)|
|multiple|  no |`boolean` if this attribute expects an array of the given type |
|defaultValue|  no |`string` An default value for this value that will be used if a user doesn't supply a value|
|deprecated|  no |`boolean`  Whether or not this property has been deprecated and will be removed in the future|
|required|  no |`boolean` whether or not this property is required to be in a POST or UPDATE|
|requiredIf| no |`array` a nested set of validations that will cause this attribute to become required |
|forbidden|  no |`boolean` whether or not this property is forbidden to be in a POST or UPDATE|
|forbiddenIf| no |`array`  a nested set of validations that will cause this attribute to become forbidden|
|example|  no |`string` An example of the value that should be supplied|
|persistToSource|  no |`boolean` Setting this property to true will force the property to be saved in the gateway endpoint (default behavior is saving in the EAV)|
|searchable|  no |`boolean` Whether or not this property is searchable|
|cascade|  no |`boolean`  Whether or not this property can be used to create new entities (versus when it can only be used to link existing entities)|

> **Warning**
> To prevent collisions with json-ld, json-hall, graphql and inner gateway workings property names aren't allowed to start with the following characters `_`,`@`,`$` additionally you can’t add a property called `id` to your schema’s. When importing schema’s all properties in violation of the above will be ignored without warning.

\####Types
The type of attribute provides basic validations and a way for the gateway to store and cash values in an efficient manner. Types are derived from the OAS3 specification. Current available types are:

| Format | Description |
|--------|-------------|
|string| a text |
|integer| a full number without decimals|
|decimal| a number including decimals|
|boolean| a true/false |
|date| an ISO-??? date |
|date-time| an ISO-??? date |
|array| an array or list of values|
|object|Used to nest a Entity as attribute of another Entity, read more about [nesting]()|
|file|Used to handle file uploads, an Entity SHOULD only contain one attribute of the type file, read more about [handling file uploads]() |

*   you are allowed to use integer instead of int, boolean instead of bool, date-time or dateTime instead of datetime,

\####Formats
A format defines a way a value should be formatted, and is directly connected to a type, for example a string MAY BE a format of email, but an integer cannot be a valid email. Formats are derived from the OAS3 specification, but supplemented with formats that are generally needed in governmental applications (like BSN) . Current available formats are:

General formats

| Format | Type(s) | Description |
|--------|---------|-------------|
|alnum|         |Validates whether the input is alphanumeric or not. Alphanumeric is a combination of alphabetic and numeric characters|
|alpha|         |Validates whether the input contains only alphabetic characters|
|numeric|         |Validates whether the input contains only numeric characters|
|uuid|string||
|base| 	|Validate numbers in any base, even with non regular bases.|
|base64|	| Validate if a string is Base64-encoded.|
|countryCode|string|Validates whether the input is a country code in ISO 3166-1 standard.|
|creditCard|string|Validates a credit card number.|
|currencyCode|string|Validates an ISO 4217 currency code like GBP or EUR.|
|digit|string|Validates whether the input contains only digits.|
|directory|string|Validates if the given path is a directory.|
|domain|string|Validates whether the input is a valid domain name or not.|
|url|string|Validates whether the input is a valid url or not.|
|email|string|Validates an email address.|
|phone|string|Validates a phone number.|
|fibonacci|integer|Validates whether the input follows the Fibonacci integer sequence.|
|file|string|Validates whether file input is as a regular filename.|
|hexRgbColor|string|Validates whether the input is a hex RGB color or not.|
|iban|string|Validates whether the input is a valid IBAN (International Bank Account Number) or not.|
|imei|string|Validates if the input is a valid IMEI.|
|ip|string|Validates whether the input is a valid IP address.|
|isbn|string|Validates whether the input is a valid ISBN or not.|
|json|string|Validates if the given input is a valid JSON.|
|xml|string|Validates if the given input is a valid XML.|
|languageCode|string|Validates whether the input is language code based on ISO 639.|
|luhn|string|Validate whether a given input is a Luhn number.|
|macAddress|string|Validates whether the input is a valid MAC address.|
|nfeAccessKey|string|Validates the access key of the Brazilian electronic invoice (NFe).|

*   Phone numbers should ALWAYS be treated as a string since they MAY contain a leading zero.

*Country specific formats*

| Format | Type(s) | Description |
|--------|---------|-------------|
|bsn|string|Dutch social security number (BSN)|
|nip|string, integer|Polish VAT identification number (NIP)|
|nif|string, integer|Spanish fiscal identification number (NIF)|
|cnh|string, integer|Brazilian driver’s license|
|cpf|string, integer| Validates a Brazilian CPF number |
|cnpj|string, integer|Validates if the input is a Brazilian National Registry of Legal Entities (CNPJ) number |

*   Dutch BSN numbers should ALWAYS be treated as a string since they MAY contain a leading zero.

\####Validations
Besides validations on type and string you can also use specific validations, these are contained in the validation array. Validation might be specific to certain types or formats e.g. minValue can only be applied to values that can be turned into numeric values. And other validations might be of a more general nature e.g. required.

| Validation | value | Description |
|--------|---------|-------------|
|between| |Validates whether the input is between two other values.|
|boolType| |Validates whether the type of the input is boolean.|
|boolVal| |Validates if the input results in a boolean value.|
|call| |Validates the return of a \[callable]\[] for a given input.|
|callableType| |Validates whether the pseudo-type of the input is callable.|
|callback| |Validates the input using the return of a given callable.|
|charset| |Validates if a string is in a specific charset.|
|alwaysInvalid| | Validates any input as invalid|
|alwaysValid| | Validates any input as valid|
|anyOf| | This is a group validator that acts as an OR operator. AnyOf returns true if at least one inner validator passes.|
|arrayType| | Validates whether the type of an input is array|
|arrayVal| |Validates if the input is an array or if the input can be used as an array (instance of ArrayAcces or SimpleXMLElement.|
|attribute| | Validates an object attribute, even private ones.|
|consonant| | Validates if the input contains only consonants.|
|contains| | Validates if the input contains some value. |
|containsAny| | Validates if the input contains at least one of defined values.|
|control| | Validates if all of the characters in the provided string, are control characters. |
|countable| | Validates if the input is countable, in other words, if you’re allowed to use count() function on it. |
|decimal| | Validates whether the input matches the expected number or decimals.|
|each| | Validates whether each value in the input is valid according to another rule.|
|endsWith| | This validator is similar to Contains(), but validates only if the value is at the end of the input.|
|equals| | Validates if the input is equal to some value.|
|equivalent| | Validates if the input is equivalent to some value.|
|even| |  Validates whether the input is an even number or not.|
|executable| |Validates if a file is an executable.|
|exists| | Validates files or directories.|
|extension| | Validates if the file extension matches the expected one. This rule is case-sensitive.|
|factor| | Validates if the input is a factor of the defined dividend.|
|falseVal| | Validates if a value is considered as false.|
|file| | Validates whether file input is as a regular filename.|
|image| | Validates if the file is a valid image by checking its MIME type.|
|filterVar| | Validates the input with the PHP’s filter\_var() function.|
|finite| | Validates if the input is a firtine number.|
|floatType| | Validates whether the type of the input is float.|
|floatVal| |Validate whether the input value is float.|
|graph| | Validates if all characters in the input are printable and actually creates visible output (no white space).|
|greaterThen| | Validates whether the input is greater than a value.|
|identical| | Validates if the input is identical to some value.|
|in| | Validates if the input is contained in a specific haystack. |
|infinite | | Validates if the input is an infinite number.|
|instance| | Validates if the input is an instance of the given class or interface.|
|iterableType| | Validates whether the pseudo-type of the input is iterable or not, in other words, if you're able to iterate over it with foreach language construct.|
|key| | Validates an array key.|
|keyNested| | Validates an array key or an object property using . to represent nested data.|
|keySet| | Validates keys in a defined structure.|
|keyValue| | |
|leapDate| |Validates if a date is leap.|
|leapYear| |Validates if a year is leap.|
|length| | |
|lessThan| |Validates whether the input is less than a value.|
|lowercase | |Validates whether the characters in the input are lowercase.|
|not| | |
|notBlank| |Validates if the given input is not a blank value (null, zeros, empty strings or empty arrays, recursively).|
|notEmoji| |Validates if the input does not contain an emoji.|
|no| |Validates if value is considered as “No”.|
|noWhitespace| |Validates if a string contains no whitespace (spaces, tabs and line breaks).|
|noneOf| |Validates if NONE of the given validators validate.|
|max| |Validates whether the input is less than or equal to a value.|
|maxAge| |Validates a maximum age for a given date. The $format argument should be in accordance with PHP's date() function.|
|mimetype| |Validates if the input is a file and if its MIME type matches the expected one.|
|min| |Validates whether the input is greater than or equal to a value.|
|minAge| |Validates a minimum age for a given date. The $format argument should be in accordance with PHP's date() function.|
|multiple| |Validates if the input is a multiple of the given parameter.|
|negative| |Validates whether the input is a negative number.|

# Objects

An object is a data set conforming to schema, e.g for the schema pet we might have an object pluto.

## Metadata

## Hydration

The process of transforming incoming data to objects is called hydration.
