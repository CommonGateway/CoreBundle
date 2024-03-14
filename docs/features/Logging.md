# Logging

> **Warning**
> This file is maintained at the Conduction [Google Drive](https://docs.google.com/document/d/1niVyNcIiOiAbq_lgbczPlfbNj-9yTdCpdkMDEXEGgnc/edit). Please make any suggestions or alterations there.

The gateway uses Symfony’s [monolog bundle](https://symfony.com/doc/current/logging.html) for logging and provides several channels for logging.

## Channels
By default, the following channels are provided by the gateway, but plugins might add their own channels by including a `monolog.yaml` in their configuration. Channels represent the part of the gateway that has created the log and are used to separate logs by category.
- deprecation
- endpoint
- request
- schema
- cronjob
- action
- object
- synchronization
- plugin
- cache
- object
- call
- installation
- mapping

> **Note:** Deprecations are logged in the dedicated "deprecation" channel when it exists

## Log levels

The gateway uses the following log/error levels conform [RFC 54240](https://datatracker.ietf.org/doc/html/rfc5424)

| Name      | Description                                                                                              |
|-----------|----------------------------------------------------------------------------------------------------------|
| DEBUG     | Detailed debugging information.                                                                          |
| INFO      | Handles normal events. Example: SQL logs.                                                                |
| NOTICE    | Handles normal events, but with more important events.                                                   |
| WARNING   | Warning status, where you should take action before it becomes an error.                          |
| ERROR     | Error status, where something is wrong and needs immediate action.                                  |
| CRITICAL  | Critical status. Example: System component is not available.                                             |
| ALERT     | Immediate action should be exercised. This should trigger some alerts and wake you up during nighttime. |
| EMERGENCY | It is used when the system is unusable.                                                                  |

## Data
The gateway uses a [dataprocessor](https://symfony.com/doc/current/logging/processors.html) to add additional data to a log. The following data is automatically added (if available through the session object).

| Name            | Description                                                                                                                                                                                             |
|-----------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| host            | The host domain of the request made.                                                                                                                                                                    |
| ip              | The IP of the request made.                                                                                                                                                                             |
| session         | The current session id.                                                                                                                                                                                 |
| process         | The current process id.                                                                                                                                                                                 |
| endpoint        | The id of the endpoint where the call landed.                                                                                                                                                           |
| schema          | The id of the schema that was used during the session.                                                                                                                                                  |
| cronjob         | The id of the currently running cronjob.                                                                                                                                                                |
| action          | The id of the currently running action.                                                                                                                                                                 |
| mapping         | The id of the mapping that was used/done last.                                                                                                                                                          |
| user            | The user that made the request.                                                                                                                                                                         |
| organization    | The current organization of the user/application that made the request.                                                                                                                                 |
| application     | The application that made the request.                                                                                                                                                                  |
| source          | The id of the source that was accessed by the process.                                                                                                                                                  |
| object          | The id of the object that was used during the session.                                                                                                                                                  |
| plugin          | The plugin where the code that triggered the log creation originated from.                                                                                                                              |
| event           | Not yet implemented...                                                                                                                                                                                  |
| synchronization | Not yet implemented...                                                                                                                                                                                  |
| headers         | The headers of the request. Not yet implemented...                                                                                                                                                      |
| command         | The command options and input that triggered the call, [based on](https://github.com/symfony/symfony/blob/6.2/src/Symfony/Bridge/Monolog/Processor/ConsoleCommandProcessor.php). Not yet implemented... |

> **Note:** The plugin identifier is not automatically added to logs, but plugins are required to add that value themselves so that logs are easily traced back to a specific plugin.

> **Note:** PHP will lowercase the incoming headers (for HTTP/1), or the HTTP client will lowercase the headers (for HTTP/2). According to RFC 7230, headers in HTTP/1 are case-insensitive, and according to RFC 7540, headers in HTTP/2 may only be lowercase. This means that the headers can only be found in lowercase in the logs.

Normally speaking a call should be started by either a cronjob, command, or endpoint.

## Storage
By default, the gateway stores logs to `{channel name}.log` files in the log directory of the gateway, prints logs out to STD and saves them to the Mongo database. When storing the logs to mongoDB all logs are stored in a single collection to make them easily searchable.

## Retrieving logs
Logs can be retrieved through the `admin/logs` endpoint that provides them from the mongo database.

## Mail when logs are created
It is possible to configure an Action ( See [Events.md](Events.md)) in such a way that on log creation an email is sent automatically. For this, your Action needs to listen to the Gateway event `commongateway.log.create` and needs to be handled by the ActionHandler named `EmailHandler`.

> **Note:** You will need the [CustomerNotificationsBundle](https://github.com/CommonGateway/CustomerNotificationsBundle) in order to use the EmailHandler for sending emails.

> **Note:** The event `commongateway.log.create` is not thrown for logs with [log level](#log-levels) `DEBUG`, `INFO`, `NOTICE`, or `WARNING`.

In order to only send emails for specific logs you can use the Action conditions (using [JsonLogic](https://jsonlogic.com)). Here is an example of these conditions:

```json
{
  "and": [
    {
      "==": [
        {
          "var": "level_name"
        },
        "ERROR"
      ]
    },
    {
      "==": [
        {
          "var": "channel"
        },
        "call"
      ]
    },
    {
      "or": [
        {
          "==": [
            {
              "var": "context.source"
            },
            "0a3dacde-a5b4-4d7f-9e17-0a39c515c191"
          ]
        },
        {
          "==": [
            {
              "var": "context.source"
            },
            "b40ddf2d-e553-4e1f-87dd-4c2c7d224d8a"
          ]
        }
      ]
    },
    {
      "!": {
        "in": [
          "Stack trace",
          {
            "var": "message"
          }
        ]
      }
    }
  ]
}
```

> **Note:** All properties found in [Data](#data) are added to the `context` array, see the example above for `context.source`.

## Creating logs from your plugin
You might feel the need to create additional logging for your plugin because it does some extremely important stuff that just *might* go wrong and then needs to be fixed. There are basically 3 ways of going about this

1. There is an appropriate channel (for example actions for an action handler that you have built). Use that! Conforming yourself to existing logging means that your logs will be automatically available through the gateways tool like the Gateway UI and Grafana.
2. Use the plugin channel, that is what it is for, any undefined logs can be stored there.
3. Create your own channel by expanding monolog.yaml from your plugin, this is almost never the preferred option. It gives you great flexibility in how to use the logs, but it leaves the context of the gateway making your logs unpredictable for those who need them the most, your users.

You **SHOULD** always log from services, not just because it is easier, but because following separation of concern your business logic (and therefore the stuff you want to log) should be contained there anyway. It does however also make it very easy to add logging to your service through [autowiring](https://symfony.com/doc/current/logging/channels_handlers.html#monolog-autowire-channels). When adding a logger to your action handlers you can for example include the action channel like:

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

After this creating a log is rather easy, just use  `$this->logger->{level}({message})` e.g.

```php
$this->logger->info('I just got the logger');
$this->logger->error('An error occurred);
```

Keep in mind that if you want your logs to be findable and accessible through the Gateway UI you should also include your plugin package name as an extra value e.g.

```php
$this->logger->info('I just got the logger',["plugin"=>"common-gateway/pet-store-bundle"]);
```

> **Note:** It is actually possible to register your logs under the wrong plugin, do not do this. First of all, it will confuse your users and be traceable through the process id. Secondly, it will be flagged as a code injection, attract attention, and be reported.
