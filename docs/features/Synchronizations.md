# Synchronizations

> **Warning**
> This file is maintained at Conduction’s [Google Drive](https://docs.google.com/document/d/1bJ45SdIaB21TdIoB2sL5biXeJ_L_6QbrfMb0TmzgXGs/edit) Please make any suggestions of alterations there.

The data synchronization process is an essential part of the Common Gateway data layer. This process ensures that data between the data layer and an external source remains consistent. While it's possible to sync directly between sources, this would necessitate a tripartite setup involving the Gateway.

## Determining the Current State of Data
The initial step in the synchronization process involves determining the current state of data. There are three main scenarios:

1. The object exists on the Gateway but not on the source: In this case, we need to decide if the object should be deleted from the Gateway or added to the source, based on the specific rules and constraints of the system.
2. The object exists on the source but not on the Gateway: Here, we usually need to add the object to the Gateway to maintain synchronization.
3. The object exists on both the source and Gateway: In this situation, we need to establish which version is newer and update accordingly. There are three sub-scenarios:
   - The Gateway version is newer: The source should be updated with the Gateway's version.
   - The source version is newer: The Gateway should be updated with the source's version.
   - The versions are the same: No action is required.
   - In instances where we can't establish which version is newer, we must assume which party is "right" and determine a source of truth. Typically, this would be the source, but it can be configured to be the Gateway.

![synchronizations.svg](synchronizations.svg)

## Creation of a Synchronization Object
The next step is to create a synchronization object that holds and describes the relationship between the two objects (Data Layer and Source). This object includes details such as the IDs on both ends, the dates of changes, and a hash of the source object.

The synchronization object is crucial as it allows us to determine if the source object has changed, even if the object doesn't have a property that enables this detection. By comparing the current hash with the previous hash stored in the synchronization object, we can detect any changes and trigger the necessary updates. This method ensures the consistency of data across the Gateway and the source, enhancing the reliability and integrity of our system.
