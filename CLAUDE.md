# CLAUDE.md

Guidance for working in this repository. Match the existing conventions exactly — this codebase is
small, uniform, and highly opinionated, so new code should be indistinguishable from what's here.

## What this is

A deployable **Google Cloud Function** (PHP 8.3+, `php83` runtime) that reads the current temperature
and relative humidity from your [SmartThings](https://www.smartthings.com/) devices and returns them
as a single JSON envelope, with an average across all non-stale readings. It is an **application, not
a library** — it wires the sibling `christianjbrown/*` libraries together behind an HTTP entry point:
the `run()` function in `index.php` builds the config, constructs a `SmartThings` client and a
`CloudFunction`, and returns the PSR-7 response.

The app consumes four private `dev-main` sibling packages: `php-cloud-function-lib` (the HTTP
envelope/gating/caching framework), `php-smartthings-api-lib` (the read-only SmartThings client),
`php-user-friendly-exception-lib`, and `php-code-quality-scripts` (dev). It runs on Google's
[Functions Framework](https://github.com/GoogleCloudPlatform/functions-framework-php) locally.

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

`composer start` exports `.local.env` (git-ignored) and serves the function at `http://localhost:8080`
(override with `PORT`) via `FUNCTION_TARGET=run` on the Functions Framework router. A local run needs
at least `SMARTTHINGS_API_TOKEN` and `K_REVISION` set — see `README.md` for the full env-var list.

Style tooling comes from the `christianjbrown/php-code-quality-scripts` dev dependency (php-cs-fixer
+ PHP_CodeSniffer, **Symfony2 coding standard**); the `bin/php-cs*` scripts are thin wrappers over it.
Static analysis is **PHPStan at `level: max`** (`phpstan.neon.dist`). Always run `composer fix-style`
first, then `composer check-style` to surface anything left to fix by hand, then `composer stan` and
`composer test` before finishing.

## Architecture

Everything lives directly under `src/` (no sub-layers). PSR-4: `ChristianBrown\SmartThingsClimate\` →
`src/` (`autoload`), `ChristianBrown\SmartThingsClimate\Tests\` → `tests/` (`autoload-dev`). The
top-level `index.php` holds the framework entry point and is intentionally outside the namespace.

- **`index.php`** — defines `run(ServerRequestInterface): ResponseInterface`, the Functions Framework
  target. It reads `getenv()`, builds a `Config` via `ConfigTransformer`, constructs the `SmartThings`
  facade and pulls its device / device-status / location-room clients, assembles the `DataProvider`
  and `OutputTransformer`, and hands both to a `CloudFunction`, returning its `run()` response.
- **`Config`** / **`ConfigInterface`** — a small holder for the SmartThings API token plus the
  `FunctionConfigInterface` (from `php-cloud-function-lib`) that drives gating/caching.
- **`ConfigTransformer`** / **`ConfigTransformerInterface`** — builds a `Config` from the environment
  array. It guards `SMARTTHINGS_API_TOKEN` (via `ENV_API_TOKEN`) with sequential presence/type checks
  and delegates the rest of the env to the injected `FunctionConfigTransformer`.
- **`DataProvider`** — implements the lib's `DataProviderInterface`. `getData()` lists devices, and for
  each keeps those exposing a `temperatureMeasurement` or `relativeHumidityMeasurement` capability,
  reads its status, resolves the room name (for devices with a room) and battery, flags readings older
  than `STALE_THRESHOLD` (24h) as stale, and builds `DeviceReading` value objects.
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
