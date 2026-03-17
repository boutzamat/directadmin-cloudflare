#!/bin/bash

# Check if the plugin is active before proceeding
source /usr/local/directadmin/plugins/cloudflare_sync/triggers/_active.sh

DOMAIN="${DOMAIN:-$domain}"
[ -z "$DOMAIN" ] && exit 0

/usr/bin/php /usr/local/directadmin/plugins/cloudflare_sync/bin/cloudflare_sync-cli.php delete-zone "$DOMAIN" >>/var/log/cloudflare_sync.log 2>&1 &

echo "$(date) domain_destroy_post $DOMAIN" >>/var/log/cloudflare_sync.log
