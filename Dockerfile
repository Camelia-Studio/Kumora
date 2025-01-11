FROM dunglas/frankenphp@sha256:455232397cc29d3dd8f872b38e07711b9ccd4adb7f6ee2c518bffa6dc84fb05a

ENV SERVER_NAME=":80"

ARG APP_ENV=prod

RUN apt-get update && apt-get install -y --no-install-recommends \
	acl \
	file \
	gettext \
	git \
	&& rm -rf /var/lib/apt/lists/*

RUN set -eux; \
	install-php-extensions \
		@composer \
		apcu \
		intl \
		opcache \
		zip \
        pdo_mysql \
	;

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY --link composer.* symfony.* ./

ENV APP_ENV=${APP_ENV}


# On ajoute un if pour installer les dépendances de dev si APP_ENV est égal à dev
RUN composer install --no-cache --prefer-dist --no-dev --optimize-autoloader --no-scripts --no-progress;

COPY . .

RUN rm -rf var/tailwind \
    && php bin/console importmap:install \
    && php bin/console tailwind:build \
    && php bin/console asset-map:compile \
    && php bin/console cache:clear
