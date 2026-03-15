FROM php:8.2-apache

RUN a2enmod rewrite

COPY docker/apache/entrypoint.sh /usr/local/bin/extmgr-entrypoint.sh
RUN chmod +x /usr/local/bin/extmgr-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/extmgr-entrypoint.sh"]
CMD ["apache2-foreground"]
