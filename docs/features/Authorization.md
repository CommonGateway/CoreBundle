# Authorization

> **Warning**
> This file is maintained at the Conduction [Google Drive](https://docs.google.com/document/d/10Puo6zlEq_Ja9ps7MBYcyvtbbQrroaWvWJhVUMhdlY0/edit). Please make any suggestions of alterations there.

This document explains how authorization is managed in the Common Gateway project, following the principles of Role-Based Access Control (RBAC).

## Uses Classes

*   [RequestService](./classes/Service/RequestService.md)

## Role-Based Access Control (RBAC)

In the Common Gateway, both users and applications are granted permissions based on RBAC. For information on how users and applications are authenticated, please refer to the [Authentication](./Authentication.md) page.

In our system, a user or an application can be assigned one or more roles. We refer to these roles as "groups" in our applications. Each group is associated with a set of permissions, which we call "scopes".

A key feature of our RBAC implementation is that groups can inherit scopes from other groups. This allows us to create a hierarchy of groups with progressively broader scopes.

### Example

Here is an example to illustrate the inheritance of scopes in our RBAC system:

*   **Anonymous**: This group has the most basic set of scopes. It represents users or applications that have not been authenticated.

*   **User**: This group inherits all scopes from the Anonymous group. In addition, it has additional scopes that are specific to authenticated users.

*   **Manager**: This group inherits all scopes from the User group. It has additional scopes that enable management functions.

*   **Administrator**: This group inherits all scopes from the Manager group. As the group with the highest level of access, it has all available scopes.

This hierarchy allows for a clear and manageable organization of scopes. It ensures that each user or application has only the permissions it needs to perform its tasks, in line with the principle of least privilege.

## Scope Inheritance

In the Common Gateway project, we establish a hierarchy of groups, each inheriting scopes from the group that lies below it. This "bottom-up" inheritance mechanism allows for efficient scope management, as each group inherits all the scopes of its subordinates, with any additional scopes assigned manually. For example, if there exists a 'Manager' group that sits above a 'User' group, the 'Manager' will inherit all scopes from the 'User'. The 'User' group may, in turn, inherit scopes from an 'Anonymous' group, leading to a situation where a 'Manager' possesses the scopes of both 'User' and 'Anonymous' groups.

## Scopes Definition

Scopes within the Common Gateway are defined based on the CRUD (Create, Read, Update, Delete) operations. A scope can be applied to an entire system aspect (e.g., 'cronjobs.READ') or to a specific object (e.g., '\[uuid].read').

Below is a breakdown of system aspects and their possible scopes:

| System Aspect | CREATE | READ | UPDATE | DELETE | SPECIAL |
|---------------|--------|------|--------|--------|---------|
| Actions       | Yes    | Yes  | Yes    | Yes    | RUN       |
| Sources       | Yes    | Yes  | Yes    | Yes    | -       |
| Cronjobs      | Yes       | Yes  | Yes    | Yes    | RUN     |
| Endpoints     | Yes    | Yes  | Yes    | Yes    | -       |
| Objects       | Yes       | Yes  | Yes    | Yes    | REVERT       |
| Schemas       | Yes    | Yes  | Yes    | Yes    | -       |
| Logs          | Yes    | Yes  | Yes    | Yes    | -       |
| Plugins       | Yes    | Yes  | -      | Yes    | UPDATE  |
| Collections   | Yes    | Yes  | Yes    | Yes    | -       |
| Mappings      | Yes    | Yes  | Yes    | Yes    | -       |
| Templates     | Yes    | Yes  | Yes    | Yes    | -       |
| Users         | Yes    | Yes  | Yes    | Yes    | -       |
| Groups        | Yes    | Yes  | Yes    | Yes    | -       |
| Applications  | Yes    | Yes  | Yes    | Yes    | -       |
| Organizations | Yes    | Yes  | Yes    | Yes    | -       |

Note: In the table above, a '-' indicates that the scope is not applicable for that aspect. For example, the 'CREATE' operation is not applicable to 'Objects', and the 'UPDATE' operation is not applicable to 'Plugins' but they have a special 'UPDATE' scope.

This scope inheritance and definition mechanism provides a flexible and robust system for managing access and operations within the Common Gateway project.

There are also some special scopes
**Cronjobs/Actions:RUN** The ability to manually run an action or cronjob
\*\*Objects :REVERT \*\* The ability to manually revert an object to an earlier version
\*\*Plugins :UPDATE  \*\* The ability to manually update a plugin to a newer version

# Common Gateway: Ownership and Creation

In the context of the Common Gateway project, it's crucial to understand the difference between the roles of an 'Owner' and a 'Creator'. These two roles possess different levels of control over objects within the system, and each has specific rights and limitations.

## Owner vs. Creator

### Owner

The 'Owner' of an object in the system has full control over it. This means that they can perform all CRUD (Create, Read, Update, Delete) operations on the object, including changing its properties, modifying its functionality, and even deleting it. Essentially, the owner has all rights to any object they own.

In addition to these rights, the owner also has the unique authority to transfer ownership to another user, application, or organization. This allows for flexibility in management and control, as the ownership can be shifted according to the needs of the project or team.

### Creator

The 'Creator' of an object, on the other hand, has no rights. It is merely a transactional log of the user or application that created an object in the first place. In most cases the creator becomes the owner when creating an object.

## Multitenancy in the Common Gateway

Multitenancy is a key concept in the Common Gateway project. It allows multiple independent instances of users and applications to operate within the same environment, while maintaining distinct, secure access to their respective objects. In this project, multitenancy is implemented at the organization level, meaning that all objects are tied to an organization, and users and applications can only interact with objects that belong to the same organization.

### Object Ownership and CRUD Rights

Within an organization, users and applications have CRUD (Create, Read, Update, Delete) rights to objects. However, these rights are restricted to the scope of their respective organization. This means that a user or application from one organization cannot access or manipulate the objects of another organization.

For instance, if a user belongs to Organization A, they cannot read or modify the data of Organization B unless they have been granted specific access to Organization B.

### Switching Between Organizations

If a user is a part of multiple organizations, they must manually switch between these organizations to exercise their rights within each. When a user switches organizations, their context changes, and they can now interact with the objects of the newly selected organization.

For example, if a user belongs to both Organization A and Organization B, they would initially have access to the objects of Organization A. If they want to access the objects of Organization B, they would need to switch their active organization to Organization B. This ensures that data is securely partitioned between organizations, preserving the integrity and security of each organization's data.

### Maintaining Multitenancy

Multitenancy is maintained through one of two methods:

*   **Single Database Setup:** In a single database setup, the organization is always added as a query parameter in the database operations. This ensures that only the data corresponding to the active organization is fetched, maintaining data separation between different organizations.

*   **Multiple Database Setup:** In a multiple database setup, each organization has its own separate database. Traffic is routed to the specific database that corresponds to the active organization. This is the preferred setup because it provides a higher level of data isolation and can better handle the scale of large organizations.

In both cases, the principle of multi tenancy is preserved. Users and applications only have access to their own organization's data, ensuring data security and privacy across all organizations in the Common Gateway environment.
