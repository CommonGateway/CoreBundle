#Logging

> **Warning**
> This file is maintained at the Conduction [Google Drive](https://docs.google.com/document/d/1niVyNcIiOiAbq_lgbczPlfbNj-9yTdCpdkMDEXEGgnc/edit). Please make any suggestions or alterations there.

The gateway uses symfony’s [monolog bundle](https://symfony.com/doc/current/logging.html) for logging, and provides several channels for logs.

## Channels
By default, the following channels are provided by the gateway, but plugins might add their own channels by including a `monolog.yaml` in their configuration. Channels represent the part of the gateway that has created the log and are used to separate logs by category.
endpoint
request
schema
cronjob
action
object
synchronization
plugin
composer
installation
mapping
call

## Error levels

The gateway uses the following error levels conform [RFC 54240](https://www.rfc-editor.org/rfc/rfc54240
DEBUG: Detailed debugging information.
INFO: Handles normal events. Example: SQL logs
NOTICE: Handles normal events, but with more important events
WARNING: Warning status, where you should take an action before it will become an error.
ERROR: Error status, where something is wrong and needs your immediate action
CRITICAL: Critical status. Example: System component is not available
ALERT: Immediate action should be exercised. This should trigger some alerts and wake you up during night time.
EMERGENCY: It is used when the system is unusable.

## Data
The gateway uses a [dataprocessor](https://symfony.com/doc/current/logging/processors.html) to add additional data to a log. The following data is automatically added (if available through the session object).
session: The session id
user: The user that made the request
application: The application that made the request
organization: The organization of that application
source: The source that was accessed by the process
endpoint: The id of the endpoint where the call landed
schema: The schema that was used during the session
action: The currently running action
object
event:
synchronization
cronjob: The cronjob that triggered the call
command: The command options and input that triggered the call, [based on](https://github.com/symfony/symfony/blob/6.2/src/Symfony/Bridge/Monolog/Processor/ConsoleCommandProcessor.php)

The plugin identifier is not automatically added to logs, but plugins are required to add that value themself so that logs are easily traced back to an specific plugin

Normally speaking a call should be started by either a cronjob,command or endpoint,

## Storage
By default, the gateway stores logs to `{channel name}.log` files in the log directory of the gateway, prints logs out to STD out and saves them to the Mongo database. When storing the logs to mongoDB all logs are stored to a single collection to make them easily searchable.

## Retrieving logs
Logs can be retrieved through the `admin/logs` endpoint that provides them from the mongo database.

## Creating logs from your plugin
You might feel the need to create additional logging for your plugin because it does some extremely important stuff that just *might* go wrong and then needs to be fixed. There are basically 3 ways of going about this

1 - There is an appropriate channel (for example actions for an action handler that you have built). Use that! Conforming yourself to existing logging means that your logs will be automatically available through the gateways tool like the admin ui and grafana.
2 - Use the plugin channel, that is what it is for, anny undefined logs can be stored there.
3 - Create your own channel by expanding monolog.yaml from your plugin, this is almost never the preferred option. It gives you great flexibility in how to use the logs, but it leaves the context of the gateway making your logs unpredictable for those who need them the most, your users.

You **SHOULD** always log from services, not just because it is easter, but because following separation of concern your business logic(and therefore the stuff you want to log) should be contained there anyway. It does however also make it very easy to add logging to your service trough [autowiring](https://symfony.com/doc/current/logging/channels_handlers.html#monolog-autowire-channels). When adding a logger to your action handlers you can for example include the action channel like:

```php
…
use Psr\Log\LoggerInterface
… 

public function __construct(LoggerInterface $ActionLogger)
{
    $this->logger = $ActionLogger;
}
```

Or when using the generic plugin channel

```php
…
use Psr\Log\LoggerInterface
… 

public function __construct(LoggerInterface $PluginLogger)
{
    $this->logger = $PluginLogger;
}
```

After this creating a log is rather easy, just use  `$this->logger->{level}({message}) e.g.

```php
$this->logger->info('I just got the logger');
$this->logger->error('An error occurred');

```

Keep in mind that if you want your logs to be findable and accessible through the admin ui you should also include your plugin package name as an extra value e.g.

```php
$this->logger->info('I just got the logger',[“plugin”=>”common-gateway/pet-store-bundle”]);

```

**note:** It is actually possible to register your logs under the wrong plugin, don’t. First of all it will confuse your users and be traceable through the process id. Secondly it will be flagged as a code injection attract and reported. 
