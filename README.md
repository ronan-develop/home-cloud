# My-Web-App

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
SELECT TABLE_NAME
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_TYPE = 'BASE TABLE' 
  AND TABLE_SCHEMA='home-cloud';
```

[ ] @home work with Docker

[ ] The asset mapper configuration

generate docker file

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

[ ] Travailler sur l'importation de fichier.

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
