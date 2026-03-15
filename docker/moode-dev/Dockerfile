FROM php:8.2-apache

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
  && rm -rf /var/lib/apt/lists/*

# Install Node.js LTS (v20)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
  && apt-get install -y nodejs \
  && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite headers \
  && sed -ri 's!/var/www/html!/var/www!g' /etc/apache2/sites-available/000-default.conf \
  && sed -ri 's!/var/www/html!/var/www!g' /etc/apache2/apache2.conf

# Create moOde-like user/group structure
RUN groupadd -r moode-extmgr || true \
  && useradd -r -g moode-extmgr moode-extmgrusr || true \
  && usermod -aG moode-extmgr www-data || true

# Create moOde directory structure
RUN mkdir -p /var/www/extensions/sys \
  && mkdir -p /var/www/extensions/installed \
  && mkdir -p /var/www/extensions/cache \
  && mkdir -p /var/www/inc \
  && mkdir -p /var/www/templates \
  && chown -R www-data:moode-extmgr /var/www/extensions \
  && chmod -R 775 /var/www/extensions

COPY docker/moode-dev/entrypoint.sh /usr/local/bin/moode-dev-entrypoint.sh
RUN chmod +x /usr/local/bin/moode-dev-entrypoint.sh

# Verify installations
RUN php -v && node -v && python3 --version

WORKDIR /var/www

ENTRYPOINT ["/usr/local/bin/moode-dev-entrypoint.sh"]
CMD ["apache2-foreground"]
