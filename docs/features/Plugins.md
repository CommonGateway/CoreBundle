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

## Installation

The gateway supports installations, update and remove actions for plugins. Allowing them to change configurations and alter data, this is done through stallation.json file. The installation.json file is a fundamental part of the plugin installation process. It provides the necessary configuration for a plugin to integrate smoothly with the platform. This file should be located in the `/Installation` folder of the plugin's directory.

Here is an explanation of the various sections in the installation.json file:


### InstallationService


- **installationService**: This specifies the service that handles the installation process. For example: "installationService": "CommonGateway\\PetStoreBundle\\Service\\InstallationService"

The installation service allows you to run code during changes to the plugins livecycle. It **MUST** always implement the `CommonGateway\CoreBundle\Installer\InstallerInterface`. And it **CAN** provide functions that are called during changes to the plugin from the gateways installer.

An example installationService could like like

```php
<?php
/**
 * The installation service
 *
 * @author  Conduction.nl <info@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 */

namespace CommonGateway\PetStoreBundle\Service;

use CommonGateway\CoreBundle\Installer\InstallerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;


class InstallationService implements InstallerInterface
{

    /**
     * The entity manager
     *
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * The installation logger.
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;


    /**
     * The constructor
     *
     * @param EntityManagerInterface $entityManager      The entity manager.
     * @param LoggerInterface        $installationLogger The installation logger.
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $installationLogger
    ) {
        $this->entityManager = $entityManager;
        $this->logger        = $installationLogger;

    }//end __construct()


    /**
     * Every installation service should implement an install function
     *
     * @return void
     */
    public function install()
    {
        $this->logger->debug("PetStoreBundle -> Install()");

        $this->checkDataConsistency();

    }//end install()


    /**
     * Every installation service should implement an update function
     *
     * @return void
     */
    public function update()
    {
        $this->logger->debug("PetStoreBundle -> Update()");

        $this->checkDataConsistency();

    }//end update()


    /**
     * Every installation service should implement an uninstall function
     *
     * @return void
     */
    public function uninstall()
    {
        $this->logger->debug("PetStoreBundle -> Uninstall()");

        // Do some cleanup to uninstall correctly...

    }//end uninstall()


    /**
     * The actual code run on update and installation of this bundle
     *
     * @return void
     */
    public function checkDataConsistency()
    {
        //This is the place where you can add or change Installation data from/for this bundle or other required bundles.
        //Note that in most cases it is recommended to use .json files in the Installation folder instead, if possible.
        
        $this->entityManager->flush();

    }//end checkDataConsistency()


}//end class
```

> Note:
> In most cases it isn’t actually necessary to write an installation service, you can just load configurations by supplying the necessary objects.


### Configuration
There are two routes to include configuration (objects) in your plugin. The first and easiest one is to include them directly into your installation.json. This is possible for applications, users, cards, actions, collections, endpoints and cronjobs. You can also supply the object separately, in that case they **MUST** be contained in de the `/Installation` folder of the plugin's directory and **SHOULD** be in a sub folder labeled after the type of object that you want to create e.g. `/Cards`. This is the preferred way (especially with larger plugins) because it keeps a repository more readable.

If however you want to create objects from the `installation.json` you can use the following properties:
- **applications**: This is an array of applications related to the plugin. Each application should have properties like title, $id, $schema, version, description, and domains.

- **users**: This section defines the users that have access to the plugin. Each user should have properties like $id, version, description, email, locale, and securityGroups.

- **cards**: This section includes properties like schemas, collections, and applications.

- **actions**: This section defines the handlers and the actions associated with them. Each handler should have properties like reference, actionHandler, listens, and configuration. The configuration section includes specific parameters that the handler uses.

- **collections**: This is an array of collections that the plugin should have access to. Each collection should have properties like reference and schemaPrefix.

- **endpoints**: This section defines the API endpoints that the plugin exposes. This section is divided into multipleSchemas and schemas. The multipleSchemas section allows defining endpoints that use multiple schemas. Each endpoint should have properties like $id, version, name, description, schemas, path, pathRegex, and methods. The schemas section allows defining endpoints specific to a schema.

- **cronjobs**:

