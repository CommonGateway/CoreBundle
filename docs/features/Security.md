# Security

We believe in integrating security into the core of our development process. We employ automated penetration testing and scanning as part of our Continuous Integration and Continuous Deployment (CI/CD) pipeline. This approach allows us to identify and address potential security vulnerabilities early, during the development phase, rather than later in the production phase.

## Automated Penetration Testing

Automated penetration testing tools are integrated into our CI/CD pipeline to simulate attacks on our systems and identify security weaknesses. These tools conduct a series of tests to check for common vulnerabilities, including those listed in the OWASP Top 10.

The results from these tests are then used to inform our development and security teams about potential vulnerabilities. This process enables us to address these vulnerabilities before the software is deployed to production.

## Scanning

Our CI/CD pipeline also includes automated scanning tools that check our source code, containers, and cloud infrastructure for security issues.

Source code scanners analyze our code to find security weaknesses such as those in the OWASP Top 10 list of common security risks.
Container scanners inspect our Docker and other container images for vulnerabilities, misconfigurations, and compliance with best practices. This is in line with our commitment to adhere to the top ten containerization security tips.
Cloud security scanners ensure that our cloud infrastructure is configured securely, following the principle of least privilege and other cloud security best practices.
Adhering to the Top Ten Containerization Security Tips
In our commitment to maintain robust security, we adhere to the top ten containerization security tips. Here are some of the practices we follow:

*   **Use minimal base images:** We only include the necessary services and components in our container images to reduce the attack surface.
*   **Manage secrets securely:** We don't store sensitive information like passwords, API keys, or secret tokens in our container images. Instead, we use secure secrets management tools.
*   **Use containers with non-root privileges:** We run our containers as non-root users whenever possible to limit the potential damage if a container is compromised.
*   **Regularly update and patch containers:** We keep our containers up to date with the latest security patches.
*   **Scan images for vulnerabilities:** As mentioned above, we use automated tools to scan our container images for known vulnerabilities.
*   **Limit resource usage:** We use container runtime security features to limit the amount of system resources a container can use.
*   **Use network segmentation:** We isolate our containers in separate network segments to limit lateral movement in case of a breach.
*   **Implement strong authentication and authorization controls:** We ensure that only authorized individuals can access our containers and the data within them.
*   **Monitor and log container activity:** We collect and analyze logs from our containers to detect any suspicious activity.
*   **Ensure immutability and maintain an effective CI/CD pipeline:** Our containers are designed to be immutable, meaning they are not updated or patched once they are deployed. Instead, changes are made to the container image and a new version of the container is deployed through our CI/CD pipeline.

By integrating security into our development process, we aim to create a secure, reliable environment for our software and services.

## User Authentication

We implement user authentication through oAuth or Active Directory Federation Services (ADFS). ADFS is a software component developed by Microsoft that provides users with single-sign-on access to systems and applications located across organizational boundaries.

Users first authenticate through oAuth/ADFS, which then produces a series of claims identifying the user. These claims are then used by the Open Catalogi application, which uses them to decide whether to grant the user access and roles (See RBAC). This system simplifies the login process for users and allows for secure authentication across different systems and applications.

## Identification Based on Two-Way SSL

Identification of other catalogs in our federative network is based on two-way SSL (Secure Sockets Layer) certificates, specifically adhering to the Dutch PKI (Public Key Infrastructure) system. This approach ensures a secure and trusted communication channel between the software and the catalog.

The two-way SSL authentication mechanism requires both the client and the server to present and accept each other's public certificates before any communication can take place. This process guarantees the identity of both the client and server, ensuring a high level of security and trust in the communication.

## Role-Based Access Control (RBAC)

Our system implements Role-Based Access Control (RBAC) to manage both user and application rights. RBAC is a method of regulating access to computer or network resources based on the roles of individual users within the organization.

In RBAC, permissions are associated with roles (and configured in our software), and users and other appliations are assigned appropriate roles. This setup simplifies managing user privileges and helps to ensure that only authorized users and applications can access certain resources or perform certain operations.

## Data Security Levels

Our system handles various types of data, each requiring different levels of security:

*   **Public Data:** This data is available to all users and doesn't contain any sensitive information. Even though it's public, we still take measures to ensure its integrity and availability.
*   **Data Available to Specified Organizations:** Some data is only accessible to certain organizations. We implement strict access controls and authentication methods to ensure that only authorized organizations can access this data.
*   **Data Available Only to the Own Organization:** Certain data is strictly internal and only accessible by our organization. This data is protected by multiple layers of security and can only be accessed by authenticated and authorized personnel within our organization.
*   **User-Specific Data:** Some data is personalized and only available to specific users. This data is protected by strong access controls and encryption. Only the specific user and authorized personnel within our organization can access this data.

We take data security very seriously and have implemented robust measures to ensure the safety, confidentiality, integrity, and availability of all data in our system.

## Seperating Landingzone, Executionzone and Data

In our setup, we utilize NGINX and PHP containers to ensure a clean separation of concerns between internet/network access, code execution, and data storage. This design facilitates robust security and improved manageability of our applications and services.

*   **NGINX Containers as Landing Zone:** The first layer of our architecture involves NGINX containers serving as a landing zone. NGINX is a popular open-source software used for web serving, reverse proxying, caching, load balancing, and media streaming, among other things. In our context, we use it primarily as a reverse proxy and load balancer.  When a request arrives from the internet, it first hits the NGINX container. The role of this container is to handle network traffic from the internet, perform necessary load balancing, and forward requests to appropriate application containers in a secure manner. This arrangement shields our application containers from direct exposure to the internet, enhancing our security posture.

*   **PHP Containers as Execution Zone:** Once a request has been forwarded by the NGINX container, it lands in the appropriate PHP container for processing. These containers serve as our execution zone, where application logic is executed.  Each PHP container runs an instance of our application. By isolating the execution environment in this way, we can ensure that any issues or vulnerabilities within one container don't affect others. This encapsulation provides a significant security advantage and makes it easier to manage and scale individual components of our application.

*   **Data Storage Outside of the Cluster:** For data storage, we follow a strategy of keeping data outside the cluster. This approach separates data from the execution environment and the network access layer, providing an additional layer of security. Data stored outside the cluster can be thoroughly protected with specific security controls, encryption, and backup procedures, independent of the application and network layers.

This three-tiered approach – NGINX containers for network access, PHP containers for code execution, and external storage for data – provides us with a secure, scalable, and resilient architecture. It allows us to isolate potential issues and manage each layer independently, thereby enhancing our ability to maintain and secure our services.
