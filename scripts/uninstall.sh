#!/bin/bash

# DirectAdmin Cloudflare Sync Plugin - Uninstall Script

PLUGIN_NAME="cloudflare_sync"
DA_PATH="/usr/local/directadmin"
PLUGIN_PATH="$DA_PATH/plugins/$PLUGIN_NAME"
SCRIPTS_PATH="$DA_PATH/scripts/custom"

echo "Uninstalling DirectAdmin Cloudflare Sync Plugin..."

# Remove symbolic links
echo "Removing DirectAdmin hooks..."

if [ -L "$SCRIPTS_PATH/dns_write_post.sh" ]; then
    rm "$SCRIPTS_PATH/dns_write_post.sh"
    if [ -f "$SCRIPTS_PATH/dns_write_post.sh.bak" ]; then
        echo "Restoring original dns_write_post.sh..."
        mv "$SCRIPTS_PATH/dns_write_post.sh.bak" "$SCRIPTS_PATH/dns_write_post.sh"
    fi
fi

if [ -L "$SCRIPTS_PATH/domain_create_post.sh" ]; then
    rm "$SCRIPTS_PATH/domain_create_post.sh"
    if [ -f "$SCRIPTS_PATH/domain_create_post.sh.bak" ]; then
        echo "Restoring original domain_create_post.sh..."
        mv "$SCRIPTS_PATH/domain_create_post.sh.bak" "$SCRIPTS_PATH/domain_create_post.sh"
    fi
fi

if [ -L "$SCRIPTS_PATH/domain_destroy_post.sh" ]; then
    rm "$SCRIPTS_PATH/domain_destroy_post.sh"
    if [ -f "$SCRIPTS_PATH/domain_destroy_post.sh.bak" ]; then
        echo "Restoring original domain_destroy_post.sh..."
        mv "$SCRIPTS_PATH/domain_destroy_post.sh.bak" "$SCRIPTS_PATH/domain_destroy_post.sh"
    fi
fi

# Remove cron job
if [ -f "/etc/cron.d/cloudflare_sync_queue" ]; then
	rm "/etc/cron.d/cloudflare_sync_queue"
fi

echo ""
echo "Plugin hooks removed successfully!"
echo ""
echo "Note: Plugin files and configuration remain at: $PLUGIN_PATH"
echo "Remove manually if you want to completely uninstall the plugin."