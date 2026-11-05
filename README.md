# Tibretiv ATS

A proof-of-concept Application Tracking System. Candidates apply to open positions with their CV,
an AI enrichment summarises and scores each application in the background, and recruiters search,
filter and follow up applications in a backoffice.

| Component | Version |
| --- | --- |
| Ubuntu | 26.04 LTS |
| PHP | 8.5 (FPM, behind nginx) |
| Symfony | 8.1 |
| MongoDB | 9.0, through Doctrine ODM |
| Redis | 8.0, as the Messenger transport |

## Documentation

| Page | What it covers |
| --- | --- |
| [Product](docs/product.md) | Pages, the AI enrichment, demo notes, known limitations |
| [Architecture](docs/architecture.md) | Bounded contexts, layers, CQRS, persistence |
| [API](docs/api.md) | `GET /api/applications`: filters, pagination, errors |
| [Frontend](docs/frontend.md) | Twig, Tailwind, Alpine, design tokens, components |
| [Infrastructure](docs/infrastructure.md) | Images, services, environment files |

## Getting started

Requires Docker with Compose and `make`.

```console
make init
```

This builds the images, installs the dependencies, creates the MongoDB indexes, seeds the demo
positions and starts the stack in the foreground, showing its logs until Ctrl+C stops it.

| URL | What |
| --- | --- |
| <http://localhost:8080> | Open positions (candidates) |
| <http://localhost:8080/backoffice> | Applications (recruiters) |
| <http://localhost:9253/metrics> | PHP-FPM Prometheus metrics |
| `mongodb://app:app@localhost:27017` | MongoDB, from the host only |

Afterwards:

| Command | Does |
| --- | --- |
| `make up` | Starts the stack again |
| `make down` | Stops it, keeping the data |
| `make install` | Builds, installs dependencies and starts, without the database setup |
| `make build`, `make deps` | Each step of `make install` on its own |
| `make db` | Re-creates the indexes and re-seeds the positions (safe to re-run) |
| `make help` | Lists every target |

`make` runs the containers with your UID/GID. To override them, or if ports 8080 or 9253 are taken,
set `APP_UID`, `APP_GID`, `APP_PORT`, `METRICS_PORT` or `MONGODB_PORT` in the environment or in a
root `.env` file before building.

## Commands

```console
make test
make phpstan
make deptrac
make console -- debug:router
docker compose run --rm build composer require vendor/package
```

Tests use a separate `app_test` MongoDB database, so the stack must be running. They cover
submission, filtering and search, the enrichment (including retries and failure), the API and the
pages.

`make test`, `make phpstan`, `make deptrac` and `make console` pass anything after `--` to the
program:

```console
make test -- --filter=EnrichmentTest
make deptrac -- --help
```

The `--` is required, otherwise `make` reads the options itself. Use `ARGS` when an argument contains
spaces or has the same name as a `make` target, for example `make test ARGS="--filter 'a b'"`.
