#!/bin/sh
set -eu

# Keep the SQLite volume as a local-development fallback. With Dokploy's managed
# PostgreSQL, api/db.php reads DATABASE_URL and runs migrations on that database.
: "${SWEETPOS_DB_PATH:=/data/database.sqlite}"
export SWEETPOS_DB_PATH
if [ -z "${DATABASE_URL:-}" ]; then
    mkdir -p "$(dirname "$SWEETPOS_DB_PATH")"
fi

# The shared DB bootstrap applies pending migrations and provisions initial
# accounts before Apache accepts traffic.
php /usr/local/lib/sweetpos/bootstrap-users.php

exec apache2-foreground
