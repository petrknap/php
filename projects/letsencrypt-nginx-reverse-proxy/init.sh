#!/usr/bin/env sh
set -e
set -x

rm /etc/nginx/conf.d/*

for RULE in `echo "${RULES}" | sed "s/,/\n/g"`; do (
    DOMAIN=$(echo "${RULE}" | cut -d ">" -f 1)
    TARGET=$(echo "${RULE}" | cut -d ">" -f 2)
    SSL_PATH="/etc/letsencrypt/live/${DOMAIN}"
    SSL_CERT="${SSL_PATH}/fullchain.pem"
    SSL_KEY="${SSL_PATH}/privkey.pem"

    [ -e "${SSL_PATH}" ] || mkdir --parent "${SSL_PATH}"
    ([ -e "${SSL_CERT}" ] && [ -e "${SSL_KEY}" ]) || (
        cp /selfsigned.crt "${SSL_CERT}"
        cp /selfsigned.key "${SSL_KEY}"
        touch "${SSL_PATH}/.fake"
    )

    cat > "/etc/nginx/conf.d/${DOMAIN}.conf" << EoS
server {
  listen 80;
  listen [::]:80;
  server_name ${DOMAIN};

  location '/' {
    return 301 https://\$server_name\$request_uri;
  }

  location '/.well-known' {
    root /tmp/letsencrypt;
  }
}

server {
  listen 443 ssl;
  listen [::]:443 ssl;
  server_name ${DOMAIN};

  ssl_certificate ${SSL_CERT};
  ssl_certificate_key ${SSL_KEY};

  location '/' {
    client_max_body_size 0;
    chunked_transfer_encoding on;

    proxy_request_buffering off;
    proxy_set_header Host \$host;
    proxy_set_header X-Forwarded-Host \$host:\$server_port;
    proxy_set_header X-Forwarded-Proto \$scheme;
    proxy_set_header X-Real-IP \$remote_addr;
    proxy_pass http://${TARGET};
  }
}
EoS
); done