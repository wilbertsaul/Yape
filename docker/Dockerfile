# PHP 8.5.13 — parche de nov/2026 (PHP2Shell + CVE-2026-129289 / CVE-2026-126714)
# No se deriva de bar_php para no afectar a los demás proyectos del VPS.
FROM php:8.5.13-fpm

# La imagen base ya trae curl, dom, mbstring, openssl, zlib, libxml, sqlite y sodium habilitado.
# Se añaden: MySQL, SOAP (SUNAT/greenter), ZIP (reconstrucción de boletas),
# opcache, bcmath, intl y gd (paridad con el entorno anterior).
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      libxml2-dev \
      libzip-dev \
      libicu-dev \
      libpng-dev \
      libjpeg62-turbo-dev \
      libfreetype-dev \
 && docker-php-ext-configure gd --with-jpeg --with-freetype \
 && docker-php-ext-install -j"$(nproc)" \
      pdo_mysql mysqli soap opcache bcmath intl zip gd \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/AlToque
