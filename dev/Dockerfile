# Local development only — production runs on OVH hosting (see .ovhconfig).
# Same PHP minor version as production; mbstring, openssl, curl, iconv and
# zlib are built into the official image, zip (ZipArchive for LENEX .lxf) is not.
# The mongodb extension backs user accounts (includes/mongo.php); composer
# installs the mongodb/mongodb library into vendor/.
FROM php:8.3-cli

RUN apt-get update \
 && apt-get install -y --no-install-recommends libzip-dev libssl-dev unzip git \
 && docker-php-ext-install zip \
 && pecl install mongodb \
 && docker-php-ext-enable mongodb \
 && rm -rf /var/lib/apt/lists/* /tmp/pear

# mail() writes to a file instead of sending (account e-mail links for testing);
# a script, because PHP appends the -f sender argument to the command
RUN printf '#!/bin/sh\ncat >> /tmp/mails.log\n' > /usr/local/bin/devmail \
 && chmod +x /usr/local/bin/devmail \
 && echo 'sendmail_path = /usr/local/bin/devmail' > /usr/local/etc/php/conf.d/dev-mail.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "/app"]
