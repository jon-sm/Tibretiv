# Tibretiv ATS

A proof-of-concept Application Tracking System.


| Component | Version |
| --- | --- |
| Ubuntu | 26.04 LTS |
| PHP | 8.5 (FPM, behind nginx) |
| Symfony | 8.1 |
| MongoDB | 9.0, through Doctrine ODM |
| Redis | 8.0, as the Messenger transport |

## The product

| Page | URL | For |
| --- | --- | --- |
| Open positions | <http://localhost:8080/> | Candidates pick a role |
| Apply | `/positions/{id}` | Candidate data, plus the CV pasted as plain text |
| Applications | <http://localhost:8080/backoffice> | Recruiters search and filter, newest first |
| Application detail | `/backoffice/{id}` | CV, AI summary and score, status, timestamps |

After an application is submitted, an AI enrichment runs in the background: it summarises the CV
and scores its relevance (0–100) for the position. The list and detail show "Analysing…" until it
finishes, and refresh on their own.

### Demo notes

- The LLM is mocked: no API is called. Until the scoring criteria are agreed, it returns the start
  of the CV as the summary and a fixed score of 50, after a short simulated delay.
- A CV containing `FAIL_ENRICHMENT` makes every attempt fail, to show the retry and failed path.
- One application per email per position. Gmail dots and `+tags` count as the same mailbox.
- The backoffice has no login in this demo.
- Dates in the backoffice are shown and filtered in Europe/Madrid time.

### Known limitations

- Events are published right after saving, without an outbox: if Redis is down at that moment the enrichment is not started, or saved.
- A failed enrichment cannot be retried from the UI yet.

## Getting started

```console
make init
```

This builds the images, installs the dependencies, creates the MongoDB indexes, seeds the demo
positions and starts the stack. Afterwards, `make up` starts it again, and `make db` re-creates the
indexes and re-seeds the positions (safe to re-run).

The steps below describe `make install`, which does the same without the database setup.

```console
make install
```

This builds the images, installs the Composer dependencies and starts the stack in the foreground,
showing its logs until you press Ctrl+C, which stops it. Each step is also
available on its own as `make build`, `make deps` and `make up`. Stop the stack with `make down`.

The app is served on <http://localhost:8080>.
PHP-FPM's Prometheus metrics are served on <http://localhost:9253/metrics>.
MongoDB is reachable from the host on `mongodb://app:app@localhost:27017` (localhost only).

`make` runs the containers with your UID/GID. To override them, or if ports 8080 or 9253 are taken,
set `APP_UID`, `APP_GID`, `APP_PORT`, `METRICS_PORT` or `MONGODB_PORT` in the environment or in a root `.env` file
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
| `tailwind` | Rebuilds the CSS when templates or stylesheets change |
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

Tests use a separate `app_test` MongoDB database, so the stack must be running. They cover
submission, filtering and search, the enrichment (including retries and failure) and the pages.

```console
make test -- --filter=MessengerBusTest
make deptrac -- --help
```

The `--` is required, otherwise `make` reads the options itself. Use `ARGS` when an argument contains
spaces or has the same name as a `make` target, for example `make test ARGS="--filter 'a b'"`.

## Frontend

Pages are server-rendered with Twig. Assets are served by AssetMapper, so there is no Node build.

- **Tailwind CSS** (via `symfonycasts/tailwind-bundle`) builds `assets/styles/app.css`. The
  `tailwind` service watches for changes, and `make deps` does a one-off build.
- **Alpine.js** adds small interactive behaviour, and is loaded through the importmap.
- **Design tokens**: Tailwind's default theme gives the primitives. The semantic tokens in
  `assets/styles/app.css` (`bg-surface`, `text-fg-muted`, `bg-accent`...) are what templates use.
  Browse them at <http://localhost:8080/design-tokens>.
- **Components**: reusable UI is Twig Components in `templates/components/` (`<twig:Button>`,
  `<twig:Input>`...), styled with utilities in their markup.

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
