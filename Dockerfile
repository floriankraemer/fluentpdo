FROM php:8.4-cli

# * Install system dependencies
# Fix for Debian Bookworm GPG key issues in PHP 8.1+ Docker images
RUN apt-get update && apt-get install -y debian-archive-keyring && \
    apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libsqlite3-dev \
    build-essential \
    pkg-config \
    default-mysql-client \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# * Install PHP extensions
RUN docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    pdo_sqlite \
    mysqli \
    zip \
    intl \
    mbstring

# * Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# * Set working directory
WORKDIR /app

# * Set default command
CMD ["php", "-a"]
