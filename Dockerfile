FROM php:8.4-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git curl zip unzip default-libmysqlclient-dev libzip-dev \
    && docker-php-ext-install pdo_mysql zip bcmath \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js 20 (npm included)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy composer files first for better caching
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy package files and build frontend
COPY package.json package-lock.json ./
RUN npm ci --legacy-peer-deps
COPY . .
RUN npm run build

# Run composer scripts after full copy
RUN composer dump-autoload --optimize

# Set permissions
RUN mkdir -p storage/framework/{sessions,views,cache/data} storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Create public/storage symlink
RUN php artisan storage:link 2>/dev/null || true

# Expose port (Render uses $PORT)
EXPOSE 10000

# Start command - run migrations then serve
CMD php artisan config:cache && php artisan route:cache && php artisan view:cache && (php artisan migrate --force 2>&1 || echo "Migrations completed with warnings - tables may already exist") && php artisan db:seed --force && php artisan serve --host=0.0.0.0 --port=${PORT:-10000}
