# Symfony app skeleton

Skeleton for a Symfony project using hexagonal architecture, DDD and CQRS.

| Component | Version |
| --- | --- |
| Ubuntu | 26.04 LTS |
| PHP | 8.5 (FPM, behind nginx) |
| Symfony | 8.1 |
| MongoDB | 9.0, through Doctrine ODM |
| Redis | 8.0, as the Messenger transport |

## Getting started

```console
make install
```

This builds the images, installs the Composer dependencies and starts the stack in the foreground,
showing its logs until you press Ctrl+C, which stops it. Each step is also
available on its own as `make build`, `make deps` and `make up`. Stop the stack with `make down`.

The app is served on <http://localhost:8080>. It returns a 404 until you add a first route.
PHP-FPM's Prometheus metrics are served on <http://localhost:9253/metrics>.

`make` runs the containers with your UID/GID. To override them, or if ports 8080 or 9253 are taken,
set `APP_UID`, `APP_GID`, `APP_PORT` or `METRICS_PORT` in the environment or in a root `.env` file
before building.

## Images

| Image | Purpose |
| --- | --- |
| `jonsnmr/symfony-app:base` | nginx and PHP-FPM runtime, as a non-root user |
| `jonsnmr/symfony-app:build` | `:base` plus Composer, Node and Yarn, for CI/CD and dependency installs |

Neither image contains application code. Under compose, `./src` is mounted on `/code`, so edits
apply immediately. The exception is the `worker` service, which keeps running the code it loaded:
run `docker compose restart worker` after changing an event handler.

## Services

All services share `app-network`.

| Service | Purpose |
| --- | --- |
| `app` | Serves HTTP |
| `worker` | Consumes domain events from Redis |
| `mongodb` | Database |
| `redis` | Message transport |
| `build` | One-off tooling, not started by `up` |

## Commands

```console
docker compose run --rm build composer require vendor/package
docker compose run --rm build yarn --version
make test
make phpstan
make deptrac
```

`make test`, `make phpstan` and `make deptrac` pass anything after `--` to the program:

```console
make test -- --filter=MessengerBusTest
make deptrac -- --help
```

The `--` is required, otherwise `make` reads the options itself. Use `ARGS` when an argument contains
spaces or has the same name as a `make` target, for example `make test ARGS="--filter 'a b'"`.

## Architecture

The Symfony project is in `src/`, so its PHP classes are in `src/src/`. Each bounded context is
a directory with three layers:

```text
src/src/
├── Kernel.php
├── Shared/                 Building blocks used by every context
│   ├── Domain/
│   ├── Application/
│   └── Infrastructure/
└── <Context>/
    ├── Domain/             Aggregates, value objects, events, repository interfaces
    ├── Application/        Commands, queries and their handlers
    └── Infrastructure/     Controllers, MongoDB repositories, mapping
```

Dependencies only point inwards, and `deptrac.yaml` enforces this:

| Layer | May depend on |
| --- | --- |
| Domain | Domain |
| Application | Application, Domain |
| Infrastructure | Infrastructure, Application, Domain, frameworks |

### CQRS

Depend on the interfaces in `App\Shared\Application\Bus`. Handlers are registered by implementing
the matching interface and defining `__invoke()` with the message as its argument.

| Message | Implements | Handler implements | Dispatched with | Runs |
| --- | --- | --- | --- | --- |
| Command | `Command` | `CommandHandler` | `CommandBus::dispatch()` | Synchronously |
| Query | `Query` | `QueryHandler` | `QueryBus::ask()` | Synchronously |
| Event | `DomainEvent` | `EventHandler` | `EventBus::publish()` | In the worker, through Redis |

Failed events are retried, then moved to the `failed` transport. Inspect them with
`bin/console messenger:failed:show`. Delivery can repeat, so event handlers must be idempotent.

### Persistence

Add one entry per context under `mappings` in `config/packages/doctrine_mongodb.yaml`. Map documents
with XML files in `Infrastructure`, so that the Domain stays free of persistence attributes.
