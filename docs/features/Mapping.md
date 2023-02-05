# Mapping

Mapping is the process of changing the structure of an object. For example, an object is either sent to or retrieved from an external source. This always follows in the Input -> mapping >- Output model. Mapping is beneficial when the source doesn't match the data model you want to use. You can use a mapping to describe the delta (difference) between two objects.

The gateway performs mapping as a series of mapping rules handled in order. Mapping rules are written in a To <- From style. Or more simply put {desired key} :{current key}.

### Simple mapping

In its simplest form, a mapping consists of changing the position of a value within an object. A simple mapping does this mutation in To <- From order. Or in other words, you describe the object you want using the object (or other data) you have.

So let's see a simple mapping rule with a tree object like this

```json
{
  "id":"0d671e30-04af-479a-926a-5e7044484171",
  "name":"The big white tree",
  "description": "Chestnut", 
  "location":"Orvil’s farm"
}
```

Let's say we need to move the data in the `description` field to a `species` field to free up the description field for more generic data. Which we then also want to fill. In short, move a value to a new position and insert a new value in the old position. We can do that with the following two mapping rules. You can also set new data if a key is not found as seen here with description:

```json
{
   "name": "A simple mapping",
   "description": "This mapping changes the position of a value within an object",
   "mapping": {
       "species": "description",
       "description": "This is the tree that granny planted when she and gramps got married"
   }
}
```

Which will give us

```json
{
  "id":"0d671e30-04af-479a-926a-5e7044484171",
  "name":"The big white tree",
  "description": "This is the tree that granny planted when she and gramps got married", 
  "location":"Orvil’s farm", 
  "species":"Chestnut"
}
```

So what happened under the hood? And why is one value executed as a movement rule and the second as a string? Let's take a look at the first rule

```json
{
    "species":"description" 
}
```

Rules are carried out as a `To <- From` pair. In this case, the `species` has a `description` key. When interpreting what the description is, the mapping service has two options:

