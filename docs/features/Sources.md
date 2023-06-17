# Sources

> **Warning**
> This file is maintained at the Conduction [Google Drive](https://docs.google.com/document/d/1htUlWeImLmybSjF9_yqXLoDSMyfmpgbWDnTMcB68hCA/edit). Please make any suggestions or alterations there.

Sources represent places where the Gateway can get its information, typically these would be other APIs, but the gateway can also connect the files servers through (S)FTP and databases directly (MongoDB, PostgreSQL, MySQL, Oracle, and MSSQL), blockchain solutions, or networks of trust (NLX/FCS). The source object represents  information about the source (status and transaction logs) as well as the current connection setting. You can find sources through the sources menu item at the left side of the Admin UI or the admin/sources endpoint on the gateway API

.

## Adding and testing a source

Sources can be added through the add source button at the top right of the sources overview page.

Let's take a look at configuring a source for the Swagger Pet store. Head over to the sources page and press “add source” on the following page and set the source `location` to https://petstore.swagger.io/v2/pet and pick a name. Since the Swagger Petstore is unconnected, we don’t need additional details and can save our connection by pressing  the save button.

After saving our connection the test tab appears below the connection detail page. We can now  test the connection to our source. Let's try a request with the GET method to the endpoint `/findByStatus?status=available`. Enter the details in the form and press `test connection`.  If the connection test is successful, we should see the result of our test in the right bottom corner Additionally, the status of the connection should update to the last call, and a new call log should be available under the logs tab with the results of our test described.

Now that we tested our connection, we can add the connection to our dashboard by pressing “+ Add to dashboard”  Headr back over to the dashboard to see the status card of our new connection.

## Syncing a source

For this part we assume that you already have made a schema called pet containing the properties `name` and `status`.  If you haven't yet done this,  follow the steps under [schema]() to create a schema resource accordingly

## Exposing a source to applications

todo

### (Reverse) Proxy

The easiest way to expose a source to applications is setting up a reverse proxy. A reverse proxy means that all the requests sent to a specific endpoint on the gateway are forwarded to the source, and the response of the source is forwarded to the asking applications.

Setting up a reverse proxy shields the underlying source from the application. The application authenticatess itself to the gateway and the gateway then contacts the source. This means that the application doesn’t need authentication or access to the source itself and allows the gateway to monitor the traffic. You can read more about setting up proxies under [endpoints](https://common-ground-documentation.readthedocs.io/en/latest/endpoints/).

## Datalayer

A different approach to exposing the data (directly) within a source is creating a schema that maps to the source. This method gives a bit more flexibility in the transformer
