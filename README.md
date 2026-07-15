# Get Smart Home Temps

A small [Google Cloud Function](https://cloud.google.com/functions) (PHP) that reads the current temperature from your [SmartThings](https://www.smartthings.com/) devices and returns them as a single JSON payload, along with an average across all non-stale readings.

It walks every device in your SmartThings account, keeps the ones that expose a `temperatureMeasurement` capability, reads each one's latest temperature, flags readings older than 24 hours as stale, and returns the lot sorted by device name.



## :heavy_check_mark: Prerequisites

- [Git](https://git-scm.com/)
- [PHP](https://www.php.net/) 8.3 or higher (8.x)
- [Composer](https://getcomposer.org/)
- A SmartThings [personal access token](https://account.smartthings.com/tokens) with the `devices` scope
- Read access to the private `christianjbrown/*` package repositories this function depends on (Composer needs a GitHub token — see [CI & deployment](#rocket-ci--deployment))

:bulb: If you're on macOS and have [Homebrew](https://brew.sh/), PHP and Composer will install with `brew install composer`.



## :building_construction: Installation

```bash
git clone git@github.com:christianjbrown/cloud-function-smart-home-temps.git
cd cloud-function-smart-home-temps
composer install
```



## :gear: Configuration

Configuration is read entirely from environment variables.

| Variable | Required | Description |
| --- | --- | --- |
| `SMARTTHINGS_API_TOKEN` | ✅ | Your SmartThings personal access token. |
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
SMARTTHINGS_API_TOKEN=your-smartthings-personal-access-token
K_REVISION=local
```



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
            "name": "Bedroom",
            "temp": 19.5,
            "timestamp": 1752580800,
            "stale": false
        },
        {
            "name": "Living Room",
            "temp": 21.0,
            "timestamp": 1752580800,
            "stale": false
        }
    ],
    "averageTempDegrees": 20.25,
    "averageTempTimestamp": 1752580800
}
```

- `devices` — one entry per temperature-reporting device, sorted by `name`. `timestamp` is the Unix time of the reading; `stale` is `true` when the reading is more than 24 hours old.
- `averageTempDegrees` / `averageTempTimestamp` — the mean temperature across non-stale devices and its associated timestamp. Both are omitted when there are no non-stale readings.



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
- **`.github/workflows/deploy.yml`** runs on push to `main`: deploys to Google Cloud Functions 2nd gen (`php83` runtime, `europe-west2`) via Workload Identity Federation, grants public (`allUsers`) invoker access on the underlying Cloud Run service, then smoke-tests the deployed URL.

Both workflows install the private `christianjbrown/*` dependencies using a `COMPOSER_AUTH` repository secret — a Composer auth JSON containing a GitHub token with read access to those repos:

```json
{"github-oauth":{"github.com":"your-github-token"}}
```

The SmartThings token and request-gating values are supplied at deploy time from Google Secret Manager (see `deploy.yml`).



## :package: Architecture

The entry point is `run()` in [`index.php`](index.php), which wires the pieces together:

- **`ConfigTransformer`** reads the environment into a `Config` (API token + request/caching config).
- **`SmartThings`** (from [`christianjbrown/php-smartthings-api-lib`](https://github.com/christianjbrown/php-smartthings-api-lib)) provides the device and device-status API clients.
- **`DataProvider`** fetches devices, filters to those with a temperature capability, reads each status, and builds `DeviceTemperature` value objects.
- **`OutputTransformer`** sorts them, computes the non-stale average, and shapes the JSON response.
- **`CloudFunction`** (from [`christianjbrown/php-cloud-function-lib`](https://github.com/christianjbrown/php-cloud-function-lib)) handles the HTTP request/response, header/origin gating, and caching headers.
