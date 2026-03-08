#!/bin/bash
# bin/docker-worktree-status.sh
# Displays all git worktrees alongside their Docker environment status.
# Usage: npm run worktree:status

set -e

# Disable color output when not connected to a terminal
if [[ -t 1 ]]; then
    GREEN='\033[0;32m'
    YELLOW='\033[1;33m'
    RED='\033[0;31m'
    BOLD='\033[1m'
    NC='\033[0m'
else
    GREEN=''
    YELLOW=''
    RED=''
    BOLD=''
    NC=''
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
CURRENT_DIR="$(pwd)"

# Generate the same sanitized ID used by docker-port-setup.sh
generate_worktree_id() {
    echo "$1" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9]/_/g' | sed 's/__*/_/g' | sed 's/^_//;s/_$//'
}

echo ""
echo -e "${BOLD}WooPayments worktree / checkout status${NC}"
echo ""
printf "  %-7s %-30s %-12s %s\n" "Port" "URL" "Docker" "Directory"
printf "  %-7s %-30s %-12s %s\n" "-------" "------------------------------" "------------" "---------"

# Collect any orphan container names to warn about later
orphan_containers=()
containers=$(docker ps -a --format '{{.Names}}' 2>/dev/null | grep '^wcpay_\|^woocommerce_payments_' || true)

worktrees=()
while IFS= read -r line; do
    if [[ $line =~ ^worktree\ (.+)$ ]]; then
        worktrees+=("${BASH_REMATCH[1]}")
    fi
done < <(git worktree list --porcelain 2>/dev/null)

for wt_path in "${worktrees[@]}"; do
    [[ -z "$wt_path" ]] && continue

    wt_name=$(basename "$wt_path")
    port=""
    worktree_id=""
    status="stopped"
    url=""

    if [[ -f "$wt_path/.env" ]]; then
        port=$(grep '^WORDPRESS_PORT=' "$wt_path/.env" 2>/dev/null | cut -d= -f2)
        worktree_id=$(grep '^WORKTREE_ID=' "$wt_path/.env" 2>/dev/null | cut -d= -f2)
        compose_project=$(grep '^COMPOSE_PROJECT_NAME=' "$wt_path/.env" 2>/dev/null | cut -d= -f2)
    fi

    # Fall back to defaults when the main checkout has no .env yet
    if [[ -z "$worktree_id" ]]; then
        if [[ "$wt_path" == "$REPO_ROOT" ]]; then
            worktree_id=$(generate_worktree_id "$wt_name")
            compose_project="wcpay_${worktree_id}"
            [[ -z "$port" ]] && port="8082"
        else
            worktree_id=$(generate_worktree_id "$wt_name")
            compose_project="wcpay_${worktree_id}"
        fi
    fi

    container_name="${compose_project:-wcpay_${worktree_id}}_wordpress"
    # Also check the legacy default name
    [[ "$wt_path" == "$REPO_ROOT" && -z "$(echo "$containers" | grep "^${container_name}$")" ]] \
        && container_name_legacy="woocommerce_payments_wordpress" || container_name_legacy=""

    check_name="$container_name"
    if docker ps --format '{{.Names}}' 2>/dev/null | grep -q "^${check_name}$"; then
        status="${GREEN}running${NC}"
    elif docker ps --format '{{.Names}}' 2>/dev/null | grep -q "^${container_name_legacy}$"; then
        status="${GREEN}running${NC}"
    elif docker ps -a --format '{{.Names}}' 2>/dev/null | grep -q "^${check_name}$"; then
        status="${YELLOW}stopped${NC}"
    elif [[ -n "$container_name_legacy" ]] && docker ps -a --format '{{.Names}}' 2>/dev/null | grep -q "^${container_name_legacy}$"; then
        status="${YELLOW}stopped${NC}"
    else
        status="${RED}none${NC}"
    fi

    [[ -n "$port" ]] && url="http://localhost:$port"

    # Mark the current working directory
    display_name="$wt_path"
    [[ "$wt_path" == "$REPO_ROOT" ]] && display_name="${display_name} (main)"
    [[ "$wt_path" == "$CURRENT_DIR" ]] && display_name="* ${display_name}"

    printf "  %-7s %-30s " "${port:-n/a}" "${url:-n/a}"
    printf "${status}"
    printf "%-$((12 - ${#status} + ${#GREEN} + ${#NC}))s" ""
    printf " %s\n" "$display_name"
done

# Detect orphan containers (containers with no matching worktree)
for container_name in $containers; do
    [[ -z "$container_name" ]] && continue
    container_project="${container_name%_wordpress}"
    found=false
    for wt_path in "${worktrees[@]}"; do
        wt_name=$(basename "$wt_path")
        cp=""
        if [[ -f "$wt_path/.env" ]]; then
            cp=$(grep '^COMPOSE_PROJECT_NAME=' "$wt_path/.env" 2>/dev/null | cut -d= -f2)
        fi
        [[ -z "$cp" ]] && cp="wcpay_$(generate_worktree_id "$wt_name")"
        if [[ "${cp}_wordpress" == "$container_name" ]] \
            || [[ "woocommerce_payments_wordpress" == "$container_name" && "$wt_path" == "$REPO_ROOT" ]]; then
            found=true
            break
        fi
    done
    [[ "$found" == "false" ]] && orphan_containers+=("$container_name")
done

if [[ ${#orphan_containers[@]} -gt 0 ]]; then
    echo ""
    echo -e "${YELLOW}Warning: orphan containers (no matching worktree found):${NC}"
    for orphan in "${orphan_containers[@]}"; do
        echo "  - $orphan"
    done
    echo ""
    echo "  To clean up: docker stop <name> && docker rm <name>"
fi

echo ""
