#!/bin/bash
# bin/docker-worktree-status.sh
# Shows status of all worktrees and their Docker environments
# Usage: pnpm run worktree:status

set -e

# Colors for output (disabled if not a terminal)
if [[ -t 1 ]]; then
    YELLOW='\033[1;33m'
    BOLD='\033[1m'
    NC='\033[0m'
else
    YELLOW=''
    BOLD=''
    NC=''
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
CURRENT_DIR="$(pwd)"

# Generate worktree ID from name (same logic as docker-port-setup.sh)
generate_worktree_id() {
    echo "$1" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9]/_/g'
}

# Get a worktree's ID from its .env, falling back to the same defaults as docker-compose.yml for the main checkout
get_worktree_id() {
    local wt_path="$1"
    local worktree_id=""

    if [[ -f "$wt_path/.env" ]]; then
        worktree_id=$(grep '^WORKTREE_ID=' "$wt_path/.env" 2>/dev/null | cut -d= -f2)
    fi

    if [[ -n "$worktree_id" ]]; then
        echo "$worktree_id"
    elif [[ "$wt_path" == "$MAIN_CHECKOUT" ]]; then
        echo "default"
    else
        generate_worktree_id "$(basename "$wt_path")"
    fi
}

# Get all git worktrees
worktrees=()
while IFS= read -r line; do
    if [[ $line =~ ^worktree\ (.+)$ ]]; then
        worktrees+=("${BASH_REMATCH[1]}")
    fi
done < <(git -C "$REPO_ROOT" worktree list --porcelain)

# Git always lists the main checkout first, while REPO_ROOT is whichever checkout this script runs from
MAIN_CHECKOUT="${worktrees[0]}"

# Get all wcpay WordPress containers
containers=$(docker ps -a --filter "name=wcpay_wp_" --format '{{.Names}}' 2>/dev/null || true)

echo ""
echo -e "${BOLD}Worktree Status${NC}"
echo ""
printf "  ${BOLD}%-6s %-30s %-14s %s${NC}\n" "PORT" "URL" "STATUS" "NAME"

orphan_containers=()

for wt_path in "${worktrees[@]}"; do
    wt_name=$(basename "$wt_path")
    port=""
    status="no container"
    url="n/a"
    worktree_id=$(get_worktree_id "$wt_path")

    # Check for .env file
    if [[ -f "$wt_path/.env" ]]; then
        port=$(grep '^WORDPRESS_PORT=' "$wt_path/.env" 2>/dev/null | cut -d= -f2)
    fi

    # Use default port for main checkout if not set in .env
    if [[ -z "$port" && "$worktree_id" == "default" ]]; then
        port="8082"
    fi

    container_name="wcpay_wp_$worktree_id"

    # Check container status
    if docker ps --format '{{.Names}}' 2>/dev/null | grep -q "^${container_name}$"; then
        status="running"
    elif docker ps -a --format '{{.Names}}' 2>/dev/null | grep -q "^${container_name}$"; then
        status="stopped"
    fi

    [[ -n "$port" ]] && url="http://localhost:$port"

    # Build display name with markers
    display_name="$wt_name"
    [[ "$wt_path" == "$MAIN_CHECKOUT" ]] && display_name="$wt_name (main)"
    [[ "$wt_path" == "$CURRENT_DIR" ]] && display_name="* $display_name"

    printf "  %-6s %-30s %-14s %s\n" "${port:-n/a}" "$url" "$status" "$display_name"
done

# Find orphan containers
for container_name in $containers; do
    [[ -z "$container_name" ]] && continue
    container_wt_id="${container_name#wcpay_wp_}"
    found=false

    for wt_path in "${worktrees[@]}"; do
        wt_id=$(get_worktree_id "$wt_path")
        [[ "$container_wt_id" == "$wt_id" ]] && found=true && break
    done

    [[ "$found" == "false" ]] && orphan_containers+=("$container_name")
done

if [[ ${#orphan_containers[@]} -gt 0 ]]; then
    echo ""
    echo -e "${YELLOW}Warnings:${NC}"
    for orphan in "${orphan_containers[@]}"; do
        echo "  - Orphan container: $orphan (no matching worktree)"
    done
    echo ""
    echo "  To clean up: docker stop <name> && docker rm <name>"
fi

echo ""
