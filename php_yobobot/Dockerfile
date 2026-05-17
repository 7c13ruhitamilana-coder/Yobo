FROM php:8.3-cli-alpine

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS curl-dev \
    && apk add --no-cache ca-certificates \
    && docker-php-ext-install curl \
    && apk del .build-deps

WORKDIR /app

COPY . /app

RUN mkdir -p /app/storage

ENV PORT=10000
ENV APP_DATA_DIR=/app/storage
ENV SESSION_COOKIE_SECURE=1

EXPOSE 10000

CMD ["sh", "-lc", "php -S 0.0.0.0:${PORT:-10000} -t public public/index.php"]
