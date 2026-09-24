# Local development only — production runs on OVH hosting (see .ovhconfig).
# Same PHP minor version as production; mbstring, openssl, curl, iconv and
# zlib are built into the official image, zip (ZipArchive for LENEX .lxf) is not.
FROM php:8.3-cli

RUN apt-get update \
 && apt-get install -y --no-install-recommends libzip-dev \
 && docker-php-ext-install zip \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /app
EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "/app"]
