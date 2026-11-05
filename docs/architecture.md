# Architecture

Hexagonal architecture with DDD and CQRS. The Symfony project is in `src/`, so its PHP classes are
in `src/src/`.

## Bounded contexts

| Context | Owns |
| --- | --- |
| `JobPosition` | Positions: creating, listing, opening and closing them |
| `CandidateApplication` | Applications, their AI enrichment and the backoffice |
| `Shared` | Building blocks used by every context: ids, clock, buses |

`CandidateApplication` never imports `JobPosition`'s Domain. It sees positions through its own port,
`Application\Port\JobPositionCatalog`, implemented in its Infrastructure by an adapter that asks
`JobPosition`'s public queries on the query bus.

### Flow of an application

1. The candidate submits the apply form. The `SubmitCandidateApplication` command checks the position
   is open and the mailbox has not applied yet, saves the application, and publishes
   `CandidateApplicationSubmitted`.
2. The event goes to Redis. The `worker` service consumes it and runs
   `EnrichCandidateApplicationOnSubmission`, which calls the CV analyser port (mocked) and saves the
   summary and score.
3. If every retry fails, a Messenger listener dispatches `MarkEnrichmentFailed`.

## Layers

Each context is a directory with three layers:

```text
src/src/
├── Kernel.php
├── Shared/
│   ├── Domain/
│   ├── Application/
│   └── Infrastructure/
└── <Context>/
    ├── Domain/             Aggregates, value objects, events, repository interfaces
    ├── Application/        Commands, queries, their handlers, ports and read models
    └── Infrastructure/     Controllers, API, forms, MongoDB repositories, mapping, adapters
```

Dependencies only point inwards, and `deptrac.yaml` enforces this (`make deptrac`):

| Layer | May depend on |
| --- | --- |
| Domain | Domain |
| Application | Application, Domain |
| Infrastructure | Infrastructure, Application, Domain, frameworks |

Domain and Application are framework-free: no Symfony, Doctrine or PSR imports.

## CQRS

Depend on the interfaces in `App\Shared\Application\Bus`. Handlers are registered by implementing
the matching interface and defining `__invoke()` with the message as its argument.

| Message | Implements | Handler implements | Dispatched with | Runs |
| --- | --- | --- | --- | --- |
| Command | `Command` | `CommandHandler` | `CommandBus::dispatch()` | Synchronously |
| Query | `Query` | `QueryHandler` | `QueryBus::ask()` | Synchronously |
| Event | `DomainEvent` | `EventHandler` | `EventBus::publish()` | In the worker, through Redis |

Failed events are retried, then moved to the `failed` transport. Inspect them with
`make console -- messenger:failed:show`. Delivery can repeat, so event handlers must be idempotent.

Writes go through aggregates and repositories. Reads use read-model ports in Application,
implemented with the raw MongoDB collection, without hydrating aggregates.

## Persistence

Add one entry per context under `mappings` in `config/packages/doctrine_mongodb.yaml`. Map documents
with XML files in `Infrastructure/Persistence/Mapping`, so that the Domain stays free of persistence
attributes. Ids are UUID value objects, stored as BSON binary (subtype 4) by custom ODM types.
