#!/bin/bash

# DirectAdmin Cloudflare Sync Plugin - Installation Script

PLUGIN_NAME="cloudflare_sync"
DA_PATH="/usr/local/directadmin"
PLUGIN_PATH="$DA_PATH/plugins/$PLUGIN_NAME"
SCRIPTS_PATH="$DA_PATH/scripts/custom"

echo "Installing DirectAdmin Cloudflare Sync Plugin..."

# Create custom scripts directory if it doesn't exist
if [ ! -d "$SCRIPTS_PATH" ]; then
    echo "Creating custom scripts directory..."
    mkdir -p "$SCRIPTS_PATH"
fi

# Set executable permissions
echo "Setting permissions..."
chmod +x "$PLUGIN_PATH/cloudflare_sync.php"
chmod +x "$PLUGIN_PATH/triggers/"*.sh

# Create symbolic links for hooks
echo "Setting up DirectAdmin hooks..."

# DNS write hook
if [ -f "$SCRIPTS_PATH/dns_write_post.sh" ]; then
    echo "Backing up existing dns_write_post.sh..."
    mv "$SCRIPTS_PATH/dns_write_post.sh" "$SCRIPTS_PATH/dns_write_post.sh.bak"
fi
ln -s "$PLUGIN_PATH/triggers/dns_write_post.sh" "$SCRIPTS_PATH/dns_write_post.sh"

# Domain create hook
if [ -f "$SCRIPTS_PATH/domain_create_post.sh" ]; then
    echo "Backing up existing domain_create_post.sh..."
    mv "$SCRIPTS_PATH/domain_create_post.sh" "$SCRIPTS_PATH/domain_create_post.sh.bak"
fi
ln -s "$PLUGIN_PATH/triggers/domain_create_post.sh" "$SCRIPTS_PATH/domain_create_post.sh"

# Domain destroy hook
if [ -f "$SCRIPTS_PATH/domain_destroy_post.sh" ]; then
    echo "Backing up existing domain_destroy_post.sh..."
    mv "$SCRIPTS_PATH/domain_destroy_post.sh" "$SCRIPTS_PATH/domain_destroy_post.sh.bak"
fi
ln -s "$PLUGIN_PATH/triggers/domain_destroy_post.sh" "$SCRIPTS_PATH/domain_destroy_post.sh"

# Create storage directories
echo "Creating storage directories..."
mkdir -p "$PLUGIN_PATH/storage/locks"
mkdir -p "$PLUGIN_PATH/storage/metadata"

# Set proper ownership
chown -R diradmin:diradmin "$PLUGIN_PATH"

# Create queue directory and files
mkdir -p "$PLUGIN_PATH/storage"
touch "$PLUGIN_PATH/storage/queue"
chown diradmin:diradmin "$PLUGIN_PATH/storage/queue"

# Create the cron script
CRON_FILE="/etc/cron.d/cloudflare_sync_queue"
printf "* * * * * root /usr/bin/php /usr/local/directadmin/plugins/cloudflare_sync/bin/queue-worker.php >/dev/null 2>&1\n" > "$CRON_FILE"
chown root:root "$CRON_FILE"
chmod 644 "$CRON_FILE"

# Create log file
LOG_FILE="/var/log/cloudflare_sync.log"
touch "$LOG_FILE"
chmod 644 "$LOG_FILE"

echo ""
echo "Installation completed!"
echo ""
echo "Next steps:"
echo "1. Access the admin configuration at: /CMD_PLUGINS_ADMIN/$PLUGIN_NAME"
echo "2. Configure your Cloudflare API token"
echo "3. Test the plugin with: php $PLUGIN_PATH/cloudflare_sync.php sync yourdomain.com --dry-run"
echo ""
echo "Documentation: See README.md for detailed usage instructions"