# moOde-like development environment
# Uses NGINX + PHP-FPM (same as real moOde)
FROM php:8.2-fpm-bookworm

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
  curl \
  git \
  unzip \
  sudo \
  bash \
  procps \
  python3 \
  python3-pip \
  sqlite3 \
  libsqlite3-dev \
  libonig-dev \
  libzip-dev \
  nginx \
  supervisor \
  && rm -rf /var/lib/apt/lists/*

# Install PHP extensions required by moOde
RUN docker-php-ext-install pdo pdo_sqlite mbstring zip

# Install Node.js LTS (v20) for Gulp
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
  && apt-get install -y nodejs \
  && rm -rf /var/lib/apt/lists/*

# Create moOde-like user/group structure
RUN groupadd -r moode-extmgr || true \
  && useradd -r -g moode-extmgr moode-extmgrusr || true \
  && usermod -aG moode-extmgr www-data || true

# Clone moOde source code (full www directory)
RUN mkdir -p /opt/moode-src \
  && cd /opt/moode-src \
  && git init \
  && git remote add origin https://github.com/moode-player/moode.git \
  && git config core.sparseCheckout true \
  && echo "www/" >> .git/info/sparse-checkout \
  && echo "var/local/www/db/" >> .git/info/sparse-checkout \
  && git pull --depth=1 origin master

# Copy moOde www files to /var/www
RUN cp -r /opt/moode-src/www/* /var/www/ \
  && mkdir -p /var/www/extensions/sys/assets/js \
  && mkdir -p /var/www/extensions/sys/assets/css \
  && mkdir -p /var/www/extensions/sys/assets/data \
  && mkdir -p /var/www/extensions/sys/scripts \
  && mkdir -p /var/www/extensions/sys/logs \
  && mkdir -p /var/www/extensions/installed \
  && mkdir -p /var/www/extensions/cache \
  && mkdir -p /var/local/www/db \
  && mkdir -p /var/local/www/imagesw

# Copy moOde database template
RUN if [ -f /opt/moode-src/var/local/www/db/moode-sqlite3.db.sql ]; then \
  sqlite3 /var/local/www/db/moode-sqlite3.db < /opt/moode-src/var/local/www/db/moode-sqlite3.db.sql; \
  else \
  sqlite3 /var/local/www/db/moode-sqlite3.db "CREATE TABLE IF NOT EXISTS cfg_system (id INTEGER PRIMARY KEY, param TEXT, value TEXT);"; \
  fi

# Configure NGINX (moOde-like config)
COPY docker/nginx-fpm/nginx/default.conf /etc/nginx/sites-available/default
COPY docker/nginx-fpm/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx-fpm/nginx/fastcgi_params /etc/nginx/fastcgi_params
RUN mkdir -p /etc/nginx/snippets
COPY docker/nginx-fpm/nginx/snippets/fastcgi-php.conf /etc/nginx/snippets/fastcgi-php.conf

# Configure PHP-FPM
COPY docker/nginx-fpm/php-fpm/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/nginx-fpm/php-fpm/php.ini /usr/local/etc/php/conf.d/moode.ini

# Configure Supervisor to run both NGINX and PHP-FPM
COPY docker/nginx-fpm/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Set permissions
RUN chown -R www-data:moode-extmgr /var/www/extensions \
  && chmod -R 775 /var/www/extensions \
  && chown -R www-data:www-data /var/local/www \
  && chmod -R 775 /var/local/www

COPY docker/nginx-fpm/entrypoint.sh /usr/local/bin/moode-dev-entrypoint.sh
RUN chmod +x /usr/local/bin/moode-dev-entrypoint.sh

# Verify installations
RUN php -v && node -v && python3 --version && nginx -v

WORKDIR /var/www

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/moode-dev-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
