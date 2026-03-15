# Apache variant for simpler/quicker testing
# Note: moOde production uses NGINX + PHP-FPM, not Apache
FROM php:8.2-apache

# Install SQLite and PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
  sqlite3 \
  libsqlite3-dev \
  curl \
  && docker-php-ext-install pdo pdo_sqlite \
  && rm -rf /var/lib/apt/lists/* \
  && a2enmod rewrite

# Enable FollowSymLinks for ext-mgr source
COPY docker/apache/apache-extmgr.conf /etc/apache2/conf-available/extmgr.conf
RUN a2enconf extmgr

COPY docker/apache/entrypoint.sh /usr/local/bin/extmgr-entrypoint.sh
RUN chmod +x /usr/local/bin/extmgr-entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["/usr/local/bin/extmgr-entrypoint.sh"]
CMD ["apache2-foreground"]
