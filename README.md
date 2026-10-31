# Sheets Sync

Laravel app that imports data from Google Sheets into MySQL every day. A configuration stored in the database decides which spreadsheets, tabs and columns are imported, and into which tables. A small web UI (React + Bulma via Inertia) manages it.

**Stack:** PHP 8.4, Laravel 13, MySQL 8, Inertia v3 + React 19, Bulma, Google Sheets API v4, Pest.

## How it works

```
spreadsheets          one Google spreadsheet (file)       name, google_spreadsheet_id, is_active
 └─ sheet_tabs        tabs to read from the file          tab_name, header_row
     └─ tab_mappings  target DB table (many per tab)      target_table
         └─ column_mappings  sheet header → DB column     source_column, target_column, data_type
sync_runs             log of every import                 status, rows_processed, warnings, error
```

Example from the task: a tab with columns `[date, product_name, price, amount]` gets a mapping to the table `sales` with `date→date, product_name→name, price→product_price, amount→amount`. A second mapping on the same tab can fill another table with a different structure.

- **Columns are matched by header name**, ignoring case and extra spaces. Files with reordered or extra columns work with the same mapping. A mapped header that a sheet doesn't have is left empty and reported in the sync log.
- **Tables and columns are created automatically.** If the target table doesn't exist, it is created. If a mapping gets a new column, the table is altered to add it and the column is filled on the next sync. If a column's type is changed in the mapping, the table column is changed to match.
- **Each sync replaces the tab's data.** The rows a tab imported before are deleted and the current rows are inserted, in one transaction. Every row records its source (`_source_spreadsheet_id`, `_source_tab`, `_synced_at`), so several files can share one table without touching each other's rows. Renaming a tab or changing the spreadsheet ID updates the source of the rows already imported, so the next sync replaces them instead of adding duplicates.
- **One sync per spreadsheet at a time.** While a sync is running, **Sync now** does not queue another one, and a second sync (for example `sheets:sync --now`) is refused with an error in the sync log.
- **Values are cast** to `string`, `text`, `integer`, `decimal`, `date`, `datetime` or `boolean`. Values that can't be read become `NULL` and are reported in the sync log.
- **Daily run:** the scheduler runs `sheets:sync` at `SHEETS_SYNC_DAILY_AT` and queues one job per active spreadsheet.

### Errors and warnings

Every sync creates a row in `sync_runs`. Its results are shown on the **Sync log** page.

- **Errors** stop the run. It gets the status `failed`, and the message is saved in `error` and written to the Laravel log. Examples: no access to the spreadsheet, a wrong tab name, missing Google credentials. Tabs imported before the error keep their new data.
- **Warnings** don't stop the run. The data is imported, the status stays `success`, and the messages are saved in `warnings` (a JSON list):

| Case                                   | Warning                                                                                               |
| -------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| Tab has no mappings                    | `Tab "Sales": no mappings configured, skipped.`                                                       |
| Mapped header is missing in the sheet  | `Tab "Sales": column "price" not found, sales.product_price left empty.`                              |
| No mapped header is found at all       | `Tab "Sales": none of the columns mapped to sales found in header row 1, existing rows kept.`         |
| Cells can't be read as the column type | `Tab "Sales": 2 value(s) in column "date" could not be read as date, sales.date left empty for them.` |

When no mapped header is found, the import into that table is skipped, so a wrong header row or renamed headers don't wipe the data. Empty cells don't produce warnings.

## Google API credentials

The app reads sheets with a **Google API key**. No browser login is needed. The key is stored in `.env`. An API key can only read spreadsheets shared as **Anyone with the link → Viewer**.

