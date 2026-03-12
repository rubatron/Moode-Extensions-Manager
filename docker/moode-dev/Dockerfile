FROM php:8.2-apache

RUN a2enmod rewrite \
  && sed -ri 's!/var/www/html!/var/www!g' /etc/apache2/sites-available/000-default.conf \
  && sed -ri 's!/var/www/html!/var/www!g' /etc/apache2/apache2.conf

COPY docker/moode-dev/entrypoint.sh /usr/local/bin/moode-dev-entrypoint.sh
RUN chmod +x /usr/local/bin/moode-dev-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/moode-dev-entrypoint.sh"]
CMD ["apache2-foreground"]
