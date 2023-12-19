# Events


> **Warning**
> This file is maintained at the Conduction [Google Drive](https://docs.google.com/document/d/1aeNZ9I8H4iq2XigByu96lJSe3Cw-lMcWx8bcuJBHxcE/edit). Please make any suggestions or alterations there.

The Common Gateway is based on event-driven architecture, meaning that all code and functionality are loosely coupled (see booth architecture and code quality). That means that at no point during the execution of business logic, a functionality should directly call a different functionality. This might seem complicated (and at times it is) but it provides two important benefits:

- It allows us to divide the work of executing code among several “worker” containers (read more). Providing an extreme performance boost on production environments on heavy load business logic.
- It allows all interested parties to develop plugins for the Common Gateway that directly hook into and extend the core functionality.

## Triggers

Event-driven architecture uses events to trigger and communicate between services (some functionality from the codebase). A good example of this is an endpoint.  The gateway detects if a user or application approaches an endpoint (e.g., `api/pets`) and sets an event on the stack. Events always consist of a unique trigger of the type `string` In this case `commongateway.endpoint` and an array of data (in this case the request information like method en body). We call this throwing an event. Other good examples of triggers are :
cronjobs,
Object changes(e.g., CRUD actions)changes in objects (e.g. CRUD actions).

## Actions

Actions are preconfigured sets of business logic that “listen” for one or more events to be thrown and then execute code. The [ActionHandler](Action_handlers.md) contains the executable code.

Actions primarily consist of three things:
- The events it listens to
- The action handler that should be used to handle the action
- Configuration for that action handler

Storing the configuration for the action handler in the actual action means that actionHandlers can be reused. 
An example would be the mail actionHandler provided by the [CustomerNotificationsBundle](https://github.com/commonGateway/customernotificationsBundle). 
It can be used by actions hooking into the new user event to send a welcome email to new users AND by actions hooking into the logger event to email the gateway admin whenever errors occur.

### Chaining actions

Additionally, Actions can throw events themselves. You can build simple flows using this typical pattern (called chaining). Currently, the gateway isn’t a full-blown BPMN engine and should not be used that way. It is however possible to integrate the BPMN engine into gateway flows using custom plugins (we are still looking for a sponsor for a Camunda or Flowable plugin).

## Event list

Events can't be pre-defined as they come into existence once a service throws them. You can define your own events through either:
the gateway UI
admin API.
Events should logically be namespaces and use dot notation. The namespace commongateway is reserved for core functionality

The gateway subscribes to the following events by default.

| Name                                  | When                                                                                                     | Data                                                                                |
|---------------------------------------|----------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|
| commongateway.object.pre.create       | Before an object is created in the database                                                              | ["object"=>{array representation of object},"entity"=>{uuid of the objects entity}] |
| commongateway.object.post.create      | After an object is created in the database                                                               | ["object"=>{array representation of object},"entity"=>{uuid of the objects entity}] |
| commongateway.object.post.read        | After an object is read from the database                                                                | ["object"=>{array representation of object},"entity"=>{uuid of the objects entity}] |
| commongateway.object.pre.update       | Before an object is updated in the database                                                              | ["object"=>{array representation of object},"entity"=>{uuid of the objects entity}] |
| commongateway.object.post.update      | After an object is updated in the database                                                               | ["object"=>{array representation of object},"entity"=>{uuid of the objects entity}] |
| commongateway.object.pre.delete       | Before an object is deleted in the database                                                              | ["object"=>{array representation of object},"entity"=>{uuid of the objects entity}] |
| commongateway.object.post.delete      | After an object is deleted in the database                                                               | []                                                                                  |
| commongateway.object.pre.flush        | Before the work of the entity manager is transferred to the database                                     | []                                                                                  |
| commongateway.object.post.flush       | After the work of the entity manager is transferred to the database                                      | []                                                                                  |
| commongateway.installer.pre.upgrade   | Before the installer upgrades                                                                            | []                                                                                  |
| commongateway.installer.post.upgrade  | After the installer upgrades                                                                             | []                                                                                  |
| commongateway.initilizer.pre.upgrade  | Before the initializer upgrades                                                                          | []                                                                                  |
| commongateway.initilizer.post.upgrade | After the initializer upgrades                                                                           | []                                                                                  |
| commongateway.plugin.pre.install      | Before the plugin is installed                                                                           | []                                                                                  |
| commongateway.plugin.post.install     | After the plugin is installed                                                                            | []                                                                                  |
| commongateway.plugin.pre.upgrade      | Before the plugin is upgraded                                                                            | []                                                                                  |
| commongateway.plugin.post.upgrade     | After the plugin is upgraded                                                                             | []                                                                                  |
| commongateway.plugin.pre.remove       | Before the plugin is removed                                                                             | []                                                                                  |
| commongateway.plugin.post.remove      | After the plugin is removed                                                                              | []                                                                                  |
| commongateway.log.create              | After a monolog log is created that doesn't have log level `DEBUG`, `INFO`, `NOTICE` or `WARNING`. | All data of the log, see [Logging.md](Logging.md) for more information.             |



## Design your own triggers, events, actions, and action handlers

When adding your customizations to the Common Gateway, you should always follow the separation of concerns:

keep flows small (don’t try to do too much in one flow)
keep functionality ([actionHandlers](Action_handlers.md)) minimal

For complex scenarios, consider using several chained actionHandlers.
When adding your own flavor to the common gateway you should always follow separation of concerns.

In other words, keep flows small, don’t try to do too much from a single flow, and keep your actionHandlers minimal. If things get more complex consider using several chained action handlers.

> **ALWAYS** use the `[vendor].[plugin].[action].[sub action]` naming pattern for your events to prevent conflicts with other events. When adding events on an installation or app basis use: either the app (e.g `app..[action].[sub action]`) or cron (e.g. `cron.[action].[sub action]`) namespace patterns to keep your events recognizable.

