> **Warning**
> This file is maintained at Conduction’s [Google Drive](https://docs.google.com/document/d/1qNErKlzI5LfjoK68COdKNcElydBz2PbSpz6J-hHL2ms/edit) Please make any suggestions of alterations there.

# Action Handlers

This is a placeholder text, for now see [Events](Events.md) for more detailed information about actions & events.

## Actions

Actions are preconfigured sets of business logic that “listen” for one or more [events](Events.md) to be thrown and then execute code. The ActionHandler contains the executable code.

Actions primarily consist of three things:
The events it listens to
The action handler that should be used to handle the action
Configuration for that action handler

Storing the configuration for the action handler in the actual action means that actionHandlers can be reused. An example would be the mail actionHandler provided by the [CustomerNotificationsBundle](https://github.com/commonGateway/customernotificationsBundle). It can be used by actions hooking into the new user event to send a welcome email to new users AND by actions hooking into the logger event to send an email to the gateway admin whenever errors occur.

### Chaining actions

Additionally, Actions can throw events themselves. You can build simple flows using this typical pattern (called chaining). Currently, the gateway isn’t a full-blown BPMN engine and should not be used that way. It is however possible to integrate the BPMN engine into gateway flows using custom plugins (we are still looking for a sponsor for a Camunda or Flowable plugin).

## Using existing action handlers

to do

## Creating your own action handlers

to do