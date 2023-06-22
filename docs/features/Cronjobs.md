# Cronjobs

> **Warning**
> This file is maintained at the Conduction [Google Drive](https://docs.google.com/document/d/1nXxbY7Rwk0gBYiUv6o-SoK5v6ToXUCGaAQ6J8KBGEzc/edit). Please make any suggestions of alterations there.

Cronjobs are a central part of the Common Gateway’s [event system](Events.md), enabling scheduling of tasks and automating a myriad of routine procedures. By utilizing the crontab (cron table) file, you can schedule scripts or commands to run at a fixed time, date, or interval. This makes cron jobs a powerful tool for system administration, task automation, data manipulation, and more.

These cron jobs throw events, which may carry an optional data set. The data set can comprise any information relevant to the task - from simple identifiers to complex data structures, depending on the event’s nature and the task at hand. This allows for great flexibility, facilitating tasks such as data backup, sending emails, or system maintenance.

The real power of this system comes into play when other components subscribe to these events. [Action handlers](Action_handlers.md), or functions that determine how an application responds to a certain event, can subscribe to cron job-generated events. When an event occurs, these action handlers are notified and can then process or interact with the associated data.

For instance, a handler might subscribe to a "BackupComplete" event. When a cron job finishes backing up a database, it throws this event with data about the backup's result. The handler, having subscribed to this event, will then receive this data, enabling it to perform subsequent actions - perhaps logging the backup's success, notifying a system administrator, or initiating another dependent task.

In conclusion, cron jobs in the Common Gateway are a versatile tool, enabling task scheduling and data transmission through events. The true potential is unlocked when other elements, such as action handlers, subscribe to these events, providing a reliable way to automate complex tasks and procedures.

To further explore and experiment with cron jobs, you can use an online [crontab editor](https://crontab.guru/).

