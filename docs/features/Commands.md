# Commands

> **Warning**
> This file is maintained at the Conduction [Google Drive](https://docs.google.com/document/d/1YdklehnXuBec330zJ4xH1IXcUjrFIQJpE4vTHEX0hOs/edit). Please make any suggestions of alterations there.

In the Common Gateway, which is a critical component of the architecture, Symfony command-line commands play an integral role. These commands, which are executed in a container and require command access, are powerful tools that can be used for various tasks such as manipulating the database, handling migrations, managing plugins, and many other critical tasks.

However, it's important to note that in normal operations, installations, and implementations of the Common Gateway, direct usage of these commands should be avoided. These commands are generally utilized internally by the gateway's functionalities and are designed to assist in extreme situations where manual intervention is required to troubleshoot or resolve a complex issue.

These commands are primarily designed to interact with the underlying Symfony framework, which powers the Common Gateway. They are a part of Symfony's console component which provides a simple API for creating command-line commands.

Please be aware that these commands, while powerful, should be used with caution. They have the ability to directly manipulate the state of your application and should only be used when necessary and by individuals who have a deep understanding of the Common Gateway and its architecture.

Remember, while these commands exist as a helpful tool in extreme circumstances, in most situations, the Common Gateway is designed to operate and manage its tasks without needing direct command-line intervention. Please always refer to the official documentation and guidelines before running these commands.

## comongateway:composer:update

options
–bundle {required bundle name} only run the updater for a specific bundle
–data {optional bundle name} force the loading of test data
–skip-schema {optional bundle name}
–skip-script {optional bundle name}
–unsafe 



