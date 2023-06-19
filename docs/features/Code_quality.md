# Code Quality

> **Warning**
> This file is maintained at the Conduction [Google Drive](https://docs.google.com/document/d/1KYnCd4O-wvdV7Z0fgWhXyi4hjTsy-8VBnNnLsZpNMTQ/edit). Please make any suggestions of alterations there.

Code quality is a central pillar of our development philosophy. For us, it signifies easy-to-interpret, well-documented, and maintainable code. This means that we aim to reduce cyclomatic complexity and cognitive strain, while clearly defining units of code for specific tasks and nothing more. High-quality code is essential for rapid development, especially when code needs to be revisited months later.

## Writing good code

Writing good, maintainable code is a crucial part of any software development process. It ensures that the codebase is easy to understand, modify, and extend, thereby increasing the efficiency of the developers and the overall quality of the software.

*   **No Dead Code:** Dead code is code that is no longer in use or never used, including unused variables, functions, or even modules. It should be removed as it creates unnecessary complexity and can cause confusion and mistakes.
*   **No Code Duplication**: Code duplication is a known code smell and should be avoided. It makes the codebase harder to maintain and increases the likelihood of bugs. Use principles of DRY (Don't Repeat Yourself) to avoid duplication.
*   **Keep Units of Code Short and Simple**: Long methods or functions can be difficult to understand, test, and debug. It is advisable to break them down into smaller, simpler units that do one thing well.
*   **Separation of Concerns**: Each module or component of the software should have a single responsibility. This design principle, known as the Single Responsibility Principle (SRP), makes the software easier to maintain and understand.
*   **Loosely Coupled Architecture**: Coupling refers to the degree to which one module depends on other modules. High coupling leads to a fragile system that is hard to change and understand. It's better to have a loosely coupled architecture where modules interact through well-defined interfaces.
*   **Keep the Codebase as Small as Possible**: A smaller codebase is easier to understand, test, and maintain. It also reduces the risk of bugs. Remove unused code, avoid unnecessary complexity, and strive for simplicity.
*   **Tested Software**: Tests are crucial to ensure that the software works as expected and to prevent the introduction of bugs. Automated tests are particularly useful as they can be run frequently and catch regressions early.
*   **Refactoring**: Code smells are indicators of potential problems in the code. They might not be causing a problem now, but they increase the risk of bugs in the future. Regular refactoring helps keep the codebase clean and maintainable.

One of the good resources for writing maintainable software is "Building Maintainable Software" by Joost Visser from the Software Improvement Group. This book provides practical guidelines for writing clean, maintainable software and should be a part of every developer's toolkit.

## CI/CD Pipeline

Our continuous integration/continuous delivery (CI/CD) pipeline is a crucial part of our software development process.

CI/CD is a method to frequently deliver apps to customers by introducing automation into the stages of app development. The main concepts attributed to CI/CD are continuous integration, continuous delivery, and continuous deployment.

CI/CD bridges the gaps between development and operation activities and teams by enforcing automation in building, testing, and deployment.

### Codacy

Codacy is an automated code review tool that helps developers to save time in code reviews and to tackle technical debt. It uses static code analysis to identify new static analysis issues, code coverage, code duplication, and code complexity in every commit and pull request, directly from your Git workflow.

### PHPCBF and PHPCS

PHPCBF (PHP Code Beautifier and Fixer) and PHPCS (PHP Code Sniffer) are tools that we use to ensure that our code adheres to our chosen coding standards.

PHPCS is a tool that checks your PHP code to see if it adheres to the specified coding standards. It can even check your CSS and JavaScript.

PHPCBF is the companion tool to PHPCS and can be used to automatically correct coding standard violations. PHPCBF works by taking the PHP Code Sniffer tokenized version of a file and making modifications directly to that, then writing out the changes.

### PHPUnit

PHPUnit is a framework that we use for unit testing our PHP code. Unit testing is a method where individual units of source code are tested to determine if they are suitable for use. It helps us to verify if the logic of individual units of our source code is working correctly.

### Postman

Postman is a collaboration platform for API development. It's used for building, testing, and modifying APIs. Postman helps us ensure that any APIs we create are functioning as intended, and allows us to create mock servers, document our APIs, and more.

## Deployment Process

Despite having a CI/CD pipeline, we do not deploy from it. Instead, we use Kubernetes and Harbor for defining deployments on the client side.

Kubernetes is an open-source platform designed to automate deploying, scaling, and operating application containers. Harbor, on the other hand, is an open-source cloud native registry that stores, signs, and scans content.

By leveraging the combination of Kubernetes and Harbor, we ensure our deployment process is robust, scalable, and secure. This setup gives us the flexibility to manage our deployments effectively according to each client's specific requirements and infrastructure.

## Code Formatting

PHP Standards Recommendations (PSR)
We adhere to the PSR-1 (Basic Coding Standard) and PSR-12 (Extended Coding Standard), which have been established by the PHP Framework Interop Group (PHP-FIG). These standards provide rules about how PHP code should be formatted and are generally accepted across the PHP community.

### Symfony Coding Standards

In addition to the PSR-1 and PSR-12 standards, we follow the Symfony Coding Standards, which include several additional conventions such as using Yoda conditions and prefixing abstract classes with Abstract.

### Doc blocks and inline comments

In the journey towards maintaining high-quality code, documentation plays a crucial role. It provides a clearer understanding of the functionality and purpose of different parts of the codebase, enhancing readability and maintainability.

Rich DocBlocks and inline comments are two powerful tools in this regard.

## Code Reviews

### Four-Eye Principle

For all normal pull requests, we follow the Four-Eye Principle, meaning that at least two team members must review and approve the changes before they can be merged into the main or development branch. This ensures that at least four eyes have seen the code, minimizing the chances of bugs or issues going unnoticed.

### Six-Eye Principle

For pull requests that contain core changes—significant modifications that affect the fundamental operation of our application—we follow the Six-Eye Principle. This means that at least three team members must review and approve the changes. Increasing the number of reviewers for these critical changes reduces the risk of introducing bugs or instability into our application.

## Branching Strategy

### Main Branch

The main branch contains the code of the current production version. Only fully tested, stable code should be merged into this branch.

### Development Branch

The development branch serves as an integration branch for features and fixes. It contains the code for the next release. Once the code in the development branch is stable and thoroughly tested, it can be merged into the main branch.

### Feature Branches

Feature branches are created for new features or bug fixes. They branch off from the development branch and should be merged back into it once the feature is completed or the bug is fixed. Each feature branch should have a clear scope and contain changes related to only one specific feature or bug fix.

Remember, each commit should make clear, concise changes, and the commit message should accurately describe those changes.

Following these standards, we can maintain a high level of code quality, ensure the stability of our application, and foster effective collaboration amongst our team.

### Semantic Versioning

Semantic Versioning ((SemVer) is a versioning scheme for software that aims to convey meaning about the underlying changes in a release. It is a widely used standard that helps developers and users understand what kind of changes they can expect when moving from one version of a software package to another.

A semantic version number consists of three parts: MAJOR.MINOR.PATCH, each separated by a dot.

*   **MAJOR** version increment indicates that there are incompatible changes in the API, and users may need to change their code to ensure compatibility with the new version.
*   **MINOR** version increment indicates that new features have been added in a backwards-compatible manner. Users can benefit from the new features without making any changes to their existing code.
*   **PATCH** version increment indicates that backwards-compatible bug fixes have been introduced. These changes are meant to improve the performance, stability, or accuracy of the software without adding any new features.

For example, in version 2.3.4:

'2' is the Major version
'3' is the Minor version
'4' is the Patch version

By adopting Semantic Versioning, developers can make their upgrade paths clearer and package users can have better expectations about compatibility between different versions of the software.
