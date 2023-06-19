# Plugins

> **Warning**
> This file is maintained at the Conduction [Google Drive](https://docs.google.com/document/d/1TOQbfFrwDel4sF2D36tGjDAZJD9K7P0VKd9I10JDVy0/edit). Please make any suggestions or alterations there.

Plugins are a neat way of separating concerns and making sure that client specific code doesn't get into the core. You can read a bit more about why we use plugins under [code quality](Code_quality.md).

The Common Gateway is easily extendable through a plugin structure. The structure is based on the [Symfony bundle system](https://symfony.com/doc/current/bundles.html) in other words, all Common Gateway plugins are Symfony bundles, and Symfony bundles can be Common Gateway plugins.
You can consider a plugin for the Common Gateway as a configuration set to extend a base Gateway's functionality. The plugin structure is based on the [Symfony bundle system](https://symfony.com/doc/current/bundles.html). In other words, all Common Gateway plugins are Symfony bundles, and Symfony bundles can be Common Gateway plugins.

If you want to develop your own plugin, we suggest using the Pet store plugin as a starting point.

## Finding and installing plugins

If you start from a brand new Gateway installation and head over to your Dashboard, you can find the plugin section on the left side panel. You can search for the plugins you want to add from this tab by selecting Search for plugins. Find the plugin you wish to install and view its details page. You should see an install button in the top right corner if the plugin is not installed.

The Common Gateway finds plugins to install with packagist. It does this entirely under the hood, and the only requirement is that plugins need a ‘common-gateway-plugin” tag. Packagist functions as a plugin store as well in this regard.

The plugins are installed, updated, and removed with the composer CLI. While this feature still exists for developers, we recommend using the user interface see plugins for installing plugins.


## Creating plugins
If you want to develop your plugin, we recommend using the [PetStoreBundle](https://github.com/CommonGateway/PetStoreBundle). This method ensures all necessary steps are taken, and the plugin will be found and installable through the method described above.
## Updating and removing plugins

In case you want to update or remove a plugin, go to “Plugins” in the Gateway UI main menu and select “Installed”. Click on the plugin that you want to update or remove and press the Update or Remove button in the top right of the screen.

## Adding Actions, Sources, Cronjobs, to your plugin

You can include an installation folder in the root of your plugin repository containing schema.json files or other files. Whenever the Gateway installs or updates a plugin, it looks for the schema map and handles all schema.json files in that folder as a schema upload.

Keep in mind that you will need to properly set the $schema of the object in order for the gateway to understand what schema you are trying to create. The core schema’s of the gateway are defined as

- 'https://docs.commongateway.nl/schemas/Action.schema.json',
- 'https://docs.commongateway.nl/schemas/Application.schema.json',
- 'https://docs.commongateway.nl/schemas/CollectionEntity.schema.json,
- 'https://docs.commongateway.nl/schemas/Cronjob.schema.json',
- 'https://docs.commongateway.nl/schemas/DashboardCard.schema.json',
- 'https://docs.commongateway.nl/schemas/Endpoint.schema.json',
- 'https://docs.commongateway.nl/schemas/Entity.schema.json',
- 'https://docs.commongateway.nl/schemas/Gateway.schema.json',
- 'https://docs.commongateway.nl/schemas/Mapping.schema.json',
- 'https://docs.commongateway.nl/schemas/Organization.schema.json',
- 'https://docs.commongateway.nl/schemas/SecurityGroup.schema.json',

> Note: While adding SecurityGroups through core schema's is allowed, adding (or changing) Users is not, because of security reasons, if you would like to add users (in a more secure way) take a look at how to configure an installation.json file.
> - _'https://docs.commongateway.nl/schemas/User.schema.json',_

[Here](https://github.com/CommonGateway/CoreBundle/blob/master/Schema/example.json) is an example. The $id and $schema properties are needed for the Gateway to find the plugin. The version property's value helps the Gateway decide whether an update is required and will update automatically.

## Installation.json

to do

## Adding test data or fixtures to your plugin

You can include both fixtures and test data in your plugin. The difference is that fixtures are required for your plugin to work, and test data is optional. You can include both data sets as .json files in the folder at the root of your plugin repository. An example is shown here.

Datasets are categorized by name, e.g., data.json in the data folder will be considered a fixture, whereas [anything else].json will be regarded as test or optional data (and not loaded  by default).

As a fixture, anything in data.json is always loaded on a plugin installation or update. The other files are never loaded on a plugin install or update. However, the user can load the files manually from the plugin details page in the gateway UI.


All files should follow the following convention in their structure
1 - A primary array indexes on schema refs,
2 - Secondary array within each primary array containing the objects that you want to upload or update for that specific schema.

```json
{
  “ref1”: [
    {”object1”},
    {”object2”},
    {”object3”}
  ],
  “ref2”:[
    {”object1”},
    {”object2”},
    {”object3”}
  ],
  “ref3”:[
    {”object1”},
    {”object2”},
    {”object3”}
  ]
}
```

Keep in mind that the `ref` here could either be a schema you defined yourself or a gateway core schema. It is therefore also possible to upload several configuration files through a data.json.

When handling data uploads (be it fixtures or others), the Gateway will loop through the primary array, trying to find the appropriate schema for your object. If it finds this schema, it creates or updates the object for the schema.

For fixtures, this is done in an unsafe manner, meaning that
When the Gateway can’t find a schema for a reference, it will ignore the reference and continue
The Gateway won't validate the objects (meaning that you can ignore property requirements, but you can't add values for non-existent properties)

When handling other uploads with optional ore test data the Gateway does so in a safe manner, meaning:
When the Gateway can’t find a schema for a reference, it will throw an error
The Gateway will validate the objects and throw an error when it reaches an invalid object

## Adding configuration scripts to your plugin

Sometimes, you should be more specific about how you want your plugin to be configured. For example, provide endpoints for your schemas, check if sources exist, or create actions.

For this, you can add PHP scripts to your plugin that run whenever your plugin is installed, updated, or removed. While you can technically have the code anywhere in your codebase, optimally, it's made as a service. There is an example shown here (#todo). You will need an installer to make it work for the Gateway.
For this, you can add PHP scripts to your plugin that are run whenever your plugin is installed, updated or removed. To include an installation script create a new service in the service folder of your plugin (convention is calling it InstallationService



