#!/usr/bin/env bash
#
# Full setup of the Sheets Sync project on Ubuntu 22.04 / 24.04.
# Run as a normal user with sudo rights (not with sudo). Safe to re-run.
#
#   ./setup.sh                  install packages, MySQL database, app, cron and queue worker
#   ./setup.sh --skip-packages  only (re)configure the app
#
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_VERSION="8.4"
cd "$APP_DIR"

info() { printf '\n\033[1;32m==> %s\033[0m\n' "$*"; }
fail() { printf '\033[1;31m%s\033[0m\n' "$*" >&2; exit 1; }

[[ $EUID -ne 0 ]] || fail "Run ./setup.sh as your normal user (without sudo); it calls sudo itself."

# Read a key from .env (empty when missing).
env_get() { { grep -E "^$1=" .env || true; } | tail -n1 | cut -d= -f2-; }

# Set or append a key in .env.
env_set() {
    if grep -qE "^#? ?$1=" .env; then
        sed -i -E "s|^#? ?$1=.*|$1=$2|" .env
    else
        echo "$1=$2" >> .env
    fi
}

# 1. System packages ----------------------------------------------------------
if [[ "${1:-}" != "--skip-packages" ]]; then
    info "Installing PHP ${PHP_VERSION}, MySQL, Node 22 and Composer"
    export DEBIAN_FRONTEND=noninteractive
    sudo apt-get update -y
    sudo apt-get install -y software-properties-common ca-certificates curl git unzip cron
    sudo add-apt-repository -y ppa:ondrej/php
    sudo apt-get install -y mysql-server \
        php${PHP_VERSION}-{cli,mysql,mbstring,xml,curl,zip,bcmath,intl}
    sudo systemctl enable --now mysql cron

    if ! command -v node >/dev/null; then
        curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
        sudo apt-get install -y nodejs
    fi

    if ! command -v composer >/dev/null; then
        curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
        sudo php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
        rm /tmp/composer-setup.php
    fi
fi

# 2. .env and MySQL -----------------------------------------------------------
info "Creating .env and the MySQL database"
[[ -f .env ]] || cp .env.example .env

DB_PASS="$(env_get DB_PASSWORD)"
# head closes the pipe early, so tr exits with SIGPIPE; ignore that under pipefail.
# The fixed "Aa1_" suffix guarantees upper/lower/digit/special for MySQL's
# validate_password policy; "_" is safe in .env, sed and SQL quotes.
[[ -n "$DB_PASS" ]] || DB_PASS="$(LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c 20 || true)Aa1_"

env_set DB_CONNECTION mysql
# The MySQL user is created for 127.0.0.1 (TCP); "localhost" would use the
# socket and look for 'sheets_sync'@'localhost', which doesn't exist.
env_set DB_HOST 127.0.0.1
env_set DB_DATABASE sheets_sync
env_set DB_USERNAME sheets_sync
env_set DB_PASSWORD "$DB_PASS"

# Google API key: asked for when .env has none. The input is hidden and never
# lands in the shell history. A GOOGLE_API_KEY environment variable (e.g. in CI)
# is used instead of asking. Set before the queue worker starts, so the worker
# sees it without a restart.
GOOGLE_KEY="${GOOGLE_API_KEY:-}"
if [[ -z "$GOOGLE_KEY" && -z "$(env_get GOOGLE_API_KEY)" && -t 0 ]]; then
    read -rsp "Google API key (input hidden, Enter to skip): " GOOGLE_KEY
    echo
fi
if [[ -n "$GOOGLE_KEY" ]]; then
    [[ "$GOOGLE_KEY" =~ ^[A-Za-z0-9_-]+$ ]] || fail "GOOGLE_API_KEY has unexpected characters."
    env_set GOOGLE_API_KEY "$GOOGLE_KEY"
fi
chmod 600 .env

# Fresh Ubuntu installs let root in via auth_socket (sudo mysql); if root has a
# password instead, fall back to prompting for it (mysql reads it from the tty).
mysql_root() {
    if sudo mysql -e 'SELECT 1' >/dev/null 2>&1; then
        sudo mysql "$@"
    else
        echo "MySQL root needs a password on this machine."
        mysql -u root -p "$@"
    fi
}

mysql_root <<SQL
CREATE DATABASE IF NOT EXISTS sheets_sync CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS sheets_sync_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'sheets_sync'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
ALTER USER 'sheets_sync'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON sheets_sync.* TO 'sheets_sync'@'127.0.0.1';
GRANT ALL PRIVILEGES ON sheets_sync_testing.* TO 'sheets_sync'@'127.0.0.1';
SQL

# 3. Application --------------------------------------------------------------
info "Installing the application"
composer install --no-interaction --prefer-dist
[[ -n "$(env_get APP_KEY)" ]] || php artisan key:generate --force
php artisan migrate --force --seed
npm ci --no-audit --no-fund
npm run build

# 4. Daily schedule and queue worker -------------------------------------------
info "Installing cron entry and the sheets-sync-worker service"
PHP_BIN="$(command -v php)"
# The "# sheets-sync" marker lets re-runs replace only our own line.
CRON_LINE="* * * * * cd ${APP_DIR} && ${PHP_BIN} artisan schedule:run >/dev/null 2>&1 # sheets-sync"
( crontab -l 2>/dev/null | grep -vF '# sheets-sync' || true; echo "$CRON_LINE" ) | crontab -

sudo tee /etc/systemd/system/sheets-sync-worker.service >/dev/null <<UNIT
[Unit]
Description=Sheets Sync queue worker
After=network.target mysql.service

[Service]
User=${USER}
WorkingDirectory=${APP_DIR}
ExecStart=${PHP_BIN} artisan queue:work --tries=1 --timeout=900
Restart=always

[Install]
WantedBy=multi-user.target
UNIT
sudo systemctl daemon-reload
sudo systemctl enable sheets-sync-worker
sudo systemctl restart sheets-sync-worker

info "Done"
if [[ -z "$(env_get GOOGLE_API_KEY)" ]]; then
    cat <<NOKEY
  GOOGLE_API_KEY is not set. Add it to .env (see README), then restart the worker:
      sudo systemctl restart sheets-sync-worker
NOKEY
fi
cat <<SUMMARY
  Start the app:  php artisan serve   →  http://localhost:8000
SUMMARY
