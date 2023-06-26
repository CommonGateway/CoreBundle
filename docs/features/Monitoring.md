# Monitoring

> **Warning**
> This file is maintained at the Conduction [Google Drive](https://docs.google.com/document/d/1guerprkkQgqTqMVEy9xQnyhRP8ME8lHVP0Ogy0_GkYM/edit). Please make any suggestions or alterations there.

As a gateway, the Common Gateway sits at the front of your application landscape, acting as the main entry point for all incoming requests. This central position makes it a prime location for collecting valuable data about the health, performance, and behavior of your application ecosystem.

Monitoring is vital to the Common Gateway for several reasons:

1. **Performance Monitoring**: By tracking metrics like request duration, rate, and error rates, you can gain insights into the performance of your application. This can help you identify bottlenecks, understand capacity needs, and ensure that your application is performing optimally.

2. **Health Checks**: Health checks provide a way to quickly detect and respond to issues that could impact the availability or performance of your services. These checks can be used to trigger alerts, ensuring that you can respond quickly when problems arise.

3. **Debugging and Troubleshooting**: When issues do arise, having detailed metrics at your disposal can be invaluable for diagnosing and resolving the problem. Prometheus's querying language PromQL can be used to slice and dice the data in various ways, making it easier to understand the root cause of an issue.

4. **Capacity Planning and Scaling**: By tracking the load on your services, you can make informed decisions about when to scale up or down, helping you manage costs and ensure sufficient capacity to handle your traffic.

## Prometheus
The Common Gateway supports Prometheus monitoring through its MetricsService. This service exposes a /metrics endpoint that Prometheus can scrape to collect metrics about the operation of the Common Gateway. This makes it easy to integrate the Common Gateway with Prometheus, allowing you to benefit from the rich insights that Prometheus can provide about the health and performance of your gateway and the services behind it.

Prometheus is an open-source systems monitoring and alerting toolkit that is widely adopted for its simplicity and effectiveness. It collects metrics from monitored targets by scraping metrics HTTP endpoints on these targets.

## Supported Metrics
The common gateway supports several metrics

### General information
| Name | Type | Help |
|------|------|------|
| app_version | gauge | The current version of the application. |
| app_name | gauge | The name of the current version of the application. |
| app_description | gauge | The description of the current version of the application. |
| app_users | gauge | The current amount of users |
| app_organisations | gauge | The current amount of organisations |
| app_applications | gauge | The current amount of applications |
| app_requests | counter | The total amount of incoming requests handled by this gateway |
| app_calls | counter | The total amount of outgoing calls handled by this gateway |


### Errors
| Name | Type | Help |
|------|------|------|
| app_error_count | counter | The amount of errors, this only counts logs with level_name 'EMERGENCY', 'ALERT', 'CRITICAL' or 'ERROR'. |
| app_error_list | counter | The list of errors and their error level/type. |

### Objects
| Name | Type | Help |
|------|------|------|
| app_objects_count | gauge | The amount objects in the data layer |
| app_cached_objects_count | gauge | The amount objects in the data layer that are stored in the MongoDB cache |
| app_schemas_count | gauge | The amount defined schemas |
| app_schemas | gauge | The list of defined schemas and the amount of objects. |

### Plugins
| Name | Type | Help |
|------|------|------|
| app_plugins_count | gauge | The amount of installed plugins |
| app_installed_plugins | gauge | The list of installed plugins. |


