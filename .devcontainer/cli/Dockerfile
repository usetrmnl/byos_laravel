# From official php image.
FROM php:8.4-cli-alpine
# Create a user group and account under id 1000.
RUN addgroup -g 1000 -S user && adduser -u 1000 -D user -G user
# Install quality-of-life packages.
RUN apk add --no-cache bash curl git vim
# Install composer for php deps.
RUN apk add --no-cache composer
# Add Chromium and Image Magick for puppeteer.
RUN apk add --no-cache \
  imagemagick-dev \
  chromium

ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium
ENV PUPPETEER_DOCKER=1

RUN mkdir -p /usr/src/php/ext/imagick
RUN chmod 777 /usr/src/php/ext/imagick
RUN curl -fsSL https://github.com/Imagick/imagick/archive/refs/tags/3.8.0.tar.gz | tar xvz -C "/usr/src/php/ext/imagick" --strip 1

# Install PHP extensions
RUN docker-php-ext-install imagick

# Composer uses its php binary, but we want it to use the container's one
RUN rm -f /usr/bin/php84
RUN ln -s /usr/local/bin/php /usr/bin/php84
# Install postgres pdo driver.
# RUN apk add --no-cache postgresql-dev && docker-php-ext-install pdo_pgsql
# Install redis driver.
# RUN mkdir -p /usr/src/php/ext/redis; \
#     curl -fsSL --ipv4 https://github.com/phpredis/phpredis/archive/6.0.2.tar.gz | tar xvz -C "/usr/src/php/ext/redis" --strip 1; \
#     docker-php-ext-install redis
# Install nodejs and npm for frontend.
RUN apk add --no-cache nodejs npm
# Prevent container from exiting early.
CMD ["sleep", "infinity"]
