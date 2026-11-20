FROM php:8.4-apache@sha256:901b0dbcd2419cc9cd307ea05e57403449ce722ccd5b32335da6e8d39f2b1ee0
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libonig-dev unzip \
    && docker-php-ext-install pdo_pgsql mbstring opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY . ./
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
COPY deploy/vps/apache.conf /etc/apache2/sites-available/000-default.conf
RUN printf 'Listen 127.0.0.1:8011\n' > /etc/apache2/ports.conf \
    && printf 'ServerName localhost\n' > /etc/apache2/conf-enabled/servername.conf \
    && printf 'upload_max_filesize=6M\npost_max_size=8M\nmemory_limit=128M\nexpose_php=Off\n' > /usr/local/etc/php/conf.d/lola.ini
# Produksi: PHP tidak mengecek ulang file tiap request (kode hanya berubah lewat build ulang image).
RUN printf 'opcache.enable=1\nopcache.validate_timestamps=0\nopcache.memory_consumption=64\nopcache.max_accelerated_files=10000\nrealpath_cache_size=4096K\nrealpath_cache_ttl=600\n' > /usr/local/etc/php/conf.d/opcache-prod.ini
# Cache konfigurasi, route, event, dan view Laravel saat container mulai (nilai .env baru terbaca di sini),
# lalu kembalikan kepemilikan ke www-data supaya Apache tetap bisa menulis cache view.
CMD ["sh", "-c", "php artisan optimize || echo \"optimize gagal, jalan tanpa cache\" >&2; chown -R www-data:www-data bootstrap/cache storage/framework && exec apache2-foreground"]
