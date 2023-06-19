# Providing APIs (Application programming interface)

> **Warning**
> This file is maintained at Conduction’s [Google Drive](https://docs.google.com/document/d/1LMm7OCoJrghHLWv9mRztXiWkzIQZuACq6uOFI8XV2ys/edit) Please make any suggestions of alterations there.

The gateway provides an API for other applications to use and consume APIs from sources in a way that gateway acts both as a provider and consumer of APIs. How to consume APIs from the gateway is further detailed under the Sources chapters. This chapter deals with providing APIs from the gateway to other applications

## Endpoints

Each api consists of an [collection]() of [endpoints](Endpoints.md). These provide the basis location that a call can be made to e.g. `/api/pets`.

## Contex

The gateway always views each call to an endpoint in its own context determined by three main aspects.

*   ([User](Authentication.md)) Who is making the call? e.g. User John
*   (\[Application]Applications.md) How is he making the call? e.g. from the front desk applications
*   (Process) For which process is he making the call? e.g. client registration

The call is then handled by the [request service]().

## Generic API Functionality

A normal filter:
{propertyName}={searchValue} e.g. `firstname=john`

A property is IN array filter:
{propertyName}\[]={searchValue1} e.g. ‘firstname\[]=john’
{propertyName}\[]={searchValue2} e.g. ‘firstname\[]=harry’

or

A normal filter with method:
{propertyNema}\[method]={searchValue} e.g. ‘firstname\[case\_insensitive]=john’

A property is IN array filter with method:
{propertyNema}\[method]\[]={searchValue1} e.g. ‘number\[int\_compare]\[]=2’
{propertyNema}\[method]\[]={searchValue2} e.g. ‘number\[int\_compare]\[]=5’
Note that not every method can be used like this

All functional query parameters always start with an \_ to prevent collisions with property names e.g. \_order

## Methods

method less queries (e.g. `firstname=john`) are treated as exact methods `firstname[exact]=john`

*   **\[exact] (default) exact match**
    Only usable on properties of the type `text`,  `integer` or `datetime`. Seea

*   **\[case\_insensitive] (default) case insensitive searching**
    Only usable on properties of the type `text`, uses the regex function under the hood in a case insensitive way.

*   **\[case\_sensitive] case sensitive searching**
    Only usable on properties of the type `text`, uses the regex function under the hood in a case sensitive way.

*   **\[like] wildcard search**
    Only usable on properties of the following types:
    `text`,
    `integer`
    `datetime`
    These types work the same as a regex search, but wraps the value in `.*` creating `.*$value.*` and sets the matching pattern to case insensitive and multi-line.This means you can search for single words in sentences or text. Keep in mind that `like` will search for complete occurrences zo $name\[like] =John Doe will only return hits on “John Doe” and not records containing either John OR  Doe.

*   **\[>=] equal or greater than**
    Only usable on properties of the type `integer`, will automatically cast the searched value to integer to make the comparison

*   **\[>] greater than**
    Only usable on properties of the type `integer`, will automatically cast the searched value to an integer to make the comparison

*   **\[<=] equal or smaller than**
    Only usable on properties of the type `integer`, will automatically cast the searched value to an integer to make the comparison

*   **\[<] smaller than**
    Only usable on properties of the type `integer`, will automatically cast the searched value to an integer to make the comparison

*   **\[after] equal or greater than**
    Only usable on properties of the type `date` or `datetime`

*   **\[strictly\_after] greater than**
    Only usable on properties of the type `date` or `datetime`

*   **\[before] equal or smaller than**
    Only usable on properties of the type `date` or `datetime`

*   **\[strictly\_before] smaller than**
    Only usable on properties of the type `date` or `datetime`

*   **\[regex] compare the values based on regex**
    Only usable on properties of the type `string`

*   **\[int\_compare] will cast the value of your filter to an integer before we filter with it.**
    Useful when the stored value in the gateway cache is an integer, but by default you are searching in your query with a string “1012”.
    Works with the property IN array filter like this:
    {propertyNema}\[int\_compare]\[]={searchValue1}

*   **\[bool\_compare] will cast the value of your filter to a boolean before filtering.**
    Useful when the stored value in the gateway cache is a boolean, but by default you are searching in your query with a string “true”.
    Works with the property IN array filter like this:
    {propertyNema}\[bool\_compare]\[]={searchValue1}

> **Note**
> When comparing dates we use the PHP [dateTime($value)](https://www.php.net/manual/en/class.datetime.php) function to cast the strings to dates. That means that you can also input strings like `now`.`yesterday` see the full list of [relative formats](https://www.php.net/manual/en/datetime.formats.relative.php).

## Ordering the results

\_order\[propertyName] = desc/asc

> **Note**
> The `_search`order property currently also supports `order` for backwards compatibility

## Working with pagination

Requests to collections (e.g. more then one object) are encapsulated in an response object, the gateway automatically paginates results on 30. You can set the amount of items per page through the `_limit` query parameter. There is no upper limit to this parameter, so if desired, you could request 10000 objects in one go. This does however come with a performance drain because of the size of the returned response in bytes where the main throttle is the internet connection speed of the transfer combined with the size of individual objects.  We therefore suggest not to user limits greater than 100 in frontend applications.

```json
{
  "total":100,
  "limit":30,
  "pages":4,
  "page":1,
  "results":[]
}
```

*   **\_limit**
*   **\_page**
*   **\_start**

> **Note**
> The pagination properties currently also support backwards compatibility by removing the \_ part. Meaning that they may also be used as `limit`,`page` and `start`

## The search index

The Common Gateway automatically creates a search index of all objects based on the text value of their properties (non-text values are ignored). This search index can be used when approaching API endpoints through the special `_search` query parameter.  Search functions as a wildcard.

e.g. `_search=keyword`

By default the search query searches in all fields. If you want to search specific properties you can do so by defining them as methods. You can search properties fields (in an OR configuration) by separating them through a comma, and supplying them in the method.You can also search in sub properties  e.g.  `_search[property1,property2.subProperty]=keyword`.

> **Note**
> The `_search` property currently also supports `search` for backwards compatibility

## Limiting the return data

In some cases you either don’t need or want a complete object. In those cases it's good practice for the consuming application to limit the field in its return call. This makes the return messages smaller (and therefore faster), but it is also more secure, because it prevents the sending and retention of unnecessary data.

The returned data can be limited using the \_fields query parameter. This parameter is expected to be an array containing the name of the requested properties. It’s possible to include nested properties using dot notation. Let’s take a look at the following example.  We have a person object containing the following data:

```json
{
  "firstname":"John",
  "lastname":"Doe",
  "born":{
    "city":"Amsterdam",
    "country":"Netherlands",
    "date":"1985-07-27"	
  }
}
```

Of we then query using ` _fields[]=firstname&_fields[]=born.date` we would expect the following object to be returned:

```json
{
  "firstname":"John",
  "born":{
    "date":"1985-07-27"	
  }
}
```

> **Note**
> The \_fields property may be aliased as \_properties

\_remove is specific unset

## Specifying the data format

The gateway can deliver data from its data layer in several formats. Those formats are independent from their original source, e.g. A source where the gateway consumes information from might be XML based, but the gateway could then provide that information in JSON format to a different application.

The data format is defined by the application requesting the data through the `Accept` header.

## Mapping the data (transformation)

It is also possible to transform incoming data by providing a mapping object, more information about creating mapping objects can be found under [mappings](Mappings.md).

Mappings can be passed through the gateway by url encoding the desired mapping and passing it trough the \_mappings query parameter.

> **Note**
> It is discouraged to use mappings in this context since it makes the API restFull.
