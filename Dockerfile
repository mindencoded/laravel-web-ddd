#https://www.cloudsigma.com/deploying-laravel-nginx-and-mysql-with-docker-compose/

# Use the official PHP image
FROM php:8.2-fpm

ARG OCTANE_SERVER
ARG OCTANE_PROXY_PORT
ARG OCTANE_RPC_PORT
ARG XDEBUG_CLIENT_HOST
ARG XDEBUG_CLIENT_PORT

# Set environment variables
ENV NODE_VERSION=18.17.1
ENV NPM_VERSION=8.10.0
ENV NVM_DIR /root/.nvm
ENV XDEBUG_CLIENT_HOST=$XDEBUG_CLIENT_HOST
ENV XDEBUG_CLIENT_PORT=$XDEBUG_CLIENT_PORT
ENV OCTANE_SERVER=$OCTANE_SERVER
ENV OCTANE_PROXY_PORT=$OCTANE_PROXY_PORT
ENV OCTANE_RPC_PORT=$OCTANE_RPC_PORT
ENV XDEBUG_CONFIG_FILE=/usr/local/etc/php/conf.d/xdebug.ini

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
    libcurl4-openssl-dev \
    libpq-dev \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring zip exif pcntl gd

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install Xdebug
RUN pecl install xdebug-3.2.2 && docker-php-ext-enable xdebug && docker-php-ext-install sockets
RUN echo "xdebug.client_host=${XDEBUG_CLIENT_HOST}" >> ${XDEBUG_CONFIG_FILE} \
    && echo "xdebug.client_port=${XDEBUG_CLIENT_PORT}" >> ${XDEBUG_CONFIG_FILE} \
    && echo "xdebug.start_with_request=yes" >> ${XDEBUG_CONFIG_FILE} \
    && echo "xdebug.force_display_errors=1" >> ${XDEBUG_CONFIG_FILE} \
    && echo "xdebug.remote_handler=dbgp" >> ${XDEBUG_CONFIG_FILE} \
    && echo "xdebug.mode=develop,debug,coverage" >> ${XDEBUG_CONFIG_FILE} \
    && echo "xdebug.discover_client_host=0" >> ${XDEBUG_CONFIG_FILE} \
    && echo "xdebug.log=/tmp/xdebug.log" >> ${XDEBUG_CONFIG_FILE}

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

# Copy application files
COPY . /var/www/html

#Copy environment file
#COPY .env.example .env

# Set permissions
RUN chown -R www-data:www-data \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache \
    /tmp

RUN chmod -R 777 /var/www/html/storage

RUN composer install --no-dev --optimize-autoloader

#Create key app
#RUN php artisan key:generate

# Install npm dependencies
RUN npm install

SHELL ["/bin/bash", "-c"]

RUN if [[ "${OCTANE_SERVER}" == "roadrunner" ]]; then \
        php artisan octane:install --server="roadrunner" && \
        chmod +x ./vendor/bin/rr && ./vendor/bin/rr get-binary --no-interaction; \
    elif [[ "${OCTANE_SERVER}" == "swoole" ]]; then \
        yes no | pecl install swoole && \
        touch /usr/local/etc/php/conf.d/swoole.ini && \
        echo 'extension=swoole.so' > /usr/local/etc/php/conf.d/swoole.ini && \
        php artisan octane:install --server="swoole"; \
    else \
        echo "No valid octane server."; \
    fi

# Expose octane-start-server and xdebug ports
EXPOSE ${XDEBUG_CLIENT_PORT} ${OCTANE_PROXY_PORT} ${OCTANE_RPC_PORT}

#Run FastCGI Process Manager (FPM)
CMD ["php-fpm"]

#Run Octane Server
CMD bash -c 'if [[ "${OCTANE_SERVER}" == "roadrunner" || "${OCTANE_SERVER}" == "swoole" ]]; then php artisan octane:start --server=${OCTANE_SERVER} --host=0.0.0.0 --rpc-port=${OCTANE_RPC_PORT} --port=${OCTANE_PROXY_PORT} --watch; fi'

