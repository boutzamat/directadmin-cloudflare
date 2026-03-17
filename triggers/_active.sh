PLUGIN="/usr/local/directadmin/plugins/cloudflare_sync"
CONF="$PLUGIN/plugin.conf"

[ ! -f "$CONF" ] && exit 0
grep -q "^active=yes" "$CONF" || exit 0