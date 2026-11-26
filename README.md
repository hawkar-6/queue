# Grade Reports API

Open this folder in VS Code. PHP 8.2+ with PDO SQLite and mbstring is required.
On this Windows machine mbstring is installed but disabled. In each new PHP terminal run:

    $env:PHP_INI_SCAN_DIR = (Resolve-Path .\dev\php).Path

## Run the demo

1. Start the API: php artisan serve
2. Open a second terminal and start the worker: php artisan queue:work database
3. Open a third PowerShell terminal and run: powershell -ExecutionPolicy Bypass -File .\demo.ps1
4. Open http://127.0.0.1:8000/docs/index.html. The OpenAPI file is /docs/openapi.yaml.

For a live presentation, stop the worker, submit scores and inspect the queued report. Start the worker and read the same ID again. Scores [60, 80, 100] give count 3, average 80, highest 100 and lowest 60.

## Requirements in the code

- routes/api.php defines register, login, logout, submit and read routes, Sanctum authentication middleware and rate limits.
- app/Policies/ReportPolicy.php permits only the owner to view a report.
- app/Jobs/GenerateReport.php calculates the grade summary, retries transient failures up to three attempts and marks terminal failures.
- app/Http/Controllers handles validation, token issuance and report submissions. The report and queue entry are saved in the same database transaction.
- bootstrap/app.php ensures API failures return JSON. Examples include 401 for a missing token, 403 for another owner's report, 404 for missing data, 422 for invalid scores, and 429 for exceeding the rate limit.
- config/scribe.php configures documentation and bearer tokens. To regenerate HTML and OpenAPI run php artisan scribe:generate.
- Run php artisan test to check access control, validation, token revocation and a real database queue worker using an isolated SQLite test database.

## Fresh installation on another machine

Install dependencies with composer install. Copy .env.example to .env, set APP_URL=http://127.0.0.1:8000, run php artisan key:generate, create an empty database/database.sqlite if missing, then run php artisan migrate and php artisan scribe:generate. Use SQLite and the database queue on the same database connection.

## Class report batches

Import postman/Class-Batches.postman_collection.json and send its requests in order.
Classes use their own queue. Stop continuous workers listening to class-reports before
presenting and finish any older demo batches. The default worker for individual reports
can continue running. Start the API as described above.

- Before requests 04 and 07, run one job: php artisan queue:work database --queue=class-reports --once
- Before requests 05 and 12, finish outstanding jobs: php artisan queue:work database --queue=class-reports --stop-when-empty
- 03 to 05 show four class reports moving from 0% to 25% to 100%.
- 06 to 12 show a second batch. A guest receives 403 when attempting to cancel it.
  Its owner cancels the last three reports. The first class keeps its result and progress stays at 25%.

POST /api/report-batches accepts classes with a name and a list of scores.
GET /api/report-batches/{batch} returns overall progress and results per class.
POST /api/report-batches/{batch}/cancel cancels outstanding reports. Only the owner
can read or cancel. Cancelling an already completed batch returns 409.
