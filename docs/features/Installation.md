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
7. Change directory into the gateway folder (The folder where you also find the docker-compose.yml file) example: `cd commonground-gateway`
8. Startup the gateway through `$ docker compose up`.
9. You should now see the gateway initiating the virtual machines it needs on your command line tool. (this might take some time on the first run, you will see the text ‘Ready to handle connection’ when it is ready to connect)
10. Additionally, you should now see the containers come up in your docker desktop tool (that you can use from here on)
11. When it is done you can find the Gateway API in your browser under [localhost](http://localhost/), the Gateway UI under [localhost:8000](http://localhost:8000), and any additional web apps that are part of your ecosystem under [localhost:81](http://localhost:81).
>__Note__: Read more about command line tools [here](https://developers.google.com/web/shows/ttt/series-2/windows-commandline) and how to [navigate](https://www.codecademy.com/learn/learn-the-command-line/modules/learn-the-command-line-navigation/cheatsheet)

**Troubleshooting**

If during the steps above you run in any problems the following tips might help:
- If during step 9 the text 'Ready to handle connection' does not appear (keep in mind, this might take a while!) and your docker desktop shows that the php-1 container is not in status running you could be running into an error. If this is the case check your command line tool for any error messages.
- For more general troubleshooting (relevant for local and server installation) please take a look at [troubleshooting](#troubleshooting)


## Server Installation

There are three main routes to install the Common gateway, but we advise using the [helm](https://helm.sh/) route for the Kubernetes environment. However, if you want, you can install the gateway on a Linux machine or use a docker-compose installation.

### Haven / Kubernetes
The Common Gateway is a Common Ground application built from separate components. To make these components optional, they are housed in separate [Kubernetes containers](https://kubernetes.io/docs/concepts/containers/). This means that a total installation of The Common Gateway requires several Containers. You can find which containers these are under [architecture](Architecture.md).


### Installation through Helm charts (recommended method)

Before you continue, make sure you use helm V3 for all the following steps.

And before installing the gateway through Helm charts, make sure you configure cert-manager on your cluster. For more information please visit the [cert-manager documentation](https://cert-manager.io/docs/).

Also make sure to also install the nfs provisioner, see: [ArtifactHub](https://artifacthub.io/packages/helm/kvaps/nfs-server-provisioner). When installing nfs make sure you install with persistance enabled. And make sure to configure at least a persistence size of 1Gi (8Gi or even higher is recommended). The persistence size must be at least as big as the gateway vendor Persistent Volume Claim(s combined) on your cluster.

Helm charts for the Common Gateway are also provided through [ArtifactHub](https://artifacthub.io/packages/helm/commonground-gateway/commonground-gateway). Remember that these Helm files only install the gateway and not any attached applications.

You can add the predefined repository via cli:

```cli
`$ helm repo add helm repo add commonground-gateway-frontend https://raw.githubusercontent.com/ConductionNL/commonground-gateway-frontend/master/helm
```

And installed with the chart:

```cli
`$ helm install my-commonground-gateway commonground-gateway-frontend/commonground-gateway --version 0.1.5
```


More information about installing via Helm can be found on the [Helm Documentation](https://helm.sh/docs/). Further information about installation options can be found on [ArtifactHub](https://artifacthub.io/packages/helm/commonground-gateway/commonground-gateway).

> Note:
> With Helm, the difficulty often lies in finding all possible configuration options. To facilitate this, we have included all options in a so-called values file, which you can find [here](https://artifacthub.io/packages/helm/commonground-gateway/commonground-gateway?modal=values). One very common value used when installing the gateway through helm is the value --set global.domain={{your domain here}}.

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


## Troubleshooting
During installation, it is possible that you run into problems, below you will find common problems and how to deal with them. If you are still running into problems after reading this or if you have any constructive criticism please seek contact with our development team (info@conduction.nl).
> Note: when troubleshooting you will, in most cases, need to run some commands. Unless stated otherwise, these commands should always be executed in the php container.

A very common way to check why the Common Ground Gateway is not functioning as expected is by running the `bin/console doctrine:schema:validate` command. This is a command that validates the mappings of the Commonground Gateway database, but will often return insightful error messages when running into other problems as well.

**You have requested a non-existent service**

If you get the error message "You have requested a non-existent service" then this indicates in most cases that your config/bundles.php file isn't up-to-date. This bundles.php file should contain all installed bundles. If you, for example, get a message that `OpenCatalogi\OpenCatalogiBundle\ActionHandler\ComponentenCatalogusApplicationToGatewayHandler` does not exist, you most likely need to add `OpenCatalogi\OpenCatalogiBundle` to the bundles.php file like this: `OpenCatalogi\OpenCatalogiBundle\OpenCatalogiBundle::class => ['all' => true],` locally you can just edit this file, with a server installation you might want to use something like [vi editor](https://www.redhat.com/sysadmin/introduction-vi-editor) for this.

## Adding the gateway to your existing Symfony project (Beta)
The gateway is a Symfony bundle and can also be added directly to an existing Symfony project through composer. The basic composer command is `composer require commongateway/corebundle` and you can read more about the installation process on [packagist](https://packagist.org/packages/commongateway/corebundle).

## Applications
There are several applications that make use of Common Gateway installation as a backend, best known are [huwelijksplanner (HP)](https://github.com/huwelijksplanner), [Klantinteractie Service Systeem(KISS)](https://github.com/Klantinteractie-Servicesysteem) en [Open Catalogi (OS)](https://opencatalogi.nl/).


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

After you installed the Commonground Gateway, you can use the Commonground Gateway as it is, or take a look at plugins. More about plugins can be found [here](Plugins.md)