* The value is either a dot notation array pointing to another position in the object (see dot notation). If so, then the value of that position is copied to the new position. (Under the hood the gateway uses [PHP dot notation to](https://github.com/adbario/php-dot-notation) achieve this result)
* The value is not a dot notation array to another position in the object (see dot notation), then the value is rendered as a [twig](https://twig.symfony.com/) template.

Keep in mind that dot notations have no maximum depth, so on object like

```json
{
  "id":"0d671e30-04af-479a-926a-5e7044484171",
  "name":"The big white tree",
  "description": "Chestnut", 
  "location":{
	“”name”:"Orvil’s farm"
}
}
```

Could be mapped like

```json
{
   "name": "A simple mapping",
   "description": "This mapping changes the position of a value within an object",
   "mapping": {
       "location": "location.name",
   }
}

To

```json
{
  "id":"0d671e30-04af-479a-926a-5e7044484171",
  "name":"The big white tree",
  "description": "This is the tree that granny planted when she and gramps got married", 
  "location":"Orvil’s farm", 
  "species":"Chestnut"
}
```

> **Note**
> Using dot notation to move values around within an object will NOT cause the value to change or be converted. In other words you can move an entire array or sub object around by simply moving the property that it is in. Also booleans will remain booleans, integers integers etc.


### Advanced (Twig) mapping

Another means of mapping is Twig mapping. Let's look at a more complex mapping example to transform or map out data. We have a tree object in our data layer  looking like

```json
{
  "id":"0d671e30-04af-479a-926a-5e7044484171",
  "name":"The big white tree",
  "description": "This is the tree that granny planted when she and gramps got married", 
  "location":"Orvil’s farm", 
  "species":"Chestnut"
}
```

Now the municipality opened up a county-wide tree register, and we would like to register our tree there. The municipality decided to move locations and species of the tree into metadata array data and thus expects an object like this

```json
{
  "id":"0d671e30-04af-479a-926a-5e7044484171",
  "name":"The big old tree",
  "description": "This is the tree that granny planted when she and gramps got married", 
  "metadata":{
    "location":"Orvil’s farm", 
    "species":"Chestnut"
  }
}
```

Ok, so let's put our mapping to the rescue!

A mapping always consists of an array where the array keys are a dot notation of where we want something to go. And a value representing what we want to go there. That value is a string that may contain twig logic. In this twig logic, our original object is available as a variable. In this case we could do a mapping like

```json
{
   "name": "A twig mapping",
   "description": "For more complex mappings"
   "mapping": {
       "name": "name",
       "description: "description",
       "metadata.location": "{{ location }}",
       "metadata.species": "{{ species }}"
   }
}
```

We would then end up wit a new object (after mapping) looking like

 ```json
{
    "id":"0d671e30-04af-479a-926a-5e7044484171",
    "name":"The big white tree",
    "description": "This is the tree that granny planted when she and gramps got married",
    "metadata":{
        "location":"Orvil’s farm",
        "species":"Chestnut"
    }
}
```

> **Note**
> Both dot-notation and twig-based mapping are valid to move value's around in an object. Dot-notation is preferred performance-wise.

### Pass Through
In a normal situation the mapping only describes the wanted object, meaning that all variables that are not specifically mapped are ignored and won’t make it to the new object. If we have large objects this might be a lot of work (we would need to map EVERY value).

To solve this problem we might add passthrough to our mapping definition, this will cause all the current values to be copied under the same properties into the new object.  So

```json
{
  "id":"0d671e30-04af-479a-926a-5e7044484171",
  "name":"The big white tree",
  "description": "This is the tree that granny planted when she and gramps got married", 
  "location":"Orvil’s farm", 
  "species":"Chestnut"
}
```

With

```json
{
   "name": "A twig mapping",
   "description": "For more complex mappings", 
  "passthrough": true,
   "mapping": {
       "metadata.location": "{{ location }}",
       "metadata.species": "{{ species }}"
   }
}

Would become 

 ```json
{
    "id":"0d671e30-04af-479a-926a-5e7044484171",
    "name":"The big white tree",
    "description": "This is the tree that granny planted when she and gramps got married", 
    "location":"Orvil’s farm", 
    "species":"Chestnut",
    "metadata":{
        "location":"Orvil’s farm",
        "species":"Chestnut"
    }
}
```

Normally when using passthrough we would like to clean up the result because we tend to end up with double data.

> **Note**
> Using passthrough represents a security risk. All values make it to the new object, so it should only be used on trusted or internal objects

### Dropping keys

Okay so now we would like to do some cleanup. We can do that under the `unset` property. The `unset` property accepts an array of dot notation to drop. Let's change the mapping to

```json
{
  "name": "A dropping keys mapping",
   "description": "For dropping keys",
   "mapping": {
       "metadata.location": "{{ location }}",
       "metadata.species": "{{ species }}"
   },
   "unset": [
       "location",
       "species"
   ]
}
```

We now have an object that’s

```json
{
    "id":"0d671e30-04af-479a-926a-5e7044484171",
    "name":"The big white tree",
    "description":"This is the tree that granny planted when she and gramps got married", 
    "metadata":{
        "location":"Orvil’s farm", 
        "species":"Chestnut"
  }
}
```

> **Note**
> Dropping keys is always the second last action performed in the mapping process (before casting)

### Adding keys

The mapping setup allows you to add keys and values to objects simply by declaring them. Let's look at the above example and assume that the county wants us to enter the primary color of the tree. A value that we don't have in our object. Assume all our trees to be brown. We could then edit our mapping to

```json
{
   "name": "An adding keys mapping",
   "description": "For adding keys",
   "mapping": {
       "metadata.location": "{{ location }}",
       "metadata.species": "{{ species }}",
       "metadata.color": "Brown"
   },
   "unset": [
       "location",
       "species"
   ]
}
```

Which would return

```json
{
    "id":"0d671e30-04af-479a-926a-5e7044484171",
    "name":"The big white tree",
    "description": "This is the tree that granny planted when she and gramps got married", 
    "metadata":{
        "location":"Orvil’s farm", 
        "species":"Chestnut", 
        "color":"Brown"
  }
}
```

...even though we didn't have a color value initially.

Also, note that we used a simple string value here instead of twig code. That's because the twig template may contain strings.

### Working with conditional data

Twig natively supports many [logical operators](https://twig.symfony.com/doc/3.x/templates.html), but a few of those are exceptionally handy when dealing with mappings. For example, concatenating
strings like {{ 'string 1' ~ 'string 2' }} which can be used as the source data inside the mapping

```json
{
   "name": "An conditional data mapping",
   "description": "For concatenating strings",
   "mapping": {
       "metadata.location": "{{ location }}",
       "metadata.species": "{{ species }}",
       "metadata.color": "{{ \"The color is \" ~ color }}"
   },
   "unset": [
       "location",
       "species",
       "color"
   ]
}
```

The same is achieved with [string interpolation](https://twig.symfony.com/doc/1.x/templates.html#string-interpolation) via

```json
{
  "name": "An conditional data mapping",
   "description": "For concatenating strings",
   "mapping": {
       "metadata.location": "{{ location }}",
       "metadata.species": "{{ species }}",
       "metadata.color": "{{ \"The color is #{color}\" }}"
   },
   "unset": [
       "location",
       "species",
       "color"
   ]
}
```

So both of the above notations would provide the same result

Another useful twig take is the if statement. This can be used to check if a values exists in the first place

```json
{
  "name": "An conditional data mapping",
   "description": "For concatenating strings",
   "mapping": {
       "metadata.location": "{{ location }}",
       "metadata.species": "{{ species }}",
       "metadata.color": "{% if color %} {{color}} {% else %} unknown {% endif %}"
   },
   "unset": [
       "location",
       "species",
       "color"
   ]
}
```

or to check for specific values

```json
{
   "name": "An conditional data mapping",
   "description": "For concatenating strings",
   "mapping": {
       "metadata.location": "{{ location }}",
       "metadata.species": "{{ species }}",
       "metadata.color": "{% if color == \"violet\" %} pink {% endif %}"
   },
   "unset": [
       "location",
       "species",
       "color"
   ]
}
```

### Sub mappings [ in beta]

In some cases you might want to make use of mappings that you have created before with the mapping you are currently defining. Common cases include mapping an array of sub objects or dividing your mapping into smaller files for stability purposes.

To do this you can access the mapping service from within a mapping trough twig like:


```json
{
   …
   "mapping": {
       "{key}": "{{ {value|mappingService(‘{id or ref}’, {array})}}}",
   },
   …
}

The mapping service takes two arguments:
[required]: Either the UUID or reference of the mapping that you want to use
[optional, defaults to false]:Whether you want to be mapped in its entirety (as an object) or as an array (of objects)


> **Warning**
> This functionality is in public betá and should not be used on production environments

### Casting (Forcing) the type/format of values

Due to twig rendering, mapping output will always change all the values to `string`. For internal gateway traffic, this isn’t problematic, as the data layer will cast values to the appropriate outputs. When sending data to an external source, having all Booleans cast to strings might be bothersome. To avoid this predicament, we can force the datatype of your values by ‘casting’ them

We can cast values by including a cast property in our mapping

```json
{
   "name": "An conditional data mapping",
   "description": "For casting to strings",
  …
   "cast": {
       “{key}”: "{type of casting to perform}"
   }
}

> **Warning**
> Beware what functions PHP uses to map these values and if the cast should be possible (or else an error is thrown).

| Cast            | Function                                                  | Twig   |
|---------------- |---------------------------------------------------------- |--------|
| string          | <https://www.php.net/manual/en/function.strval.php>         | No     |
| bool / boolean  | <https://www.php.net/manual/en/function.boolval.php>        | No     |
| int / integer   | <https://www.php.net/manual/en/function.intval.php>         | No     |
| float           | <https://www.php.net/manual/en/function.floatval>           |  No     |
| array           |                                                           | No     |
| date            | <https://www.php.net/manual/en/function.date>               |  No     |
| url             | <https://www.php.net/manual/en/function.urlencode.php>      |  Yes   |
| rawurl          | <https://www.php.net/manual/en/function.rawurlencode.php>   |  Yes   |
| base64          | <https://www.php.net/manual/en/function.base64-encode.php>  |  Yes   |
| json            | <https://www.php.net/manual/en/function.json-encode.php>    |  Yes   |
| xml             |                                                           |  No     |

Example a mapping of

```json
{
  ..
    "metadata.hasFruit": "Yes",
  ..
}
```

With mapping

```json
{
  …
   "cast": {
       “metadata.hasFruit”: "bool"
   }
  …
}

Would return

```json
{
  ...
    "metadata":{
      ...
      "hasFruit":true
  }
}
```
> **Note**
> Casting is always the last action performed by the mapping service


### Translating values

Twigg natively supports [translations](https://symfony.com/doc/current/translation.html),  but remember that translations are an active filter `|trans`. And thus should be specifically called on values you want to translate. Translations are performed against a translation table. You can read more about configuring your translation table here.

The base for translations is the locale, as provided in the localization header of a request. When sending data, the base is in the default setting of a gateway environment. You can also translate from an specific table and language by configuring the translation filter e.g. {{ 'greeting' | trans({}, `[table_name]`, `[language]`) }}

Original object

```json
{
    "color":"brown"
}
```

With mapping

```json
{
   "mapping": {
    	"color":"{{source.color|trans({},\"colors\") }}"
    }
}
```

returns (on locale nl)

```json
{
    "color":"bruin"
}
```

If we want to force German (even if the requester asked for a different language), we'd map like

```json
{
    "color":"{{source.color|trans({},\"colors\".\"de\") }}" 
}
```

And get

```json
{
    "color":"braun"
}
```

### Renaming Keys

The mapping doesn't support the renaming of keys directly but can rename keys indirectly by moving the data to a new position and dropping the old position (is we are using pass through)

Original object

```json
{
    "name":"The big old tree"
}
```

With mapping

```json
{
   "passtrough":true,
   "mapping": {
   	 "naam":"{{source.name }}"
   } 
  "unset": [
      "name"
  ]
}
```

returns

```json
{
    "naam":"The big old tree"
}
```

### What if I can't map?

Even with all the above options, it might be possible that the objects you are looking at are too different to map. In that case, don't look for mapping solutions. If objects A and B are too different, add them to the data layer and write a plugin to keep them in sync based on actions.


