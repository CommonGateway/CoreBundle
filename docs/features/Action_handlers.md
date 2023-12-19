> **Warning**
> This file is maintained at Conduction’s [Google Drive](https://docs.google.com/document/d/1qNErKlzI5LfjoK68COdKNcElydBz2PbSpz6J-hHL2ms/edit) Please make any suggestions of alterations there.

# Action Handlers

The Common Gateway is based on event-driven architecture, meaning that all code and functionality are loosely coupled (see both [architecture](Architecture.md) and [code quality](Code_quality.md)).
Action handlers provide a way to execute specific business logic when a specific trigger ([Common Gateway Event](Events.md)) occurs.  

## Actions

Actions are preconfigured sets of business logic that “listen” for one or more [events](Events.md) to be thrown and then execute code. The ActionHandler contains the executable code.

Actions primarily consist of three things:
- The events it listens to
- The Action handler that should be used to handle the Action
- Configuration for that action handler

Storing the configuration for the action handler in the actual Action means that ActionHandlers can be reused. 
An example would be the mail ActionHandler provided by the [CustomerNotificationsBundle](https://github.com/commonGateway/customernotificationsBundle). 
It can be used by Actions hooking into the new user event to send a welcome email to new users AND by Actions hooking into the logger event to send an email to the gateway admin whenever errors occur.

### Chaining actions

Additionally, Actions can throw events themselves. You can build simple flows using this typical pattern (called chaining). Currently, the gateway isn’t a full-blown BPMN engine and should not be used that way. It is however possible to integrate the BPMN engine into gateway flows using custom plugins (we are still looking for a sponsor for a Camunda or Flowable plugin).

## Using existing action handlers

With any ActionHandler comes the possibility to creating a Common Gateway Action for it.
Actions can be created in a few different ways:
- With the Common Gateway admin UI. _Tab 'Actions' in the sidebar._
- By including your Action directly in the [installation files](Plugins.md#adding-core-schemas-to-your-plugin) of the bundle ([Common Gateway plugin](Plugins.md)) you are working with.
- Use an API-platform tool like Postman to directly POST (, PATCH or UPDATE) your Action on the Common Gateway you are working with.

Creating Actions with the Gateway UI is easy and straight forward, but in some cases the configuration of an Action is to complex for the Gateway UI to handle.
In these cases you will probably need to look into one of the other two options.

In order to understand Actions better please read here which properties any Action should at least have:
- A `name`, your Action is going to need a name.
- A `reference` (`$id` in an action.json installation file), each Action needs a unique reference URL starting with `https://{your-domain}/action/{short-name-for-your-bundle}` and ending with `.action.json`, something like: `"https://commongateway.nl/action/notifications.ZaakCreatedEmailAction.action.json"`
- A `version`, for example: "0.0.1". This helps when you want to keep track of versions but also makes it possible to upgrade your plugin installation files later.
- A `listens` array, each Action needs to listen to one or more [Common Gateway events](Events.md). You can add these to the `listens` array of your Action.
- Some [JsonLogic](https://jsonlogic.com/) `conditions` that will be compared to the Action data, these conditions determine when your Action should be triggered. Use `{"==": [1, 1]}` for 'always true'.
- The `class`, this should be the ActionHandler you want to use, for example: `"CommonGateway\\CustomerNotificationsBundle\\ActionHandler\\EmailHandler"`.
- A `configuration` array containing specific configuration for your Action. The possible (and required) properties to add here differ per Action, this is defined in the ActionHandler.

Actions can also have some other optional properties:
- `description` The description of your Action.
- `throws` Similar as the listens array, but this is an array of [Common Gateway events](Events.md) triggers this Action wil throw after the Action is done running.
- `priority` The priority of the Action, higher priority Actions will be run before lower priority Actions. By default, this is set to 1.
- `async` A boolean, if set to true the Action will be run asynchronous. By default, this is false.
- `userId` The userId of a user. This user will be used to run this Action for, if there is no logged-in user. This helps when, for example: setting the owner & organization of newly created Object while running this Action. 
- `isLockable` A boolean, if set to true the Action can only run again if the last run has been completed. By default, this is false. 
- `isEnabled` A boolean, if set to false the Action will never run. By default, this is true.

[Here](https://github.com/CommonGateway/PetStoreBundle/blob/main/Installation/Action/ps.EmailActionExample.action.json) is an example of an Action for the CustomerNotificationsBundle [EmailHandler](https://github.com/CommonGateway/CustomerNotificationsBundle/blob/main/src/ActionHandler/EmailHandler.php).

> **Note:** For more examples see the "/Installation/Action" & "/src/ActionHandler" folders of one of the [other CommonGateway Bundles](https://github.com/orgs/CommonGateway/repositories?q=bundle&type=all&language=&sort=).

## Creating your own action handlers

to do, say something about Service containing BL code and that handlers should not contain BL. 
