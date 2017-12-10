# This Dockerfile is used to test the MagiumEnvironmentFactory class
# It also often breaks other things too, so it's actually kind of useful
FROM magium/clairvoyant-chrome-php-7.1

USER seluser

COPY . /magium/

RUN cd /magium/ && composer update

ENV MAGIUM_EXEC "/magium/vendor/bin/phpunit /magium/src"
