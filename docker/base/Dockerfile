# Ubuntu's php8.5-mongodb (2.1.x) is too old for the current MongoDB PHP library.
# It is compiled here so that the compilers stay out of the final image.
FROM ubuntu:26.04 AS mongodb-extension

ARG MONGODB_EXT_VERSION=2.5.5

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        g++ \
        gcc \
        libssl-dev \
        make \
        php-pear \
        php8.5-dev \
        pkg-config \
    && pecl install "mongodb-${MONGODB_EXT_VERSION}" \
    && cp "$(php-config --extension-dir)/mongodb.so" /mongodb.so

# Downloaded in its own stage, like the extension above, to keep the final layers small.
FROM ubuntu:26.04 AS php-fpm-exporter

ARG TARGETARCH
ARG PHP_FPM_EXPORTER_VERSION=2.2.0
ARG PHP_FPM_EXPORTER_SHA256_AMD64=9de0d094aec6ad8a2ea536a5eb8e039ccda4882a2b574ac642d07e589fd5a903
ARG PHP_FPM_EXPORTER_SHA256_ARM64=bbd13fafb94f4b24e541366760d4022d6475f83dae1938053c9f99ae1a9d01f3

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
    && case "${TARGETARCH}" in \
        amd64) sha256="${PHP_FPM_EXPORTER_SHA256_AMD64}" ;; \
        arm64) sha256="${PHP_FPM_EXPORTER_SHA256_ARM64}" ;; \
        *) echo "Unsupported architecture: ${TARGETARCH}" >&2; exit 1 ;; \
    esac \
    && curl --fail --silent --show-error --location --output /tmp/php-fpm_exporter \
        "https://github.com/hipages/php-fpm_exporter/releases/download/v${PHP_FPM_EXPORTER_VERSION}/php-fpm_exporter_${PHP_FPM_EXPORTER_VERSION}_linux_${TARGETARCH}" \
    && echo "${sha256}  /tmp/php-fpm_exporter" | sha256sum --check --strict \
    && install -m 0755 /tmp/php-fpm_exporter /php-fpm_exporter

# jonsnmr/symfony-app:base - nginx and PHP-FPM runtime, without application code.
FROM ubuntu:26.04

# Match this to the owner of the code mounted on /code.
ARG APP_UID=1000
ARG APP_GID=1000

ENV DEBIAN_FRONTEND=noninteractive \
    APP_ENV=prod \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        nginx \
        php8.5-cli \
        php8.5-curl \
        php8.5-fpm \
        php8.5-intl \
        php8.5-mbstring \
        php8.5-redis \
        php8.5-xml \
        php8.5-zip \
        supervisor \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default

COPY --from=mongodb-extension /mongodb.so /usr/lib/php/mongodb.so
COPY mongodb.ini /etc/php/8.5/mods-available/mongodb.ini
RUN phpenmod mongodb

COPY --from=php-fpm-exporter /php-fpm_exporter /usr/local/bin/php-fpm_exporter

COPY nginx.conf /etc/nginx/nginx.conf
COPY php.ini /etc/php/8.5/cli/conf.d/90-app.ini
COPY php.ini /etc/php/8.5/fpm/conf.d/90-app.ini
COPY fpm-pool.conf /etc/php/8.5/fpm/pool.d/www.conf
COPY supervisord.conf /etc/supervisor/supervisord.conf

# Ubuntu's default log path is not writable by the non-root user.
RUN sed -i 's#^error_log = .*#error_log = /proc/self/fd/2#' /etc/php/8.5/fpm/php-fpm.conf

# The stock "ubuntu" user occupies UID/GID 1000.
RUN userdel --remove ubuntu \
    && groupadd --gid "${APP_GID}" app \
    && useradd --uid "${APP_UID}" --gid app --create-home --shell /bin/bash app \
    && install -d -o app -g app /code /run/php /run/nginx /run/supervisor /var/lib/nginx /var/log/nginx

WORKDIR /code
USER app
EXPOSE 8080 9253

CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]
