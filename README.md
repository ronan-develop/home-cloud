# Home Cloud

TODO : faire un system d'avatar pour le user, il s'affichera s'il existe à la place du message de login

Git :

- 🛠 fixes

- 🗹 finished

- 🛇 bug

- 🗑 delete

- 📦 entities

> (optional)
>
> sudo service wsl-vpnkit status

## Table of content

- [Home Cloud](#home-cloud)
  - [Table of content](#table-of-content)
  - [How to use](#how-to-use)
  - [Importing library](#importing-library)
  - [Generate docker file](#generate-docker-file)
  - [Adapt `docker-compose`](#adapt-docker-compose)

## How to use

- Command : docker compose up -d & if you want to communicate with database type

`Docker compose execute le service database avec la commande :`

```SQL
mysql -u root password=password
```

```bash
docker-compose exec database mysql -u root --password=password
```

If you want to see databases :

```SQL
-- lists all database on SQL Server
SELECT TABLE_NAME
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_TYPE = 'BASE TABLE' 
  AND TABLE_SCHEMA='home-cloud';
```

```sql
-- lists all databases (MySQL)
SHOW DATABASES;
-- or
SELECT schema_name
FROM information_schema.schemata
WHERE schema_name
LIKE 'example%' 
OR schema_name
LIKE 'another example%';
```

see tables :

```SQL
-- lists tables (MySQL)
SHOW TABLES;
```

[X] home work with Docker

[X] The asset mapper configuration

```bash
# compiling importmap.php
symfony console asset-map:compile
```

```bash
# install bundle https://github.com/SymfonyCasts/sass-bundle
composer require symfonycasts/sass-bundle
```

```bash
# watching mode
php bin/console sass:build --watch
```

```yaml
# .symfony.local.yaml to add in symfony console
workers:
    # ...
    sass:
        cmd: ['symfony', 'console', 'sass:build', '--watch']
```

## Importing library

```bash
symfony console importmap:require `name on cdnjs`
```

## Generate docker file

```bash
# generate docker compose
symfony console make:docker:database
# starting the cointainer
docker compose up -d
# communicating with the container
docker-compose ps
# communicating with the database locally
mariadb --user=root --port=32768 --host=127.0.0.1 --password home-cloud
# stopping the container
docker-compose stop
## running mysql in the container
docker-compose exec database mysql --user root --password home-cloud
```

```properties
# docker compose --help
Commands:
  attach      Attach local standard input, output, and error streams to a service's running container.
  build       Build or rebuild services
  config      Parse, resolve and render compose file in canonical format
  cp          Copy files/folders between a service container and the local filesystem
  create      Creates containers for a service.
  down        Stop and remove containers, networks
  events      Receive real time events from containers.
  exec        Execute a command in a running container.
  images      List images used by the created containers
  kill        Force stop service containers.
  logs        View output from containers
  ls          List running compose projects
  pause       Pause services
  port        Print the public port for a port binding.
  ps          List containers
  pull        Pull service images
  push        Push service images
  restart     Restart service containers
  rm          Removes stopped service containers
  run         Run a one-off command on a service.
  scale       Scale services 
  start       Start services
  stats       Display a live stream of container(s) resource usage statistics
  stop        Stop services
  top         Display the running processes
  unpause     Unpause services
  up          Create and start containers
  version     Show the Docker Compose version information
  wait        Block until the first service container stops
  watch       Watch build context for service and rebuild/refresh containers when files are updated
```

## Adapt `docker-compose`

Dans les services, il suffit de déclarer la propriété `database`.
Déclarer l'environnement (le password et le nom de la database). Il faut
également saisir les ports et préciser le network qui va relier la base de
données et PHPMyAdmin

Puis déclarer le service PHPmyAdmin. En idiquant depends_on, la jonction sera
faite avec  la base de données. Donner l'image souhaitée, le port, etc et le
network

En bas de fichier préciser le network.
