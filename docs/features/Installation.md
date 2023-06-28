# Installation


> **Warning**
> This file is maintained at the Conduction [Google Drive](https://docs.google.com/document/d/1NfZQwcg7F6as3uMYguuxegASf5Bxsy84gveKNmWJ4OQ/edit). Please make any suggestions of alterations there.

We dever the installation of the gateway between local en server installations, keep in mind that local installations are meant for development, testing en demo purposes and are (by their nature) not suited for production environments. When installing the gateway for production purposes **ALWAYS** follow the steps as set out under the server installation manual.


## Local installation
For our local environment we use [docker desktop](https://www.docker.com/products/docker-desktop/) this allows use to easily spin up virtual machines that mimic production servers. In other words it helps us to make sure that the code that we test/develop locally will also work online. The same can also be said for configurations.

In order to spin up the gateway for local use you will need both docker  [docker desktop](https://www.docker.com/products/docker-desktop/) and a [git client](https://gitforwindows.org/) (we like to use [git kraken](https://www.gitkraken.com/) but any other will suffice, you can also install [git](https://git-scm.com/) on your local machine)

**Steps**
1. Install [docker desktop](https://www.docker.com/products/docker-desktop/),  [git client](https://gitforwindows.org/)and [google chrome](https://chromeenterprise.google/).
2. Create a folder where you want install the gateway e.g. documenten/gateway
3. Open a  command line interface e.g. windows key + cmd + enter
4. Navigate to the folder you just created e.g. `$ cd documenten/gateway`
5. Git clone the common gateway repository to a folder on your machine (if you like to use the command line interface of git thats `git clone https://github.com/ConductionNL/commonground-gateway.git`
6. (optional) check out a specific version of the gateway e.g. `git checkout feature/oc-ui`
7. Change directory into the gateway folder e.g. `cd commonground-gateway`
8. Startup the component trough `$ docker compose up`
9. You should now see the gateway initiating the virtual machines that it needs on your command line tool. (this might take some time on the first run)
10. Additional you should now see the containers come up in your docker desktop tool (that you can use from here on)
6. When it it done you can find the gateway api in your browser under [localhost](localhost) the admin ui under [localhost:8000](localhost:8000) and any additional web apps that are part of your ecosystem under [localhost:81](localhost:81).


>__Note__:  Read more about command line tools [here](https://developers.google.com/web/shows/ttt/series-2/windows-commandline) and [navigate](https://www.codecademy.com/learn/learn-the-command-line/modules/learn-the-command-line-navigation/cheatsheet)

## Server Installation

There are three main routes to install the Common gateway, but we advise to use the helm route for the kubernetes environment. However, if you want you can also install the gateway on a linux machine or use a docker compose installation.

### Installation through composer (Linux / Lamp)
Before starting an linux installation make sure you have a basic LAMP setup, you can read more about that [here](https://www.digitalocean.com/community/tutorials/how-to-install-linux-apache-mysql-php-lamp-stack-ubuntu-18-04). Keep in mind that the gateway also has the following requirements that need to be met before installation.

Linux extensions
Composer
PHP extensions
A message que in the form of [RabbitMQ](https://www.rabbitmq.com/).
A caching mechanism in the form of [Redis](https://redis.io/)

After making sure you meet the requirement you can install the gateway through the following steps.

In your linux environment create a folder for the gateway ( `cd /var/www/gateway`) navigate to that folder (`cd /var/www/gateway`).
Then run either `$ composer require common-gateway/core-bundle` or the composer require command for a specific plugin.


### Installation trough docker compose
The gateway repository contains a docker compose, and an .env file containing all setting options. These are the same files that are used for the local development environment. However when using this route to install the gateway for production you **MUST** set the `APP_ENV` variable tp `PROD` (enabling caching and security features) and you must change al passwords  (conveniently labeled _!ChangeMe!_) **NEVER** run your database from docker compose, docker compose is non persist and you will lose your data. Alway use a separate managed database solution.

### Installation trough Helm charts
[Helm charts]((https://artifacthub.io/packages/helm/commonground-gateway/commonground-gateway)) for the common gateway are provided trough [ArtifactHub](https://artifacthub.io/packages/helm/commonground-gateway/commonground-gateway). Keep in mind that these helm files only install the gateway, and not any attached applications.

## Applications
There are several applications that make use of common gateway installation as a backend, best known are [huwelijksplanner (HP)](), [Klant Interactie Service Systeem(KISS)]() en [Open Catalogi (OS)]().

By design front end are run as separate components or containers (see Commonground layer architecture). That means that any frontend application using the gateway as a backend for frontend (BFF) should be installed separately.

## Production
The gateway is designd to operate differently in a production, then in a development environment. The main difference being the amount of cashing and some security setting.

## Cronjobs and the cronrunner
The gateway uses cronjobs to fire repeating event (like synchronisations) at certain intervals. Users can set the up and maintain them trough the admin ui. However cronjobs themselves are fired trough a cronrunner, meaning that there is a script running that checks every x minutes (5 by default) whether there are cronjobs that need to be fired. That means that the execution of cronjob is limited by the rate set in the cronrunner .e.g if the cronrunner runs every 5 minutes its impossible to run cronjobs every 2 minutes.

For docker compose and helm installation the cronrunner is based on the linux crontab demon and included in het installation scripts. If you are however installing the gateway manually you will need to setup your own crontab to fire every x minutes.


For your crontab you need to execute the ` bin/console cronjob:command` cli command in the folder where you installed the Common Gateway. e.g. `*/5 * * * * /srv/api bin/console cronjob:command`. If you need help defining your crontab we advise [crontab.guru](https://crontab.guru/every-5-minutes).

## Workers
The gateway uses workers to asynchronously handle workload, the concept is quite simple the gateway installation sets asynchronous works on a massage queue ([RabbitMQ](https://www.rabbitmq.com/)) other gateway installations then look into the message queue to see if there is any work that they can pick up. In the default helm installation we use 5 gateway containers for this. The amount of workers is however  configurable through the `change.me` parameter.

![workers.svg](workers.svg)

If you are installing the gateway on a linux setup you will need to manually install workers (preferably on other machines than your main gateway installation) and put them into worker mode and point them to the [RabbitMQ](https://www.rabbitmq.com/) message queue. Alternatively

## Setting up plugins
