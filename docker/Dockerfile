FROM bar_php

# SUNAT (greenter) requiere la extensión SOAP para consumir los WS.
RUN apt-get update \
 && apt-get install -y --no-install-recommends libxml2-dev \
 && docker-php-ext-install soap \
 && rm -rf /var/lib/apt/lists/*
