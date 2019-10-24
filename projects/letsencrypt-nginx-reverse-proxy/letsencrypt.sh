#!/usr/bin/env sh
set -e
set -x

[ -e /tmp/letsencrypt ] || mkdir /tmp/letsencrypt

COMMAND="/usr/local/bin/certbot-auto"
SWITCHES="--non-interactive --webroot --webroot-path /tmp/letsencrypt --agree-tos --register-unsafely-without-email"

eval "${COMMAND} renew ${SWITCHES}"

for RULE in `echo "${RULES}" | sed "s/,/\n/g"`; do (
    DOMAIN=$(echo "${RULE}" | cut -d ">" -f 1);
    SSL_PATH="/etc/letsencrypt/live/${DOMAIN}"

    if [ -e "${SSL_PATH}/.fake" ]; then(
        rm -rf "${SSL_PATH}"
        eval "${COMMAND} certonly ${SWITCHES} -d ${DOMAIN}"
    ); fi
); done
