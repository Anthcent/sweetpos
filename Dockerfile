FROM php:8.4.26-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libpq5 \
    && docker-php-ext-install pdo_pgsql \
    && apt-get purge -y --auto-remove libpq-dev \
    && rm -rf /var/lib/apt/lists/*

ENV SWEETPOS_DB_PATH=/data/database.sqlite \
    APACHE_RUN_DIR=/tmp/sweetpos-apache \
    APACHE_PID_FILE=/tmp/sweetpos-apache/apache2.pid \
    APACHE_LOCK_DIR=/tmp/sweetpos-apache \
    APACHE_LOG_DIR=/tmp/sweetpos-apache

RUN sed -ri 's/^Listen 80$/Listen 0.0.0.0:8080/' /etc/apache2/ports.conf

COPY container/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY container/entrypoint.sh /usr/local/bin/sweetpos-entrypoint
COPY container/bootstrap-users.php /usr/local/lib/sweetpos/bootstrap-users.php

WORKDIR /var/www/html
COPY _product_modal.php _restock_modal.php _sidebar.php favicon.ico auth.php historial.php index.php inventario.php login.php logout.php materiales.php mesas.php movimientos.php panel.php produccion.php reportes.php setup_guard.php usuarios.php ./
COPY api/ ./api/
COPY assets/ ./assets/
COPY config/ ./config/
COPY lib/ ./lib/

RUN chmod 0555 /usr/local/bin/sweetpos-entrypoint \
    && mkdir -p /data /tmp/sweetpos-apache \
    && chown -R www-data:www-data /data /tmp/sweetpos-apache \
    && chmod -R a-w /var/www/html

USER www-data

VOLUME ["/data"]
EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl --fail --silent --show-error http://127.0.0.1:8080/ >/dev/null || exit 1

ENTRYPOINT ["/usr/local/bin/sweetpos-entrypoint"]
CMD ["apache2-foreground"]
