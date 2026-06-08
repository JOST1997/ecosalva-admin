FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY . /app/

WORKDIR /app

EXPOSE 80

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-80} -t /app"]
