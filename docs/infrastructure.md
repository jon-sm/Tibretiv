# Infrastructure

## Images

| Image | Purpose |
| --- | --- |
| `jonsnmr/symfony-app:base` | nginx and PHP-FPM runtime, as a non-root user |
| `jonsnmr/symfony-app:build` | `:base` plus Composer, Node and Yarn, for CI/CD and dependency installs |

Neither image contains application code. Under compose, `./src` is mounted on `/code`, so edits
apply immediately. The exception is the `worker` service, which keeps running the code it loaded:
run `docker compose restart worker` after changing an event handler or the environment.

## Services

All services share `app-network`.

| Service | Purpose |
| --- | --- |
| `app` | Serves HTTP |
| `worker` | Consumes domain events from Redis |
| `tailwind` | Rebuilds the CSS when templates or stylesheets change |
| `mongodb` | Database (`app` for dev, `app_test` for tests) |
| `redis` | Message transport |
| `build` | One-off tooling, not started by `up` |

## Environment files

There are two kinds, in different places:

- **Root `.env`** (optional, untracked): Compose and Makefile settings such as `APP_UID`, `APP_PORT`
  or `MONGODB_PORT`.
- **`src/` dotenv files**: the application's variables, read by Symfony.

| File in `src/` | Committed | Role |
| --- | --- | --- |
| `.env.dist` | Yes | Development defaults. Loaded only when `.env` does not exist |
| `.env` | No | Production only, written on the host. When present, `.env.dist` is ignored |
| `.env.test` | Yes | Test overrides (`MONGODB_DB=app_test`, no LLM delay) |
| `.env.local`, `.env.*.local` | No | Personal overrides, e.g. `MOCK_LLM_LATENCY_MS` |

Real environment variables win over every file, so values set in `compose.yaml` (`APP_ENV`,
`MONGODB_URI`) cannot be overridden from a dotenv file. Check what is loaded with
`make console -- debug:dotenv`.

Never put production secrets in a committed file. See
<https://symfony.com/doc/current/configuration/secrets.html>.

## Logs

Monolog writes the dev log to `src/var/log/dev.log` and the test log to `src/var/log/test.log`. In
production, errors and the messages leading up to them go to stderr as JSON.
