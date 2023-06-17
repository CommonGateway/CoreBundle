# Authentication
Authentication is an essential part of securing the gateway. It involves validating the identity of a client—whether that's a user, a device, or another application—before allowing it to access the system's resources. There are several ways for applications to authenticate themselves to the gateway, each with its own use cases and security considerations.

## Uses Classes
- [AuthenticationService](./classes/Service/AuthenticationService.md)

## Applications
### Application Key
Application keys provide a simple and straightforward method for authenticating an application. They are unique identifiers that an application presents when making a request, acting as a sort of password. However, application keys should be safeguarded as they can potentially provide access to sensitive data and services if compromised.

### JWT Token (preferred)
JWT (JSON Web Token) is a compact and URL-safe means of representing claims to be transferred between two parties​1​. The claims in a JWT are encoded as a JSON object that is used as the payload of a JSON Web Signature (JWS) structure or as the plaintext of a JSON Web Encryption (JWE) structure, enabling the claims to be digitally signed or integrity-protected with a Message Authentication Code (MAC) and/or encrypted.

JWT tokens should be included calls using the “Authorisation” header en prefixed with bearer.

### ZGW JWT Token
ZGW (Zaakgericht Werken) JWT Tokens are a specific type of JWT token, commonly used in the Netherlands for government-related APIs. They follow a specific standard and carry additional information that is pertinent to the ZGW context.

### Two-Way SSL
Two-way SSL, also known as mutual SSL, is a process in which both the client and the server authenticate each other through the verification of each other's digital certificates. This method ensures that both parties are who they claim to be and can trust each other, thereby providing an additional layer of security.

### IP Whitelisting
IP whitelisting is a security feature that restricts access to a network or a system only to trusted users. If you are using the gateway in an API Gateway setup, you can set up IP whitelisting to only accept calls from an application if they originate from either a specific IP address or an IP address range.

> **Warning**
> IP Whitelisting should never be used alone as IP addresses can be easily spoofed. Instead, it should be used as an additional authentication requirement in a machine-to-machine context. In a Web gateway context, IP Whitelisting can lead to undesired results due to the dynamic nature of client IP addresses in such contexts.

### Domain Whitelisting
Domain whitelisting is a security feature that allows access to a system only from specific domain names. If you are using the gateway in a Web Gateway setup, you probably want to ensure that it only serves your own site. This helps prevent cross-site scripting attacks and ensures that other sites don't misuse your services.

> **Warning**
> Domain whitelisting cannot be used in a machine-to-machine context because in most cases, the requesting machine won't have a domain. Use IP whitelisting in those contexts instead.

## Users
### Integrated Identity Provider
An Integrated Identity Provider (IdP) is a system entity that creates, maintains, and manages identity information for principals and provides principal authentication to other service providers within a federation. This authentication process involves validating user credentials and providing identity data to applications for authorization decisions.

### External Identity Provider
An External Identity Provider is an authentication service that is built, hosted, and managed by a third-party service provider. It allows users to authenticate using a single set of credentials stored externally, without the need for additional passwords or usernames.

One common protocol used for this purpose is OAuth 2.0. OAuth 2.0 is an authorization protocol that enables applications to obtain limited access to user accounts on an HTTP service, such as Facebook, GitHub, and DigitalOcean. It works by delegating user authentication to the service that hosts
