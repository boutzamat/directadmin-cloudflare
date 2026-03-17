#!/bin/bash

source /usr/local/directadmin/plugins/cloudflare_sync/triggers/_active.sh

DOMAIN="${DOMAIN:-$domain}"
[ -z "$DOMAIN" ] && exit 0

QUEUE="/usr/local/directadmin/plugins/cloudflare_sync/storage/queue"

echo "$DOMAIN" >> "$QUEUE"

exit 0