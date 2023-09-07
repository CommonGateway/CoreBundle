# Import & Export


> **Warning**
> This file is maintained at Conduction’s [Google Drive](https://docs.google.com/document/d/1DNqCl6AXXrVXWzpaF3r55s56NVM0hOoHE2AL8EcyT5g/edit) Please make any suggestions of alterations there.
Import
## File uploads (Import)

The common gateway supports creating objects through file uploads. The file uploads can be done in various file formats and the results of the file uploads can be adapted to fit the schema the objects have to be created in.


### Uploading a file


This functionality can be used without the Gateway UI with the information given in this documentation. It is however easier and developed for use with Gateway UI on the Import and upload page.

So without the Gateway UI:

Files can be uploaded to the endpoint /admin/file-upload with x-form-urlencoded or multipart-formdata encoding. The files are expected to be uploaded in the field ‘upload’. Furthermore, the schema for the uploaded objects has to be defined in the field ‘schema’, which can contain the reference to the schema, as well as the id of the schema. Mapping likewise can be defined in the field ‘mapping’, and can contain the reference to the mapping as well as the id. Mapping is not a required field, the user can upload the file without mapping if a mapping is not required (or to see what the mapping should look like.

The response of this call is in the following format:
```json
{
  "results":[
    {
      "action": "CREATE",
      "object": {[the resulting object]},
      "id": null,
      "validations": {[the output of validating the object]}
    }
  ]
}
``` 
If the object is detected to be preexisting, the field `action` will indicate `UPDATE`, and the id of the preexisting object will be returned in the field `id`.

After receiving this information the objects have not yet been stored in the database. If the result does not contain content in the field `validations` this indicates that the object is ready to be uploaded to the database using a POST (if `action` is `CREATE`) or a PUT (if `action` is `UPDATE` and `id` is set) to the `objects` endpoint with a schema.

### Supported file formats
The common gateway supports file uploads in the following formats, these can all be send as a multipart/form-data form and requires the header: Content-Type: multipart/form-data:
- JSON
- Yaml
- XML
- CSV (default with the `,` as the delimiter, but adaptable with request parameters as described below)
- xlsx (Microsoft Office Excel 2007 and newer)
- xls (Microsoft Office Excel 2003 and older)
- ods (LibreOffice and OpenOffice)

### Parameters
The behavior of the file upload can be adapted with the help of a number of request query parameters.
At this time the parameters apply only to spreadsheet files (ods, xls, xlsx and csv), and one is only applicable to CSV.

The available parameters are:

- `headers`: use this for .ods files. This parameter decides if the first row of a spreadsheet should be considered a header row, containing the keys for the columns in the rows below. If this parameter is set to true, the resulting objects will be converted in such a way that all values can be found using the header
- `delimiter`: this parameter sets the delimiter character for csv files. By default this is the comma `,`, however, in many cases, csv files use the semicolon (`;`) as the delimiter (i.e. csv exports from Microsoft Office Excel always use the semicolon as delimiter). If this parameter is set, the character in the parameter is used as the delimiter in parsing the CSV file.

### Duplicate detection
A mechanism to detect duplicates with the existing database is in place, this however depends on the definition of the data. If the data is an export from the same gateway, the detection will work on the `_id` field of the objects that are found in the exports from the gateway.

If another field than `_id` has to be the unique identifier in the existing data, this can be configured using the Mapping used for importing the data from a file. This is done by overriding the field `_id` with the field you want to be the unique identifier. for example:

```json
{
 "mapping": {
    "_id": "identifier"
   }
}
```
Where `identifier` is the field we want to designate as our unique identifier. Keep in mind that if casts are required to set the `identifier` field, these casts should also be run on the `_id` field.

For further information on mapping, see the [mapping documentation](/docs/features/Mappings.md).

## Export
To retrieve records in a downloadable format, adjust the 'Accept' header in your request as follows:
- CSV Format: Set the 'Accept' header to text/csv.
- Excel Spreadsheet (XLSX): Set the 'Accept' header to application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.

By specifying the desired format in the header, you signal the gateway to provide the data in either CSV or Excel spreadsheet format, ready for download.


Additionally, if you wish to retrieve records and map them to a different-looking object with a Mapping, you can provide the id of that Mapping in the request headers as: x-mapping: {id}.

Ensure you replace {id} with the actual ID of the Mapping object you want to use.

### Export a single object
The gateway is also able to export single objects to downloadable formats, being PDF, docx and html. However, to be able to do this we need to do a bit of configuration:

First, we should make a template that contains a html/twig template to render the object in the desired format. This can be done in the Admin-UI under the tab ‘Templates’ (and click on add template). Also, a simple example can be found below:

```json
{
    "name": "A simple test template",
    "description": "Show the resource as a table",
    "content": "<html><body><h1>{{ object._self.name }}</h1><hr><table>{% for key,value in object %}<tr><th>{{ key }}</th><td>{% if value is iterable %}{% for subkey,subvalue in value %}{{ subkey }}: {%if subvalue is iterable %}array{%else%}{{subvalue}}{%endif%}<br>{% endfor %}{% else %}{{ value }}{% endif %}</td></tr>{% endfor %}</table></body></html>",
    "organization": "/admin/organisations/a1c8e0b6-2f78-480d-a9fb-9792142f4761",
    "supportedSchemas": ["b6bd2cfc-c83d-486a-869f-16d6986240cf", "dd5e3008-74aa-451f-82a6-c6edcbbbe69e"]
}
```
Note that the schema of the object to render should be in the list of supported schemas of the template.

Once this template is created, the single object can be downloaded by changing the Accept header to ‘application/pdf’, ‘text/html’ or ‘application/vnd.openxmlformats-officedocument.wordprocessingml.document’ for pdf, html and docx respectively.