1. Open <https://console.cloud.google.com/> and **create a project** (project picker → **New project**).
2. **Enable the API:** go to **APIs & Services → Library → Google Sheets API → Enable**.
3. **Create the key:** open **APIs & Services → Credentials → Create credentials → API key**.
4. Optionally restrict it: **Edit API key → API restrictions → Google Sheets API**.
5. Paste it when `setup.sh` asks for it, or put it in `.env` (see [Setup on Ubuntu](#setup-on-ubuntu)):
    ```dotenv
    GOOGLE_API_KEY=AIzaSy...
    ```
6. **Share each spreadsheet** as **Anyone with the link → Viewer** (**Share → General access**).

## Setup on Ubuntu

Get the Google API key first ([Google API credentials](#google-api-credentials)), then:

```bash
git clone https://github.com/iryna-knyzh/test-task-bulls.git sheets-sync && cd sheets-sync
./setup.sh                # asks for the Google API key
php artisan serve         # http://localhost:8000
```

Run it as a normal user with sudo rights, not with `sudo`. The key is typed hidden, so it doesn't appear on screen or in the shell history; Enter skips it. If `.env` already has `GOOGLE_API_KEY`, the script doesn't ask. The script:

- installs PHP 8.4, MySQL 8, Node 22 and Composer (`--skip-packages` skips this)
- creates `.env` with the Google API key and a MySQL database `sheets_sync` with a random password
- runs `composer install`, migrations with seeders, and the frontend build
- installs the cron entry for the scheduler and a systemd service `sheets-sync-worker` for the queue

## Manual setup

Requirements: PHP 8.4, Composer, Node 20+, MySQL 8.

```bash
composer install
cp .env.example .env     # set DB_* and GOOGLE_API_KEY
php artisan key:generate
php artisan migrate --seed
npm ci && npm run build
composer run dev         # web server + queue worker + vite
```

For the daily sync, add the scheduler to cron and keep a queue worker running:

```
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
php artisan queue:work --tries=1 --timeout=900
```

## Using the app

1. **Add spreadsheet:** paste the Google Sheets URL.
2. **Add tab:** enter the tab name as shown in Google Sheets, and the header row number.
3. **Add mapping:** enter the target table and the columns (sheet header → table column → type).
4. **Sync now** queues an import. The **Sync log** shows the results. Click a table name to see the imported rows.

The seeder adds a working example: Google's public sample sheet _Class Data_, mapped into the tables `students` and `student_majors`.

From the command line:

```bash
php artisan sheets:sync          # queue all active spreadsheets (what the scheduler runs)
php artisan sheets:sync --now    # run immediately and print the result
```

## Configuration

| `.env` variable        | Default    | Meaning                                                |
| ---------------------- | ---------- | ------------------------------------------------------ |
| `GOOGLE_API_KEY`       | –          | Google API key                                         |
| `SHEETS_SYNC_DAILY_AT` | `02:00`    | Daily sync time                                        |
| `QUEUE_CONNECTION`     | `database` | Set to `sync` to run "Sync now" without a queue worker |

**After changing `.env`, restart the queue worker.** Syncs run in the worker, and it reads `.env` only when it starts, so until then **Sync now** keeps failing with the old values (for example `Google API key is not configured`). Use `sudo systemctl restart sheets-sync-worker` after `setup.sh`, or stop and start `composer run dev` / `queue:work`. Web requests pick up changes right away.

## Tests

```bash
php artisan test
```

The Google API is replaced by `tests/Fakes/FakeSheetsClient`, so the tests run offline.

## Code map

```
app/Services/Sheets/SheetSyncService.php   reads tabs, matches headers, creates/alters tables, writes rows
app/Services/Sheets/GoogleSheetsClient.php Google Sheets API client (API key from .env)
app/Enums/ColumnType.php                   column types: DB column + value casting
app/Jobs/SyncSpreadsheetJob.php            queued sync of one spreadsheet
app/Console/Commands/SyncSheetsCommand.php sheets:sync
app/Http/Controllers/, app/Http/Requests/  UI endpoints and validation
resources/js/pages/                        React pages (Bulma)
routes/console.php                         daily schedule
setup.sh                                   Ubuntu installer
```
