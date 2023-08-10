# File uploads
> **Warning**
> This file is maintained at Conduction’s [Google Drive](https://docs.google.com/document/d/1aUt8EnKb-16A5Ke58x80bQUcL2lRpUfH_8AMm7q2YyA/edit) Please make any suggestions of alterations there.

The common gateway supports creating objects through file uploads. The file uploads can be done in various file formats and the results of the file uploads can be adapted to fit the schema the objects have to be created in.

## Uploading a file
Files can be uploaded to the endpoint /admin/file-upload with x-form-urlencoded or multipart-formdata encoding. The files are expected to be uploaded in the field ‘upload’.
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

## Supported file formats
The common gateway supports file uploads in the following formats:
- JSON
- Yaml
- XML
- CSV (default with the `,` as delimiter, but adaptable with request parameters, see below)
- xlsx (Microsoft Office Excel 2007 and newer)
- xls (Microsoft Office Excel 2003 and older)
- ods (LibreOffice and OpenOffice)

## Parameters
The behaviour of the file upload can be adapted with the help of a number of request parameters.
At this time the parameters apply only to spreadsheet files (ods, xls, xlsx and csv), and one is only applicable to CSV.

The available parameters are:

- `headers`: this parameter decides if the first row of a spreadsheet should be considered a header row, containing the keys for the columns in the rows below. If this parameter is set to `true`, the resulting objects will be converted in such a way that all values can be found using the header
- `delimiter`: this parameter sets the delimiter character for csv files. By default this is the comma `,`, however, in many cases, csv files use the semicolon (`;`) as the delimiter (i.e. csv exports from Microsoft Office Excel always use the semicolon as delimiter). If this parameter is set, the character in the parameter is used as delimiter in parsing the CSV file.

