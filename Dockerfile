# ===============================
# Stage 1: Build Python part
# ===============================
FROM python:3.12-slim AS builder

WORKDIR /build

# Install system build dependencies (required for ARM & C-extension packages)
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    gcc \
    libffi-dev \
    libssl-dev \
    python3-dev \
    && rm -rf /var/lib/apt/lists/*

# Upgrade pip & install Poetry
RUN pip install --upgrade pip setuptools wheel && pip install poetry

COPY pyproject.toml poetry.lock ./
RUN poetry install --no-root

COPY . .
RUN poetry run python3 main.py --env prod


# ===============================
# Stage 2: Apache + PHP runtime
# ===============================
FROM php:8.4-apache

# Install cron and cleanup
RUN apt-get update && apt-get install -y --no-install-recommends cron \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache rewrite module
RUN a2enmod rewrite

# Create app directories
RUN mkdir -p /var/www/crondns/data /var/www/crondns/lib /var/www/crondns/public /var/www/crondns/templates \
    /var/lib/php/sessions
WORKDIR /var/www/crondns

# Copy built Python artifacts
COPY --from=builder /build/dist/ /var/www/crondns/

# Configure Apache site
COPY apache-crondns.conf /etc/apache2/sites-available/crondns.conf
RUN a2dissite 000-default.conf && a2ensite crondns.conf

# Permissions
RUN chown -R www-data:www-data /var/www/crondns /var/lib/php/sessions \
    && chmod -R 775 /var/www/crondns

# Add cron job
COPY crondns.cron /etc/cron.d/crondns
RUN chmod 0644 /etc/cron.d/crondns && crontab /etc/cron.d/crondns

# Restrict DocumentRoot
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/crondns/public|g' /etc/apache2/sites-available/crondns.conf

# ===============================
# PHP Custom Configuration
# ===============================
RUN { \
    echo "display_errors=On"; \
    echo "display_startup_errors=On"; \
    echo "error_reporting=32767"; \
    echo "output_buffering=4096"; \
    echo "session.save_path=/var/lib/php/sessions"; \
    echo "zlib.output_compression=Off"; \
} > /usr/local/etc/php/conf.d/custom.ini

RUN { \
    echo "opcache.enable=1"; \
    echo "opcache.memory_consumption=128"; \
    echo "opcache.revalidate_freq=2"; \
    echo "opcache.validate_timestamps=On"; \
} > /usr/local/etc/php/conf.d/opcache.ini

EXPOSE 80

# Entrypoint
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]

