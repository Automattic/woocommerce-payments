#!/bin/bash
# bin/docker-seed-volumes.sh
# Seeds shared Docker volumes from local directories if volumes are empty
# This handles migration from local-directory setup to shared-volume setup

# Load worktree-specific config if available
if [ -f ".env" ]; then
    source .env
fi

# Determine container name
if [ -n "$WORKTREE_ID" ]; then
    WP_CONTAINER="wcpay_wp_${WORKTREE_ID}"
else
    WP_CONTAINER="wcpay_wp_default"
fi

# Wait for container to be ready (quick check, max 30 seconds)
for i in {1..30}; do
    if docker exec $WP_CONTAINER true 2>/dev/null; then
        break
    fi
    sleep 1
done

if ! docker exec $WP_CONTAINER true 2>/dev/null; then
    echo "Warning: Container not ready, skipping volume seeding"
    exit 0
fi

# Sync local directory to volume - copies any items that exist locally but not in the volume
sync_local_to_volume() {
    local LOCAL_DIR="$1"
    local CONTAINER_PATH="$2"
    local VOLUME_NAME="$3"

    # Skip if local directory doesn't exist
    if [[ ! -d "$LOCAL_DIR" ]]; then
        return
    fi

    # Get list of items in volume
    VOLUME_ITEMS=$(docker exec $WP_CONTAINER ls -A "$CONTAINER_PATH" 2>/dev/null || echo "")

    SYNCED_COUNT=0
    for item in "$LOCAL_DIR"/*; do
        if [[ ! -e "$item" ]]; then
            continue
        fi

        ITEM_NAME=$(basename "$item")

        # Skip index.php
        if [[ "$ITEM_NAME" == "index.php" ]]; then
            continue
        fi

        # Check if item already exists in volume
        if echo "$VOLUME_ITEMS" | grep -q "^${ITEM_NAME}$"; then
            continue
        fi

        # Item exists locally but not in volume - copy it
        if [[ $SYNCED_COUNT -eq 0 ]]; then
            echo "Syncing missing items to $VOLUME_NAME volume..."
        fi
        docker cp "$item" "$WP_CONTAINER:$CONTAINER_PATH/" 2>/dev/null || true
        docker exec $WP_CONTAINER chown -R www-data:www-data "$CONTAINER_PATH/$ITEM_NAME" 2>/dev/null || true
        echo "  + $ITEM_NAME"
        SYNCED_COUNT=$((SYNCED_COUNT + 1))
    done

    if [[ $SYNCED_COUNT -gt 0 ]]; then
        echo "  Synced $SYNCED_COUNT items to $VOLUME_NAME volume"
    fi
}

# Sync local directories to shared volumes - copies any items that exist locally but not in the volume
sync_local_to_volume "docker/wordpress/wp-content/plugins" "/var/www/html/wp-content/plugins" "wcpay-plugins"
sync_local_to_volume "docker/wordpress/wp-content/themes" "/var/www/html/wp-content/themes" "wcpay-themes"
sync_local_to_volume "docker/wordpress/wp-content/uploads" "/var/www/html/wp-content/uploads" "wcpay-uploads"
sync_local_to_volume "docker/mu-plugins" "/var/www/html/wp-content/mu-plugins" "wcpay-mu-plugins"
