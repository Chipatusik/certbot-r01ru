#!/bin/sh

cd "$(dirname "$0")";
#SERVER='https://acme-staging-v02.api.letsencrypt.org/directory'
SERVER='https://acme-v02.api.letsencrypt.org/directory'
DOMAIN='*.domain.ru'
certbot certonly --force-renewal --agree-tos --manual-public-ip-logging-ok --manual --manual-auth-hook ./authenticator.sh  --manual-cleanup-hook ./cleanup.sh --deploy-hook ./deploy.sh --preferred-challenges dns --server $SERVER  -d $DOMAIN