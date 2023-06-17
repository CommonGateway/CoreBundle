# Design Decisions

> **Warning**
> This file is maintained at the Conduction [Google Drive](https://docs.google.com/document/d/1ao7dPybYOMOchJfg4qFuTgN4z-T2vYXySvZO5OhjL4o/edit). Please make any suggestions of alterations there.

## API First

An API-first approach means that for any given development project, your APIs are treated as "first-class citizens." An API-first approach involves developing APIs that are consistent and reusable, which can be accomplished by using an API description language to establish a contract for how the API is supposed to behave. The specification we use is the [OpenAPI Specification](https://github.com/OAI/OpenAPI-Specification). You can view the latest version of this specification (3.0.1) on [GitHub](https://github.com/OAI/OpenAPI-Specification/blob/master/versions/3.0.1.md).

## Documentation

We host technical documentation on Read the Doc's and general user information on GitHub pages, to make the documentation compatible with GitHub we document in markdown (instead of reStructuredText). Documentation is part of the project and contained within the /docs folder.

## Common Ground

All applications are developed following the Common Ground standards on how a data exchange system should be: modular and open-source. More information on Common Ground can be found [here](https://commonground.nl/)
