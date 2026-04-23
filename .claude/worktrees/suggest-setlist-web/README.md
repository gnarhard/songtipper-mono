# Song Tipper

The website and backend for Song Tipper.

## Installation Requirements

- PHP `^8.2` (project currently running on PHP `8.5.x`)
- Composer `2.x`
- Node.js `>=18` and npm
- MySQL or MariaDB (default `.env` uses `DB_CONNECTION=mysql`)
- Required PHP extensions: `bcmath`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `iconv`, `json`, `libxml`, `mbstring`, `openssl`, `pcre`, `session`, `simplexml`, `tokenizer`, plus your selected DB driver (for example `pdo_mysql`)
- `imagick` PHP extension (required for chart rendering)
- Ghostscript (`gs` binary on PATH, required for chart PDF rendering)

## Valet + Chart Rendering Setup (macOS)

Install system dependencies with Homebrew:

```bash
brew install ghostscript imagemagick
```

Install the PHP `imagick` extension:

```bash
pecl install imagick
```

If `imagick` is not auto-enabled, add this to your active `php.ini`:

```ini
extension=imagick
```

When using Laravel Valet, set the Homebrew PATH in your active Valet FPM pool config
(for example `/opt/homebrew/etc/php/8.5/php-fpm.d/valet-fpm.conf`):

```ini
env[PATH] = /opt/homebrew/bin:/opt/homebrew/sbin:/usr/bin:/bin:/usr/sbin:/sbin
```

Then restart PHP/Valet services:

```bash
brew services restart php
valet restart
```

## Install

1. Create a local database (for example `songtipper`).
2. Install backend dependencies and bootstrap the app:
   ```bash
   composer setup
   ```
3. If needed, update `.env` values (especially DB credentials and `APP_URL`).
   For sharper chart renders, set `CHART_RENDER_DPI=300` (or higher, up to 600).
4. Start local development services:
   ```bash
   composer dev
   ```

`composer dev` starts the Laravel server, queue worker, log tail, and Vite.

## AI Provider

All AI features (metadata enrichment, setlist generation) use the provider selected by `AI_PROVIDER`.

Supported values:

- `anthropic` (default)
- `openai`
- `gemini`

To use Anthropic (Claude), set these values in `web/.env`:

```dotenv
AI_PROVIDER=anthropic
ANTHROPIC_ENABLED=true
ANTHROPIC_API_KEY=your-anthropic-api-key
ANTHROPIC_MODEL=claude-sonnet-4-6
ANTHROPIC_TIMEOUT_SECONDS=45
```

Anthropic is the default when `AI_PROVIDER` is unset or invalid.

## Complimentary Billing Access

You can grant yourself complimentary performer billing in two supported ways.

### One-off discount for an existing account

Run the billing grant command with your existing user email:

```bash
php artisan billing:grant-discount you@example.com lifetime --plan=pro_yearly
```

- Supported discount types are `free_year` and `lifetime`.
- `--plan` is optional, but if you omit it for a user with no selected billing plan yet, they will still need to choose Pro or Veteran during setup before access is unlocked.
- If the user already has an active Stripe subscription, the command attempts to cancel it before applying the complimentary discount.

### Auto-apply lifetime access for the owner account

Set your email in `.env` to have the app automatically sync lifetime complimentary access during registration, login, and billing setup:

```dotenv
BILLING_OWNER_LIFETIME_DISCOUNT_EMAIL=you@example.com
BILLING_OWNER_DEFAULT_PLAN=pro_yearly
```

- `BILLING_OWNER_DEFAULT_PLAN` defaults to `pro_yearly` if omitted.
- Supported plans are `pro_monthly`, `pro_yearly`, and `veteran_monthly`.

## Production (Forge) Queue Setup

Bulk chart import now uses dedicated queues:

- `imports` for `ProcessImportedChart` (AI chart identification via the configured provider)
- `renders` for `RenderChartPages` (PDF page rendering)
- `default` for everything else

### Required `.env` values

```dotenv
QUEUE_CONNECTION=redis
CHART_IDENTIFICATION_QUEUE=imports
CHART_RENDER_QUEUE=renders
REDIS_QUEUE_RETRY_AFTER=1500
```

If you use the database queue driver instead of Redis, set:

```dotenv
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=1500
```

Set `*_QUEUE_RETRY_AFTER` greater than your longest worker `--timeout`.

### Forge worker processes

Create separate queue workers so rendering does not block other jobs:

```bash
php artisan queue:work redis --queue=imports --sleep=1 --tries=1 --timeout=300
php artisan queue:work redis --queue=renders --sleep=1 --tries=1 --timeout=1200
php artisan queue:work redis --queue=default --sleep=1 --tries=1 --timeout=120
```

If using the database queue driver, replace `redis` with `database`.

### Server packages required for render jobs

- PHP extension: `imagick`
- System binary on PATH: `gs` (Ghostscript)

Without these, `RenderChartPages` jobs will fail and charts stay in pending/failed render status.

### Deployment steps

After deploy on Forge:

```bash
php artisan migrate --force
php artisan queue:restart
```

Queue health check command:

```bash
php artisan charts:queue-stats --json
```
