FROM php:8.3.3-fpm

ARG APP_PORT
ARG APP_DEBUG
ARG OCTANE_ENABLED
ARG OCTANE_HOST
ARG OCTANE_SERVER
ARG OCTANE_PROXY_PORT
ARG OCTANE_RPC_PORT
ARG XDEBUG_CLIENT_HOST
ARG XDEBUG_CLIENT_PORT

# Set environment variables
ENV APP_PORT=$APP_PORT
ENV APP_DEBUG=$APP_DEBUG
ENV NODE_VERSION=18.17.1
ENV NPM_VERSION=10.9.1
ENV NVM_DIR=/root/.nvm
ENV XDEBUG_CLIENT_HOST=$XDEBUG_CLIENT_HOST
ENV XDEBUG_CLIENT_PORT=$XDEBUG_CLIENT_PORT
ENV OCTANE_ENABLED=$OCTANE_ENABLED
ENV OCTANE_HOST=$OCTANE_HOST
ENV OCTANE_SERVER=$OCTANE_SERVER
ENV OCTANE_PROXY_PORT=$OCTANE_PROXY_PORT
ENV OCTANE_RPC_PORT=$OCTANE_RPC_PORT
ENV XDEBUG_CONFIG_FILE=/usr/local/etc/php/conf.d/xdebug.ini

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
RUN pecl install xdebug-3.3.2 && docker-php-ext-enable xdebug && docker-php-ext-install sockets
RUN echo "xdebug.client_host=${XDEBUG_CLIENT_HOST}" >> ${XDEBUG_CONFIG_FILE} \
    && echo "xdebug.idekey=PHPSTORM" >> ${XDEBUG_CONFIG_FILE} \
    && echo "xdebug.client_port=${XDEBUG_CLIENT_PORT}" >> ${XDEBUG_CONFIG_FILE} \
    && echo "xdebug.start_with_request=yes" >> ${XDEBUG_CONFIG_FILE} \
    && echo "xdebug.force_display_errors=1" >> ${XDEBUG_CONFIG_FILE} \
    && echo "xdebug.remote_handler=dbgp" >> ${XDEBUG_CONFIG_FILE} \
    && echo "xdebug.mode=debug" >> ${XDEBUG_CONFIG_FILE} \
    && echo "xdebug.discover_client_host=yes" >> ${XDEBUG_CONFIG_FILE} \
    && echo "/var/log/nginx/xdebug.log" >> ${XDEBUG_CONFIG_FILE}

RUN touch /tmp/xdebug.log \
    && chown www-data:www-data /tmp/xdebug.log \
    && chmod 777 /tmp/xdebug.log

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

WORKDIR /var/www/html

# Set permissions
RUN chown -R www-data:www-data \
  /var/www/html/storage \
  /var/www/html/bootstrap/cache \
  /tmp \
  && chmod -R 777 \
  /var/www/html/storage \
  /var/www/html/bootstrap/cache \
  /tmp

SHELL ["/bin/bash", "-c"]

RUN git config --global --add safe.directory /var/www/html

#RUN rm -rf vendor/*

RUN composer config --global process-timeout 0 \
    && composer install --no-autoloader

RUN if [[ "${OCTANE_ENABLED}" == "true" ]]; then \
        if [[ "${OCTANE_SERVER}" == "roadrunner" ]]; then \
            composer require spiral/roadrunner-cli spiral/roadrunner-http && \
            php artisan octane:install --server="roadrunner" && \
            chmod +x ./vendor/bin/rr && \
            ./vendor/bin/rr get-binary --no-interaction && \
            chmod +x ./vendor/bin/roadrunner-worker; \
        elif [[ "${OCTANE_SERVER}" == "swoole" ]]; then \
            yes no | pecl install swoole && \
            touch /usr/local/etc/php/conf.d/swoole.ini && \
            echo 'extension=swoole.so' > /usr/local/etc/php/conf.d/swoole.ini && \
            php artisan octane:install --server="swoole"; \
        fi \
    fi

#Clean
RUN php artisan clear-compiled \
    && php artisan optimize \
    && composer dump-autoload

#Copy environment file
#COPY .env.example .env

#Create key app
#RUN php artisan key:generate

# Install npm dependencies
RUN rm -rf node_modules \
    && rm package-lock.json \
    && npm install -g npm@${NPM_VERSION} \
    && npm install \
    && npm install -g chokidar-cli

#Basic Octane config for developer environments
COPY ./scripts /usr/local/src
RUN chmod +x /usr/local/src/start-server.sh
RUN if [[ "${OCTANE_ENABLED}" == "true" ]]; then \
      echo "php -d variables_order=EGPCS artisan octane:start --server=${OCTANE_SERVER} --host=${OCTANE_HOST} --rpc-port=${OCTANE_RPC_PORT} --port=${OCTANE_PROXY_PORT} --watch" >> /usr/local/src/start-server.sh; \
    else \
      echo "echo \"Octane not running.\"" >> /usr/local/src/start-server.sh; \
    fi

#Supervisor Octane config for production
COPY /supervisor/conf.d /etc/supervisor/conf.d/
RUN echo "command = php -d variables_order=EGPCS /var/www/html/artisan octane:start --server=${OCTANE_SERVER} --host=${OCTANE_HOST} --rpc-port=${OCTANE_RPC_PORT} --port=${OCTANE_PROXY_PORT}" >> /etc/supervisor/conf.d/laravel-octane.conf

# Expose ports
EXPOSE ${OCTANE_PROXY_PORT} ${XDEBUG_CLIENT_PORT} ${OCTANE_RPC_PORT}

#Start server
CMD ["/usr/local/src/start-server.sh"]

#Start Octane server with Supervisor
#CMD ["/usr/bin/supervisord"]