Below is an example of the structure of an installation.json file:
```json
{
  "installationService": "CommonGateway\\PetStoreBundle\\Service\\InstallationService",
  "applications": [
    {
      "title": "Example Front-end Application",
      "$id": "https://example.com/application/ps.frontend.application.json",
      "$schema": "https://docs.commongateway.nl/schemas/Application.schema.json",
      "version": "0.0.1",
      "description": "An example Front-end Application. This is not required for the gateway to work, there is a default Application created on init. Applications can be used to allow the given domains to use the gateway. And can be used by plugin services to get a domain of an application.",
      "domains": [
        "frontend.example.com"
      ]
    }
  ],
  "users": [
    {
      "$id": "https://example.com/user/johnDoe.user.json",
      "version": "0.0.1",
      "description": "An example User with an example SecurityGroup. It is not allowed to set a User password or change/create Admin Users this way.",
      "email": "johnDoe@username.com",
      "locale": "en",
      "securityGroups": [
        "https://example.com/securityGroup/example.securityGroup.json"
      ]
    }
  ],
  ...
}
```

The above configuration represents a part of the installation.json for a hypothetical Pet Store plugin. It specifies the installation service, an application, and a user. Additional sections should be added as needed, following the structure outlined above.

Please ensure that your installation.json file follows this structure and includes all required sections for your plugin. This will ensure a smooth installation process and correct integration of your plugin with the system

> Note:
> The installation.json should not contain any descriptionarry information about the plugin. That should be provided trough the `composer.json` in the plugins route.

## Adding test data or fixtures to your plugin

You can include both fixtures and test data in your plugin. The difference is that fixtures are required for your plugin to work, and test data is optional. You can include both data sets as .json files in the folder at the root of your plugin repository. An example is shown here.

Datasets are categorized by name, e.g., data.json in the data folder will be considered a fixture, whereas [anything else].json will be regarded as test or optional data (and not loaded  by default).

As a fixture, anything in data.json is always loaded on a plugin installation or update. The other files are never loaded on a plugin install or update. However, the user can load the files manually from the plugin details page in the gateway UI.


All files should follow the following convention in their structure
1 - A primary array indexes on schema refs,
2 - Secondary array within each primary array containing the objects that you want to upload or update for that specific schema.

```json
{
  "ref1": [
    {"object1"},
    {"object2"},
    {"object3"}
  ],
  "ref2":[
    {"object1"},
    {"object2"},
    {"object3"}
  ],
  "ref3":[
    {"object1"},
    {"object2"},
    {"object3"}
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

## Describing your plugin
Plugins are described trough a [`composer.json`](https://getcomposer.org/doc/04-schema.md) file in the plugin root. In order for a plugin to findable and installable for common gateway installations it **MUST** meet the following criteria:
- Have a name
- Type is set to `symfony-bundle`
-  `common-gateway-plugin` is added to the key-words
   `commongateway/corebundle` is a requirement

Other fields are optional but highly recommended, keep in mind that both the gateway plugin store and common-gateway website look for a readme.md in the repository root to display as personal page for the plugin.

An example composer file would look like

```json
{
    "name": "common-gateway/pet-store-bundle",
    "description": "An example package for creating symfony flex bundles as plugins",
    "type": "symfony-bundle",
    "keywords": [
        "commongateway",
        "common",
        "gateway",
        "conduction",
        "symfony",
        "common-gateway-plugin",
        "pet store"
    ],
    "homepage": "https://commongateway.nl",
    "license": "EUPL-1.2",
    "minimum-stability": "dev",
    "require": {
        "php": ">=7.4",
        "commongateway/corebundle": "^1.0.51"
    },
    "require-dev": {
        "symfony/dependency-injection": "~3.4|~4.1|~5.0"
    },
    "autoload": {
        "psr-4": {
            "CommonGateway\\PetStoreBundle\\": "src/"
        }
    }
}

```

### Publishing your plugin
The gateway used the packagist netwerk for plugin discovery, that means that you do not need to upload your plugin to an appstore etc. You simply [submit your plugin repository to packagist](https://packagist.org/packages/submit).

> Note:
> It is also possible to keep your plugins private, read more about that under {private packages](https://packagist.com/)

> Warning:
> Plugins are only finable if they adhere to all of the description requirements.


### Requiring other plugins









