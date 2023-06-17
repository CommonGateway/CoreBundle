# Datalayer

> **Warning**
> This file is maintained at Conduction’s [Google Drive](https://docs.google.com/document/d/1sLB6vOTIknrc0yEwtzLXZPQ8sope9GeXgMwRLVtwI8M/edit) Please make any suggestions of alterations there.

The data layer in the Common Gateway project is an innovative feature designed to act as a cross between an index (akin to Elasticsearch) and a data lake. It normalizes data from diverse sources and enables sophisticated searching through various query languages. Its purpose is to simplify cross-source questioning across databases, APIs, and files such as Excel spreadsheets.

## Data Normalization
Our data layer accomplishes this functionality by using schemas as Entity-Attribute-Value (EAV) objects, and normalizing data from different sources within these structures. The advantage of this method is that it provides a uniform view of data regardless of its original source or format, making it easier to search and analyze.

## Source of Truth
Despite its powerful capabilities, it's essential to understand that the data layer is not a source of truth. Instead, it serves as a facilitator, helping us search through the underlying sources. It accomplishes this through a mechanism called "smart caching."

## Smart Caching
Smart caching works by taking a subscription notification from the source (thereby instantly updating the cache if the source changes) or regularly checking the source. This design ensures that the data layer always provides the most current data available, optimizing accuracy and performance.

There is also the option to bypass the cache entirely and query the source directly in an asynchronous manner. However, this approach can result in a performance penalty due to the lack of caching.

## Extending Data Models
The data layer is not just about data normalization and searching. It also allows us to attach extra properties to objects that don't originally have them, effectively extending data models. This feature enables greater flexibility and versatility in how we use and analyze our data.

Overall, the Common Gateway data layer is a crucial component of our architecture, enabling seamless integration and querying across various data sources, while ensuring up-to-date information and the flexibility to extend data models as needed.

