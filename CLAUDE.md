# CLAUDE.md

Guidance for working in this repository. Match the existing conventions exactly — this codebase is
small, uniform, and highly opinionated, so new code should be indistinguishable from what's here.

## What this is

A deployable **Google Cloud Run function** (PHP 8.5+, `php85` runtime) that reads the current temperature
and relative humidity from your [SmartThings](https://www.smartthings.com/) devices and returns them
as a single JSON envelope, with an average across all non-stale readings. It is an **application, not
a library** — it wires the sibling `christianjbrown/*` libraries together behind an HTTP entry point:
the `run()` function in `index.php` builds the config, constructs a `SmartThings` client and a
`CloudFunction`, and returns the PSR-7 response.

The app consumes private `dev-main` sibling packages: `php-gcp-function-lib` (the HTTP
envelope/gating/caching framework), `php-smartthings-api-lib` (the read-only SmartThings client),
`php-oauth2-client-lib` (the OAuth refresh-token manager), `php-key-value-store-lib` (the DB-backed
token store), `php-api-client-lib` (the JSON request sender used by the token manager),
`php-user-friendly-exception-lib`, and `php-code-quality-scripts` (dev). It also pulls Doctrine ORM +
DBAL directly for the token store's persistence. It runs on Google's
[Functions Framework](https://github.com/GoogleCloudPlatform/functions-framework-php) locally.

**Authentication.** The function no longer uses a static SmartThings token. On each request it obtains
an OAuth access token via the refresh-token grant (`RefreshTokenManager`), reading and writing both the
access token and the rotating refresh token to a shared MySQL key-value table. The ORM plumbing for this
— the `EntityManagerFactory` and the `RefreshToken` entity — now lives in the shared
`christianjbrown/php-christianbrown-database-orm` package (`ChristianBrown\Database\…`), which this app
depends on; it is the single home for every entity on the shared `christianbrown` schema, so the
Met Office weather function and a future historical-climate reader can reuse the same mappings. Only the
`MySqlAdvisoryLock` (used to serialise token refreshes) remains local under `src/Database/`.

**Climate history.** On each request the function also records the average house temperature and
humidity to the shared `smartthings_climate` table (append-only, one row per origin request), reusing
the same `EntityManager`/connection already opened for the token store. The write is best-effort:
`DataProvider` wraps it in a `try/catch` and `error_log()`s failures so a database problem never
disturbs the response (see `ClimateAverageCalculator` and the shared `ClimateMeasurementRecorder`).

## Commands

Binaries install into `bin/` (Composer `bin-dir`), not `vendor/bin/`. Both `bin/` and `vendor/` are
gitignored and Composer-installed, so run `composer install` first. Unlike the libraries, this app
**commits `composer.lock`** for reproducible deploys.

| Task | Command |
| --- | --- |
| Run the function locally (Functions Framework) | `composer start` |
| Run tests + coverage (opens HTML report) | `composer test` |
| Run tests, no coverage | `php -d memory_limit=-1 ./bin/phpunit --no-coverage` |
| Run one test | `php -d memory_limit=-1 ./bin/phpunit --filter DataProviderTest` |
| Static analysis | `composer stan` |
| Check code style | `composer check-style` |
| Auto-fix code style | `composer fix-style` |
| Check / fix style on git diff only | `composer check-style-diff` / `composer fix-style-diff` |

### OpenAPI docs (dev-only, npm)

The committed `openapi.yaml` (generated from the `#[OA\...]` attributes in `src/` — see the Architecture
notes) can be previewed and rendered with [Redoc](https://redocly.com/) via `@redocly/cli`. This tooling
is **dev-only**: `package.json`, `package-lock.json`, `node_modules/` and the built `openapi.html` are all
in `.gcloudignore`, so the deploy upload stays pure-PHP and the php85 buildpack never sees a Node app.
There are **no runtime dependencies** — nothing here ships to GCP or runs in the deployed function.

| Task | Command |
| --- | --- |
| Install the docs tooling (once) | `npm install` |
| Live preview in the browser | `npm run docs:preview` |
| Build static `openapi.html` (git-ignored) | `npm run docs:build` |
| Lint the spec | `npm run docs:lint` |

`npm run docs:lint` runs Redocly's opinionated `recommended` ruleset and reports findings for the
minimal, generated, unauthenticated single-endpoint spec (e.g. `security-defined`); these are
informational and are **not** a CI gate. Do not "fix" them by hand-editing `openapi.yaml` — it is
regenerated from `src/` and CI fails on any drift (`composer openapi:generate` + `git diff --exit-code`).

`composer start` exports `.local.env` (git-ignored) and serves the function at `http://localhost:8080`
(override with `PORT`) via `FUNCTION_TARGET=run` on the Functions Framework router. A local run needs
the `SMARTTHINGS_OAUTH_*` credentials, `SMARTTHINGS_DATABASE_DSN`, `SMARTTHINGS_LOCATION_ID` and
`K_REVISION` set (and a reachable database with a seeded refresh token) — see `README.md` for the full
env-var list.

Style tooling comes from the `christianjbrown/php-code-quality-scripts` dev dependency: `check-style`
runs **PHP_CodeSniffer 4** with the `ChristianBrown` standard (slevomat sniffs plus PSR/PEAR/Squiz/Generic)
for linting, and **php-cs-fixer** (`@PhpCsFixer`/`@Symfony`) handles formatting; the `bin/php-cs*` scripts
are thin wrappers over it.
Static analysis is **PHPStan at `level: max`** (`phpstan.neon.dist`). Always run `composer fix-style`
first, then `composer check-style` to surface anything left to fix by hand, then `composer stan` and
`composer test` before finishing.

## Architecture

Everything lives directly under `src/` (no sub-layers). PSR-4: `ChristianBrown\SmartThingsClimate\` →
`src/` (`autoload`), `ChristianBrown\SmartThingsClimate\Tests\` → `tests/` (`autoload-dev`). The
top-level `index.php` holds the framework entry point and is intentionally outside the namespace.

- **`index.php`** — defines `run(ServerRequestInterface): ResponseInterface`, the Functions Framework
  target. It is a thin **composition root only**: it reads `getenv()`, builds a `Config` via
  `ConfigTransformer`, then constructs an anonymous `CloudFunctionFactoryInterface` whose `create()`
  holds all the failable wiring (the Doctrine entity manager over the DSN, the two
  `DatabaseKeyValueStore`s keyed `smartthings_access_token` / `smartthings_refresh_token`, the
  `RefreshTokenManager` that obtains a live access token, the `SmartThings` facade + its device /
  device-status / location-room clients, and the assembled `DataProvider` / `OutputTransformer` handed
  to a `CloudFunction`). It hands that factory + the `FunctionConfig` to a `RequestHandler` and returns
  `handle($request)`. All the `new` wiring lives here (outside the namespace, so it is excluded from
  coverage/PHPStan/phpcs, which only scan `src`/`tests`); the testable orchestration lives in `src`.
- **`RequestHandler`** / **`RequestHandlerInterface`** — the testable entry-point orchestration.
  `handle()` calls the injected `CloudFunctionFactoryInterface::create()` and returns
  `CloudFunction::run($request)`, wrapping **both** in one `try/catch (Throwable)`. Because token
  acquisition / client construction happen in the factory *before* the `CloudFunction` exists, a failure
  there (e.g. a revoked refresh token returning `invalid_grant`) would otherwise escape as a bare 500;
  the catch instead `error_log()`s the cause and returns the framework's `JsonErrorResponse` envelope,
  keeping the response contract consistent (CDN stale-if-error still shields visitors).
- **`CloudFunctionFactoryInterface`** — the seam that defers the failable wiring so `RequestHandler` can
  wrap it; implemented as an anonymous class in `index.php` (the composition root) and mocked in tests.
- **`Config`** / **`ConfigInterface`** — a small holder for the OAuth client id/secret, token URL,
  database DSN and location id, plus the `FunctionConfigInterface` (from `php-gcp-function-lib`) that
  drives gating/caching.
- **`ConfigTransformer`** / **`ConfigTransformerInterface`** — builds a `Config` from the environment
  array. A single `extractRequiredString()` helper guards each required env key (`SMARTTHINGS_OAUTH_*`,
  `SMARTTHINGS_DATABASE_DSN`, `SMARTTHINGS_LOCATION_ID`) with sequential presence/type checks (kept in
  one helper so the transformer's cyclomatic complexity stays within the `ChristianBrown` standard's limit), delegating
  the rest of the env to the injected `FunctionConfigTransformer`.
- **Shared ORM (`ChristianBrown\Database\…`)** — the `EntityManagerFactory` (Doctrine `EntityManager`
  from the DSN, native lazy objects enabled), the `RefreshToken` entity (mapping the shared
  `refresh_tokens` key-value table), the `SmartThingsClimate` entity, and the
  `ClimateMeasurementRecorder` all come from the `php-christianbrown-database-orm` package — not this
  repo. `index.php` builds one `EntityManager` and shares it between the token stores and the recorder.
- **`Database\MySqlAdvisoryLock`** — the one piece of local DB plumbing: a `GET_LOCK`/`RELEASE_LOCK`
  advisory lock (on the token store's connection) that serialises refresh-token rotation across
  instances.
- **`ClimateAverageCalculator`** / **`ClimateAverageCalculatorInterface`** — computes the average
  non-stale temperature and humidity across the `DeviceReading`s; returns `null` for a metric when no
  fresh reading exists.
- **`DataProvider`** — implements the lib's `DataProviderInterface`. `getData()` lists devices, and for
  each keeps those exposing a `temperatureMeasurement` or `relativeHumidityMeasurement` capability,
  reads its status, resolves the room name (for devices with a room) and battery, flags readings older
  than `STALE_THRESHOLD` (24h) as stale, and builds `DeviceReading` value objects. It then records the
  average climate to the database (best-effort, isolated by `try/catch`) before handing the readings to
  the `OutputTransformer`.
- **`DeviceReading`** / **`DeviceReadingInterface`** — a plain typed DTO for one device's label, room,
  battery, temperature/humidity values, timestamps, and stale flags.
- **`OutputTransformer`** — sorts the readings by label, maps each through
  `DeviceReadingOutputTransformer`, and unions on the non-stale temperature and humidity averages.
- **`DeviceReadingOutputTransformer`** — shapes one `DeviceReading` into the response array, unioning
  each optional block (room, battery, temperature, humidity) so its presence is an independent path.

## Conventions (follow all of these)

- `declare(strict_types=1);` on every file, immediately after `<?php`.
- **Every concrete class is `final` and implements a matching `...Interface`** in the same namespace
  (`DataProvider`/`DataProviderInterface`, `OutputTransformer`/`OutputTransformerInterface`). No
  abstract base classes — composition over inheritance.
- **Constants live on the interface, not the class**: env keys (`ENV_*`), capability id values
  (`ID_VALUE_*`), the `STALE_THRESHOLD`, and response body keys (`KEY_*`) — all typed constants.
- **No constructor property promotion** — declare typed `private` properties and assign them in the
  constructor body. Class members (properties then methods) are ordered **alphabetically**.
- Import functions explicitly with `use function array_filter;` etc. (after class imports, blank line
  between groups) and call them unqualified.
- **Value objects** (`Config`, `DeviceReading`): required fields are constructor args; getters `getX()`
  (boolean getters `isX()`). No enums, no `readonly`, no immutability.
- **Transformers**: one `transform(...)` method returning the shaped result. Arrays crossing a public
  boundary carry a `@param mixed[]` / `@return mixed[]` docblock so PHPStan `level: max` is satisfied
  (the payload can be a list or a map, so `mixed[]`, not `array<string, mixed>`).
- **Coverage-driven control flow**: guards are deliberately split into sequential `if`s (rather than a
  single `||`) and optional blocks are unioned as self-contained helpers so each branch is an
  independently reachable path — keep this pattern, it exists to hit 100% path coverage.

## Testing

The `phpunit.xml` config is strict (`requireCoverageMetadata`, `beStrictAboutCoverageMetadata`,
`failOnRisky`, `failOnWarning`, `beStrictAboutOutputDuringTests`, path coverage). With that in mind:

- **Coverage must stay at 100%** — line, path, method/function, and branch. Every code path, including
  each defensive guard and every optional-field block in the transformers and `DataProvider`, must be
  exercised. **Always run `composer test` and check the coverage report** before finishing — it prints
  a text summary to stdout and writes HTML to `.phpunit.cache/code-coverage-html/index.html`. New code
  without full coverage is not done.
- **Every test class needs a `#[CoversClass(...)]` attribute** (may list more than one) or the run
  fails. Use PHPUnit 12 **attributes, not annotations**: `#[CoversClass]`, `#[DataProvider]`,
  `#[TestWith]`.
- Tests mirror `src/` under `tests/`, one `final class XTest extends TestCase` per class. Mock every
  collaborator with `$this->createMock(SomeInterface::class)`; assert statically (`self::assertSame`).
  Reference the **same interface constants** production code uses so no strings are hardcoded.

## Adding a feature

1. Add the class + its matching interface (constants, if any, on the interface).
2. Follow the conventions above (final, no promotion, alphabetical members, function imports,
   `mixed[]` docblocks on array boundaries, sequential guards for path coverage).
3. If it needs new wiring, extend `index.php`'s `run()` to construct and inject it.
4. Add a matching `#[CoversClass]` test under `tests/`.
5. Run `composer fix-style`, then `composer check-style`, then `composer stan`, then `composer test`
   and **confirm the coverage report is 100%** on lines, paths, methods, and branches.
