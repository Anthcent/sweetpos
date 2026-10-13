#!/bin/sh
set -eu

: "${SWEETPOS_DB_PATH:=/data/database.sqlite}"
export SWEETPOS_DB_PATH

db_dir=$(dirname "$SWEETPOS_DB_PATH")
mkdir -p "$db_dir"

# Apply every pending SQLite migration and provision the initial accounts before
# Apache accepts traffic. The database directory must be writable by www-data.
php /var/www/html/api/migrate.php
php /usr/local/lib/sweetpos/bootstrap-users.php

exec apache2-foreground
