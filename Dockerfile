#https://www.cloudsigma.com/deploying-laravel-nginx-and-mysql-with-docker-compose/

# Use the official PHP image
FROM php:8.2-fpm

# Set environment variables
#ENV NVM_DIR /usr/local/nvm
ENV NODE_VERSION=18.17.1
ENV NPM_VERSION=8.10.0
ENV NVM_DIR /root/.nvm
ENV CLIENT_HOST=192.168.12.120
ENV XDEBUG_CLIENT_PORT=9001
ENV OCTANE_SERVER=roadrunner
ENV OCTANE_PORT=8000
ENV NGINX_CONFIG_PATH=./nginx/roadrunner.conf.d

# Copy composer.lock and composer.json into the working directory
COPY composer.lock composer.json /var/www/html/

# Set working directory
WORKDIR /var/www/html/

# Install system dependencies
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    locales \
    zip \
    jpegoptim optipng pngquant gifsicle \
    vim \
    libzip-dev \
    unzip \
    git \
    libonig-dev \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring zip exif pcntl gd

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy application files
COPY . /var/www/html

# Set permissions
RUN chown -R www-data:www-data \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache \
    /tmp

# Install Xdebug
RUN pecl install xdebug-3.2.2 && docker-php-ext-enable xdebug && docker-php-ext-install sockets
RUN echo "xdebug.client_host=${CLIENT_HOST}" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.client_port=${XDEBUG_CLIENT_PORT}" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.force_display_errors=1" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.remote_handler=dbgp" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.mode=develop,debug,coverage" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.discover_client_host=0" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.log=/tmp/xdebug.log" >> /usr/local/etc/php/conf.d/xdebug.ini


#COPY .env.example .env
#RUN php artisan key:generate
RUN composer install

COPY ${NGINX_CONFIG_PATH} /etc/nginx/conf.d

# Install dependencies and set up Octane server
RUN apt-get update && apt-get install -y \
    libssl-dev \
    && rm -rf /var/lib/apt/lists/* \
    && if [ "${OCTANE_SERVER}" = "roadrunner" ]; then \
        php artisan octane:install --server="roadrunner" && \
        chmod +x ./vendor/bin/rr && ./vendor/bin/rr get-binary --no-interaction && \
        echo "upstream roadrunner_upstream { ip_hash; server 127.0.0.1:${OCTANE_PORT}; keepalive 64; }" >> /etc/nginx/conf.d/app.conf; \
    elif [ "${OCTANE_SERVER}" = "swoole" ]; then \
        pecl install swoole && \
        php artisan octane:install --server="swoole" && \
        echo "upstream swoole_upstream { server 127.0.0.1:${OCTANE_PORT}; }" >> /etc/nginx/conf.d/app.conf; \
    else \
        echo "No valid octane server specified"; \
    fi

# Install NVM and Node.js
RUN curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.7/install.sh | bash \
&& export NVM_DIR="${NVM_DIR}" \
    && . "${NVM_DIR}/nvm.sh" \
    && nvm install ${NODE_VERSION} \
    && nvm alias default v${NODE_VERSION} \
    && nvm use default

# add node and npm to path so the commands are available
ENV NODE_PATH $NVM_DIR/v$NODE_VERSION/lib/node_modules
ENV PATH $NVM_DIR/versions/node/v$NODE_VERSION/bin:$PATH

# Install npm dependencies
RUN npm install

#RUN php artisan octane:start --server="roadrunner" --host="0.0.0.0" --rpc-port="6001" --port="8000" --watch
#RUN php artisan octane:start --server="swoole" --host="0.0.0.0" --watch

# Expose octane and xdebug ports
EXPOSE ${OCTANE_PORT} ${XDEBUG_CLIENT_PORT}

# Set the default command
CMD ["php-fpm"]
