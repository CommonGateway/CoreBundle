# Common Gateway

Introducing the Common Gateway - a cutting-edge, advanced technology platform designed to enhance data interaction and exchange for local governments. The Common Gateway is a critical tool for the seamless provision of data to Common Ground services, ensuring smooth, efficient, and secure data transactions.

At its core, the Common Gateway is a plugin-based system, allowing for flexible and customizable operations. It is capable of handling multiple plugins, each with its own unique set of functionalities, encapsulated in the form of installation.json files. The platform comes with a robust Installation Service that can read these installation.json files, understand the plugin's requirements, and create basic schema's accordingly.

But the Common Gateway is more than just a tool for data exchange. It's a comprehensive solution designed to handle a multitude of request types and generate appropriate responses. Whether it's an HTTP GET, PUT, POST, or a user downloading a file, the Gateway is equipped to handle it all. It identifies the endpoint, processes the request, and generates an appropriate response, ensuring that any incoming request can be accurately interpreted and handled.

Moreover, the Gateway follows an API-first approach, developing APIs that are consistent and reusable. It also follows the principles of Role-Based Access Control (RBAC), granting permissions to users and applications based on their roles. This ensures a secure and controlled environment for data exchange.

This is a transformative approach to data management, enabling governments to maintain control over their own data while allowing for efficient communication with Common Ground services. It's not just about data exchange, but also about creating an ecosystem where information is democratized, access is broadened, and operations are streamlined.

The Common Gateway is built with the future in mind, designed to adapt and grow with the needs of local governments and the communities they serve. It brings the power of data exchange to your fingertips, providing a platform that is as intuitive as it is powerful.

Embrace the future of data exchange with Common Gateway. It's time to revolutionize the way your local government handles data, enhancing efficiency, security, and adaptability. With the Common Gateway, you're not just preparing for the future, you're shaping it.

## Documentation
Since documentation for a technical application like the common gateway can be a bit overwhelming, we decided to spread it out in different levels of technical difficulty

1. (Non-technical) Aimed at product owners and interested parties with no to little technical background: [The product page]().
2. (A bit technical) Aimed at architects and engineers: [The read the docs page](https://commongateway.readthedocs.io/en/latest/).
3. (Technical) Aimed at developers want to build plugins or use the gateway as backend: [Readme files in the codebase](/docs).
4. (Very technical) Aimed people who want to improve and extend the code base: [In code documentations](/src).

If you don't like to read documentation online there is also a complete [manual](https://raw.githubusercontent.com/CommonGateway/CoreBundle/feature/documentation/docs/manual.pdf) available in pdf.


### Features:
* [Action_handlers](/docs/features/Action_handlers.md)
* [Api](/docs/features/API.md)
* [Architecture](/docs/features/Architecture.md)
* [Authentication](/docs/features/Authentication.md)
* [Authorization](/docs/features/Authorization.md)
* [Code_quality](/docs/features/Code_quality.md)
* [Commands](/docs/features/Commands.md)
* [Cronjobs](/docs/features/Cronjobs.md)
* [Datalayer](/docs/features/Datalayer.md)
* [Design_decisions](/docs/features/Design_decisions.md)
* [Endpoints](/docs/features/Endpoints.md)
* [Events](/docs/features/Events.md)
* [Features](/docs/features/Features.md)
* [Federalization](/docs/features/Federalization.md)
* [File imports](/docs/features/FileUpload.md)
* [Import & export](/docs/features/ImportExport.md)
* [Logging](/docs/features/Logging.md)
* [Mappings](/docs/features/Mappings.md)
* [Monitoring](/docs/features/Monitoring.md)
* [Notifications](/docs/features/Notifications.md)
* [Plugins](/docs/features/Plugins.md)
* [Schemas](/docs/features/Schemas.md)
* [Security](/docs/features/Security.md)
* [Sources](/docs/features/Sources.md)
* [Synchronizations](/docs/features/Synchronizations.md)
* [Twig](/docs/features/Twig.md)

### Services:
* [AuthenticationService](/docs/classes/Service/AuthenticationService.md)
* [CacheService](/docs/classes/Service/CacheService.md)
* [CallService](/docs/classes/Service/CallService.md)
* [ComposerService](/docs/classes/Service/ComposerService.md)
* [DownloadService](/docs/classes/Service/DownloadService.md)
* [EavService](/docs/classes/Service/EavService.md)
* [EndpointService](/docs/classes/Service/EndpointService.md)
* [FileService](/docs/classes/Service/FileService.md)
* [FileSystemCreateService](/docs/classes/Service/FileSystemCreateService.md)
* [FileSystemHandleService](/docs/classes/Service/FileSystemHandleService.md)
* [GatewayResourceService](/docs/classes/Service/GatewayResourceService.md)
* [InstallationService](/docs/classes/Service/InstallationService.md)
* [MappingService](/docs/classes/Service/MappingService.md)
* [MetricsService](/docs/classes/Service/MetricsService.md)
* [NotificationService](/docs/classes/Service/NotificationService.md)
* [OasService](/docs/classes/Service/OasService.md)
* [ObjectSyncService](/docs/classes/Service/ObjectSyncService.md)
* [RequestService](/docs/classes/Service/RequestService.md)
* [SchemaService](/docs/classes/Service/SchemaService.md)


The online Read the Docs can be found [here](https://commongateway.readthedocs.io)

To have the Common Gateway CoreBundle documentation locally, please follow the instructions below:

*   install [mkdocs](https://www.mkdocs.org/#installation)
*   clone the [CoreBundle]()
*   run `mkdocs serve` in the CoreBundle directory
*   open <http://localhost:8000> in your browser

## Contributing

If you want to contribute to the documentation, please follow the instructions below:
