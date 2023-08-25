# Import & Export


> **Warning**
> This file is maintained at Conduction’s [Google Drive](https://docs.google.com/document/d/1DNqCl6AXXrVXWzpaF3r55s56NVM0hOoHE2AL8EcyT5g/edit) Please make any suggestions of alterations there.
Import
## File uploads

The common gateway supports creating objects through file uploads. The file uploads can be done in various file formats and the results of the file uploads can be adapted to fit the schema the objects have to be created in.

### Uploading a file
Files can be uploaded to the endpoint /admin/file-upload with x-form-urlencoded or multipart-formdata encoding. The files are expected to be uploaded in the field ‘upload’. Furthermore, the schema of the uploaded objects is defined in the field ‘schema’, which can contain the reference to the schema, as well as the id of the schema. Mapping likewise is set in the field ‘mapping’, and can contain the reference to the mapping as well as the id. Mapping is not a required field, the user can upload the file without mapping if a mapping is not required (or to see what the mapping should look like.

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
The common gateway supports file uploads in the following formats:
- JSON
- Yaml
- XML
- CSV (default with the `,` as the delimiter, but adaptable with request parameters as described below)
- xlsx (Microsoft Office Excel 2007 and newer)
- xls (Microsoft Office Excel 2003 and older)
- ods (LibreOffice and OpenOffice)

### Parameters
The behavior of the file upload can be adapted with the help of a number of request parameters.
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