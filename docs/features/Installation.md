# Installation


> **Warning**
> This file is maintained at the Conduction [Google Drive](https://docs.google.com/document/d/1NfZQwcg7F6as3uMYguuxegASf5Bxsy84gveKNmWJ4OQ/edit). Please make any suggestions for alterations there.




## Local installation

For our local environment, we use [docker desktop](https://www.docker.com/products/docker-desktop/). This allows us to spin up virtual machines that mimic production servers easily. In other words, it helps us ensure that the code we test/develop locally will also work online. The same can also be said for configurations.


To spin up the gateway for local use, you will need both [Docker Desktop](https://www.docker.com/products/docker-desktop/) and a [git client](https://gitforwindows.org/) (we like to use [git kraken](https://www.gitkraken.com/) but any other will suffice, you can also install [git](https://git-scm.com/) on your local machine)

**Steps**
1. Install [docker desktop](https://www.docker.com/products/docker-desktop/), [git client](https://gitforwindows.org/) and a browser like [google chrome](https://chromeenterprise.google/).
2. Create a folder where you want to install the gateway, for example: documents/gateway
3. Open a command line interface e.g. windows key + cmd + enter
4. Navigate to the folder you just created with our examples. This would be: `$ cd documents/gateway`
5. Git clone the Common Gateway repository to a folder on your machine (if you like to use the command line interface of git that's `git clone https://github.com/ConductionNL/commonground-gateway.git`
6. (optional) check out a specific version of the gateway e.g. `git checkout feature/oc-ui`
7. Change directory into the gateway folder e.g. `cd commonground-gateway`
8. Startup the gateway through `$ docker compose up`. The first time it needs the `–build` flag as well.
9. You should now see the gateway initiating the virtual machines it needs on your command line tool. (this might take some time on the first run, you will see the text ‘Ready to handle connection’ when it is ready to connect)
10. Additionally, you should now see the containers come up in your docker desktop tool (that you can use from here on)
11. When it is done you can find the gateway API in your browser under [localhost](localhost), the admin ui under [localhost:8000](localhost:8000), and any additional web apps that are part of your ecosystem under [localhost:81](localhost:81).
>__Note__: Read more about command line tools [here](https://developers.google.com/web/shows/ttt/series-2/windows-commandline) and [navigate](https://www.codecademy.com/learn/learn-the-command-line/modules/learn-the-command-line-navigation/cheatsheet)


## Server Installation

There are three main routes to install the Common gateway, but we advise using the [helm](https://helm.sh/) route for the Kubernetes environment. However, if you want, you can install the gateway on a Linux machine or use a docker-compose installation.

### Haven / Kubernetes
The Common Gateway is a Common Ground application built from separate components. To make these components optional, they are housed in separate [Kubernetes containers](https://kubernetes.io/docs/concepts/containers/). This means that a total installation of The Common Gateway requires several Containers. You can find which containers these are under [architecture](Architecture.md).


### Installation through Helm charts (recommended method)

[Helm charts](https://artifacthub.io/packages/helm/commonground-gateway/commonground-gateway)) for the Common Gateway are provided through [ArtifactHub](https://artifacthub.io/packages/helm/commonground-gateway/commonground-gateway). Remember that these Helm files only install the gateway and not any attached applications. You will need Helm v3
You can add the predefined repository via cli:

```cli
Copy code
`$ helm repo add helm repo add commonground-gateway-frontend https://raw.githubusercontent.com/ConductionNL/commonground-gateway-frontend/master/helm
```

And installed with the chart:

```cli
Copy code
`$ helm install my-commonground-gateway commonground-gateway-frontend/commonground-gateway --version 0.1.5
```


More information about installing via Helm can be found on the Helm. Further information about installation options can be found on [ArtifactHub](https://artifacthub.io/packages/helm/commonground-gateway/commonground-gateway).

> :note:
> With Helm, the difficulty often lies in finding all possible configuration options. To facilitate this, we have included all options in a so-called values file, which you can find [here](https://artifacthub.io/packages/helm/commonground-gateway/commonground-gateway?modal=values).

### Installation through docker compose
The gateway repository contains a docker compose, and an .env file containing all setting options. These are the same files that are used for the local development environment. However when using this route to install the gateway for production you **MUST** set the `APP_ENV` variable to 'PROD` (enabling caching and security features) and you must change all passwords  (conveniently labeled _!ChangeMe!_) **NEVER** run your database from docker compose, docker compose is non persistent and you will lose your data. Alway use a separate managed database solution.

### Installation through composer (Linux / Lamp)
Before starting a linux installation make sure you have a basic LAMP setup, you can read more about that [here](https://www.digitalocean.com/community/tutorials/how-to-install-linux-apache-mysql-php-lamp-stack-ubuntu-18-04). Keep in mind that the gateway also has the following requirements that need to be met before installation.

Linux extensions
Composer
PHP extensions
A message que in the form of [RabbitMQ](https://www.rabbitmq.com/).
A caching mechanism in the form of [Redis](https://redis.io/)

After making sure you meet the requirement you can install the gateway through the following steps.

In your linux environment create a folder for the gateway ( `cd /var/www/gateway`) navigate to that folder (`cd /var/www/gateway`).
Then run either `$ composer require common-gateway/core-bundle` or the composer require command for a specific plugin.


## Adding the gateway to your existing Symfony project (Beta)
The gateway is a Symfony bundle and can also be added directly to an existing Symfony project through composer. The basic composer command is `composer require commongateway/corebundle` and you can read more about the installation process on [packagist](https://packagist.org/packages/commongateway/corebundle).

## Applications
There are several applications that make use of Common Gateway installation as a backend, best known are [huwelijksplanner (HP)](), [Klantinteractie Service Systeem(KISS)](https://github.com/Klantinteractie-Servicesysteem) en [Open Catalogi (OS)](https://opencatalogi.nl/).


By design front ends are run as separate components or containers (see Common Ground layer architecture). That means that any frontend application using the gateway as a backend for frontend (BFF) should be installed separately.

## Production

The gateway is designed to operate differently in a production, then in a development environment. The main difference being the amount of caching and some security settings.

## Cronjobs and the cronrunner

The gateway uses cronjobs to fire repeating events (like synchronisations) at certain intervals. Users can set them up and maintain them through the admin UI. However cronjobs themselves are fired through a cronrunner, meaning that there is a script running that checks every x minutes (5 by default) whether there are cronjobs that need to be fired. That means that the execution of cronjob is limited by the rate set in the cronrunner .e.g if the cronrunner runs every 5 minutes it's impossible to run cronjobs every 2 minutes.

For docker compose and helm installation the cronrunner is based on the linux crontab demon and included in het installation scripts. If you are however installing the gateway manually you will need to set up your own crontab to fire every x minutes.


For your crontab you need to execute the ` bin/console cronjob:command` cli command in the folder where you installed the Common Gateway. e.g. `*/5 * * * * /srv/api bin/console cronjob:command`. If you need help defining your crontab we advise [crontab.guru](https://crontab.guru/every-5-minutes).

## Workers

The gateway uses workers to asynchronously handle workload, the concept is quite simple the gateway installation sets asynchronous works on a massage queue ([RabbitMQ](https://www.rabbitmq.com/)) other gateway installations then look into the message queue to see if there is any work that they can pick up. In the default helm installation we use 5 gateway containers for this. The amount of workers is however  configurable through the `change.me` parameter.

![workers.svg](workers.svg)

If you are installing the gateway on a linux setup you will need to manually install workers (preferably on other machines than your main gateway installation) and put them into worker mode by running the command 'bin/console messenger:consume async', and point them to the [RabbitMQ](https://www.rabbitmq.com/) message queue. The RabbitMQ location is defined in the file api/config/messenger.yaml in the variable parameters.env(MESSENGER_TRANSPORT_DSN).

## Setting up plugins

More about plugins can be found [here](Plugins.md)


