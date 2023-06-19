# Features

Welcome to the feature page for the Common Gateway, a multi-faceted platform designed with flexibility and interoperability in mind. The Common Gateway caters to four main use cases, each enhancing the other to provide a comprehensive, unified solution for diverse data management needs. Here's a brief overview:

## Usse cages

### 1. API Gateway

The Common Gateway can function as an API Gateway, acting as a single entry point for multiple APIs. This streamlines the management of APIs, providing consistent routing, security, and other necessary features. This simplifies client-side interactions and consolidates all your API requirements under one roof.

### 2. Web Gateway

Beyond forwarding APIs, the Common Gateway can serve as a robust Web Gateway. It provides API support for applications while also handling user onboarding, management, and authentication. This dual functionality ensures seamless user experience and secure data access, enhancing application reliability and performance.

### 3. Integration Platform

The Common Gateway shines as an Integration Platform, harmonizing multiple data sources and transforming non-API sources, such as Excel files, into APIs. This capability enables easy data integration, simplifying the creation of a unified view of data from various sources. It paves the way for more efficient data processing and analysis, fostering data-driven decision-making.

### 4. Federated Network Provider

Finally, the Common Gateway facilitates a federated network, enabling cross-organizational querying of data sources. This feature allows different organizations to share access to specific data while maintaining control over their own systems. It boosts collaboration, data sharing, and multi-organizational integration without compromising system autonomy.

By combining these use cases, the Common Gateway provides a versatile solution for managing, integrating, and utilizing data across various sources and platforms. It's the perfect tool to elevate your data strategy and drive your business towards a more connected, data-informed future. Stay tuned to explore each of these features in depth.

## Functionality

### 1. Authentication

Authentication is a crucial aspect of securing the gateway, validating the identity of clients such as users, devices, or other applications before granting access to system resources. There are numerous methods for applications to authenticate themselves, including application keys, JWT tokens, ZGW JWT tokens, two-way SSL, IP and domain whitelisting. However, each method carries its own use cases and security considerations. For user authentication, the system can either utilize an Integrated Identity Provider (IdP), which manages identity information within a federation, or an External Identity Provider, a third-party service that allows users to authenticate with a single set of externally stored credentials. The choice of method depends on the specific requirements and contexts of use.

### 2. Authorization

Authorization in the Common Gateway project is managed based on Role-Based Access Control (RBAC). Users and applications are assigned roles, or "groups", each associated with a set of permissions, or "scopes". These groups can inherit scopes from other groups, creating a hierarchical organization of scopes. Scopes are defined based on the CRUD (Create, Read, Update, Delete) operations and can be applied to entire system aspects or specific objects. The system also distinguishes between the roles of an 'Owner' and a 'Creator', each having specific rights and limitations. Multitenancy is a key concept in the Common Gateway project, allowing multiple independent instances of users and applications to operate within the same environment while maintaining secure access to their respective objects. This is implemented at the organization level, and users and applications can only interact with objects that belong to the same organization. Multitenancy is maintained through either a single database setup or a multiple database setup.

### 3. Datalayer

The data layer in the Common Gateway project acts as a hybrid of an index and a data lake, normalizing data from various sources and facilitating sophisticated searches across databases, APIs, and files. It uses schemas as Entity-Attribute-Value (EAV) objects to provide a uniform view of data, regardless of its original source or format. However, it is not a source of truth but serves as a facilitator through a mechanism called "smart caching," which ensures the most current data is provided. The data layer also allows for the extension of data models by attaching additional properties to objects, enabling greater flexibility and versatility in data use and analysis.

### 4. Logging

The Common Gateway project uses Symfony's Monolog bundle for logging, offering several channels for logs and multiple error levels following the RFC 54240 standard. Channels categorize logs, and plugins can add their own channels. Logs can also contain additional data like session ID, user, application, and more, provided through a dataprocessor. Plugins are required to add their identifier to logs for traceability. Logs are stored in the gateway's log directory, printed to standard output, and saved in a MongoDB database for easy searchability. Log retrieval is possible through the admin/logs endpoint. Plugins can create additional logs, and they're advised to use existing channels or the plugin channel, or, if necessary, create their own channel. Logs should be made from services following separation of concern, and should include the plugin package name for findability and accessibility.

### 5. Mappings

The Mappings feature in the CommonGateway/CoreBundle project supports the process of transforming the structure of an object when the source data doesn't match the desired data model. This transformation is accomplished by a series of mapping rules in a "To <- From" style. In simple terms, mapping changes the position of a value within an object​1​.

### 6. Notifications

### 7. Plugins

The Common Gateway's plugin system provides a method of keeping client-specific code separated from the core functionality. This structure is based on the Symfony bundle system, making the Common Gateway easily extensible. Essentially, all Common Gateway plugins are Symfony bundles, and vice versa, allowing for a configuration set that can extend a base Gateway's functionality​1​.

### 8. Schema's

Schemas are central to the Common Gateway's data layer. They define and model objects, setting the conditions for these objects. Each object in the gateway is associated with a single schema. These schemas follow the JSON schema standard, making them interchangeable with OAS3 schemas. Schemas are akin to "tables" in traditional databases as they store data in a structured manner. However, unlike tables, data is stored as objects, each akin to a dataset or row in a traditional table, but capable of multidimensional storage, such as containing arrays or other objects. This object-oriented approach provides a much more flexible way of serving data. Furthermore, schemas define the properties of objects and also set conditional validations for the value of each property​1​.

### 9. Security

The Common Gateway integrates security into the core of its development process. As part of its Continuous Integration and Continuous Deployment (CI/CD) pipeline, the platform employs automated penetration testing and scanning. This approach allows potential security vulnerabilities to be identified and addressed during the early stages of development, rather than later in the production phase​1​.

### 10. Sources

The "Sources" feature in Common Gateway represents the locations where the Gateway obtains its data. These sources are typically other APIs, but the gateway can also connect to file servers through (S)FTP, directly connect to databases (like MongoDB, PostgreSQL, MySQL, Oracle, and MSSQL), blockchain solutions, or networks of trust such as NLX/FCS. The source object provides information about the source, including its status, transaction logs, and the current connection settings. Sources can be found through the sources menu item on the left side of the Admin UI or the admin/sources endpoint on the gateway API​1​.

### 11. Synchronization

The data synchronization process in Common Gateway is an essential component of its data layer, ensuring data consistency between the data layer and an external source. The process starts with determining the current state of data where three main scenarios exist: the object exists on the Gateway but not on the source, the object exists on the source but not on the Gateway, and the object exists on both the source and Gateway. Appropriate actions are taken depending on the scenario, such as deleting, adding, or updating the object. In situations where it's unclear which version is newer, a source of truth is determined, typically the source, but it can be configured to be the Gateway. The next step is to create a synchronization object that describes the relationship between the two objects (Data Layer and Source), containing details like the IDs on both ends, the dates of changes, and a hash of the source object. This object is essential for detecting changes in the source object and ensuring data consistency across the Gateway and the source, enhancing the reliability and integrity of the system​1​.

### 12. Twig

### 12. Federialization
