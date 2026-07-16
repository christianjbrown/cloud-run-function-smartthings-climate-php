# SmartThings Climate Google Cloud Function

[![CI](https://github.com/christianjbrown/php-gcp-function-smartthings-climate/actions/workflows/ci.yml/badge.svg)](https://github.com/christianjbrown/php-gcp-function-smartthings-climate/actions/workflows/ci.yml)

A small [Google Cloud Function](https://cloud.google.com/functions) (PHP) that reads the current temperature and relative humidity from your [SmartThings](https://www.smartthings.com/) devices and returns them as a single JSON payload, along with an average across all non-stale readings.

It walks every device in your SmartThings account, keeps the ones that expose a `temperatureMeasurement` or `relativeHumidityMeasurement` capability, reads each one's latest temperature and/or humidity, resolves the room each device belongs to, flags readings older than 24 hours as stale, and returns the lot sorted by device name.



## :heavy_check_mark: Prerequisites

- [Git](https://git-scm.com/)
- [PHP](https://www.php.net/) 8.5 or higher (8.x)
- [Composer](https://getcomposer.org/)
- A SmartThings [OAuth client](https://developer.smartthings.com/) (client id + secret) with the `r:devices:*` and `r:locations:*` scopes, plus a valid refresh token seeded into the token store
- A MySQL database reachable by the function, holding a key-value table for the rotating OAuth tokens (see [Authentication](#lock-authentication))
- Read access to the private `christianjbrown/*` package repositories this function depends on (Composer needs a GitHub token — see [CI & deployment](#rocket-ci--deployment))

:bulb: If you're on macOS and have [Homebrew](https://brew.sh/), PHP and Composer will install with `brew install composer`.



## :building_construction: Installation

```bash
git clone git@github.com:christianjbrown/php-gcp-function-smartthings-climate.git
cd php-gcp-function-smartthings-climate
composer install
```



## :gear: Configuration

Configuration is read entirely from environment variables.

| Variable | Required | Description |
| --- | --- | --- |
| `SMARTTHINGS_OAUTH_CLIENT_ID` | ✅ | Your SmartThings OAuth client id. |
| `SMARTTHINGS_OAUTH_CLIENT_SECRET` | ✅ | Your SmartThings OAuth client secret; sent as HTTP Basic auth on the token-refresh request. |
| `SMARTTHINGS_OAUTH_TOKEN_URL` | ✅ | The SmartThings OAuth token endpoint (`https://api.smartthings.com/oauth/token`). |
| `SMARTTHINGS_DATABASE_DSN` | ✅ | Doctrine DSN for the MySQL database holding the OAuth token store (see [Authentication](#lock-authentication)). |
| `SMARTTHINGS_LOCATION_ID` | ✅ | The SmartThings location whose devices are read; readings are scoped to this location. |
| `K_REVISION` | ✅ | Set automatically by the Cloud Functions/Cloud Run runtime; only needs setting yourself when running locally. |
| `REQUIRED_HEADER_KEY` | — | If set (with `REQUIRED_HEADER_VALUE`), requests must send this header to be served. |
| `REQUIRED_HEADER_VALUE` | — | Expected value for `REQUIRED_HEADER_KEY`. |
| `REQUIRED_ORIGIN` | — | Restricts responses to this CORS origin. |
| `USE_CACHE_TTL` | — | Seconds a fresh response may be cached (`Cache-Control`). |
| `USE_CACHE_BUT_REQUEST_TTL` | — | Seconds a cached response may be served while revalidating. |
| `USE_CACHE_IF_ERROR_TTL` | — | Seconds a cached response may be served if the origin errors. |
| `DEBUG` | — | Set to `true` for verbose error output. |

For local development, put these in a `.local.env` file in the project root (git-ignored). `composer start` exports it automatically:

```env
SMARTTHINGS_OAUTH_CLIENT_ID=your-oauth-client-id
SMARTTHINGS_OAUTH_CLIENT_SECRET=your-oauth-client-secret
SMARTTHINGS_OAUTH_TOKEN_URL=https://api.smartthings.com/oauth/token
SMARTTHINGS_DATABASE_DSN=mysql://user:password@localhost/schema?unix_socket=/tmp/cloudsql/project:region:instance&driver=pdo_mysql
SMARTTHINGS_LOCATION_ID=your-smartthings-location-id
K_REVISION=local
```

Locally, the DSN's `unix_socket` typically points at a running
[Cloud SQL Auth Proxy](https://cloud.google.com/sql/docs/mysql/connect-auth-proxy) socket; in Cloud
Run the socket is `/cloudsql/<instance connection name>`, mounted by the deploy's
`--set-cloudsql-instances`.



## :computer: Usage

### Run locally

```bash
composer start
```

This serves the function at `http://localhost:8080` (override with `PORT`). Send it a request:

```bash
curl http://localhost:8080
```

### Response

```json
{
    "devices": [
        {
            "name": "Bedroom Sensor",
            "roomName": "Bedroom",
            "batteryValue": 95,
            "temperatureValue": 19.5,
            "temperatureTimestamp": 1752580800,
            "temperatureStale": false,
            "humidityValue": 48,
            "humidityTimestamp": 1752580790,
            "humidityStale": false
        },
        {
            "name": "Living Room",
            "temperatureValue": 21.0,
            "temperatureTimestamp": 1752580800,
            "temperatureStale": false
        }
    ],
    "averageTempDegrees": 20.25,
    "averageTempTimestamp": 1752580800,
    "averageHumidity": 48,
    "averageHumidityTimestamp": 1752580790
}
```

- `devices` — one entry per device that reports temperature and/or humidity, sorted by `name`.
- `roomName` — the SmartThings room the device belongs to. Present only for devices that are assigned to a room.
- `batteryValue` — the device's battery level as a percentage. Present only for devices that report a battery reading.
- `temperatureValue` / `temperatureTimestamp` / `temperatureStale` — the latest temperature, the Unix time of that reading, and whether it is more than 24 hours old. These keys are present only for devices that report temperature.
- `humidityValue` / `humidityTimestamp` / `humidityStale` — the latest relative humidity (percent), the Unix time of that reading, and whether it is more than 24 hours old. Humidity carries its own timestamp and stale flag because SmartThings reports each measurement independently. These keys are present only for devices that report humidity.
- `averageTempDegrees` / `averageTempTimestamp` — the mean temperature across non-stale temperature readings and the earliest of their timestamps. Both are omitted when there are no non-stale temperature readings.
- `averageHumidity` / `averageHumidityTimestamp` — the equivalent mean humidity across non-stale humidity readings. Both are omitted when there are no non-stale humidity readings.



## :lock: Authentication

The function authenticates to SmartThings with an **OAuth access token obtained via the refresh-token
grant**, not a long-lived personal access token. On each request it:

1. Reads the current access token from a key-value row in the MySQL database (`SMARTTHINGS_DATABASE_DSN`).
2. If that token is missing or expired, POSTs the stored **refresh token** to the SmartThings token
   endpoint, authenticating the request with the client id/secret as HTTP Basic auth.
3. Persists the freshly issued access token **and the rotated refresh token** back to the database, so
   the next invocation (on any instance) picks up where this one left off.

The token store is two rows in a shared key-value table (`smartthings_access_token` and
`smartthings_refresh_token`). A valid refresh token must be **seeded once** into
`smartthings_refresh_token` before the first run; SmartThings rotates it on every use thereafter. The
minimal Doctrine ORM plumbing for this (an `EntityManagerFactory` and a `RefreshToken` key-value
entity) lives in [`src/Database/`](src/Database) — the function owns it directly and shares only the
database, not any code, with other services.



## :test_tube: Tests & code style

```bash
composer test              # PHPUnit with coverage, then opens the HTML report
composer check-style       # PHPCS across src/ and tests/
composer check-style-diff  # PHPCS on changed files only
composer fix-style         # auto-fix style in src/ and tests/
composer fix-style-diff    # auto-fix changed files only
```



## :rocket: CI & deployment

- **`.github/workflows/ci.yml`** runs on pull requests to `main`: `composer update`, PHPCS, and PHPUnit.
- **`.github/workflows/deploy.yml`** runs on push to `main`: deploys to Google Cloud Functions 2nd gen (`php85` runtime, `europe-west2`) via Workload Identity Federation, grants public (`allUsers`) invoker access on the underlying Cloud Run service, attaches the shared Cloud SQL instance (`--set-cloudsql-instances`) so the OAuth token store is reachable, then smoke-tests the deployed URL.

Both workflows install the private `christianjbrown/*` dependencies using a `COMPOSER_AUTH` repository secret — a Composer auth JSON containing a GitHub token with read access to those repos:

```json
{"github-oauth":{"github.com":"your-github-token"}}
```

The OAuth client id/secret, database DSN, and request-gating values are supplied at deploy time from Google Secret Manager (see `deploy.yml`). The runtime service account needs `roles/cloudsql.client` on the project that owns the shared database.



## :package: Architecture

The entry point is `run()` in [`index.php`](index.php), which wires the pieces together:

- **`ConfigTransformer`** reads the environment into a `Config` (OAuth client credentials + token URL + database DSN + location + request/caching config).
- **`EntityManagerFactory`** / **`RefreshToken`** (in `src/Database/`) build a Doctrine entity manager over the DSN and map the shared key-value token table; two `DatabaseKeyValueStore`s (from [`christianjbrown/php-key-value-store-lib`](https://github.com/christianjbrown/php-key-value-store-lib)) back the access and refresh tokens.
- **`RefreshTokenManager`** (from [`christianjbrown/php-oauth2-client-lib`](https://github.com/christianjbrown/php-oauth2-client-lib)) returns a valid access token, refreshing (with client-secret Basic auth) and persisting the rotated token when needed.
- **`SmartThings`** (from [`christianjbrown/php-smartthings-api-lib`](https://github.com/christianjbrown/php-smartthings-api-lib)), constructed with that access token, provides the device and device-status API clients.
- **`DataProvider`** fetches devices, filters to those with a temperature and/or humidity capability, reads each status, resolves the room name for devices assigned to one, and builds `DeviceReading` value objects.
- **`OutputTransformer`** sorts them, computes the non-stale temperature and humidity averages, and shapes the JSON response.
- **`CloudFunction`** (from [`christianjbrown/php-gcp-function-lib`](https://github.com/christianjbrown/php-gcp-function-lib)) handles the HTTP request/response, header/origin gating, and caching headers.



## :page_facing_up: License

Released under the [MIT License](LICENSE).
