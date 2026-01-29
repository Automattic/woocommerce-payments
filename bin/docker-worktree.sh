#!/bin/bash
# bin/docker-worktree.sh
# Single entry point for worktree lifecycle management
# Usage:
#   npm run worktree:create <name> [base-branch] [--json]
#   npm run worktree:status [--json]
#   npm run worktree:remove <name> [--force]

set -e

# Colors for output (disabled if not a terminal or --json mode)
setup_colors() {
    if [[ -t 1 ]] && [[ "$JSON_OUTPUT" != "true" ]]; then
        RED='\033[0;31m'
        GREEN='\033[0;32m'
        YELLOW='\033[1;33m'
        BLUE='\033[0;34m'
        BOLD='\033[1m'
        NC='\033[0m' # No Color
    else
        RED=''
        GREEN=''
        YELLOW=''
        BLUE=''
        BOLD=''
        NC=''
    fi
}

# Script directory and repo root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
WORKTREES_PARENT="$(dirname "$REPO_ROOT")"

# Configuration
PORT_RANGE_START=8084
PORT_RANGE_END=8099
HEALTH_CHECK_RETRIES=3
HEALTH_CHECK_DELAY=10

# Parse global flags from all arguments
JSON_OUTPUT="false"
FORCE_MODE="false"
POSITIONAL_ARGS=()

for arg in "$@"; do
    case $arg in
        --json)
            JSON_OUTPUT="true"
            ;;
        --force)
            FORCE_MODE="true"
            ;;
        *)
            POSITIONAL_ARGS+=("$arg")
            ;;
    esac
done

# Reset positional parameters to non-flag arguments
set -- "${POSITIONAL_ARGS[@]}"

setup_colors

# Logging functions
log_info() {
    if [[ "$JSON_OUTPUT" == "true" ]]; then
        return
    fi
    echo -e "${BLUE}$1${NC}"
}

log_success() {
    if [[ "$JSON_OUTPUT" == "true" ]]; then
        return
    fi
    echo -e "${GREEN}$1${NC}"
}

log_warn() {
    if [[ "$JSON_OUTPUT" == "true" ]]; then
        return
    fi
    echo -e "${YELLOW}WARNING: $1${NC}"
}

log_error() {
    if [[ "$JSON_OUTPUT" == "true" ]]; then
        echo "{\"error\": \"$1\"}" >&2
    else
        echo -e "${RED}ERROR: $1${NC}" >&2
    fi
}

log_step() {
    local step_num=$1
    local total=$2
    local message=$3
    if [[ "$JSON_OUTPUT" == "true" ]]; then
        return
    fi
    echo -e "  [${step_num}/${total}] ${message}"
}

# Check if infrastructure is running
check_infra() {
    if ! docker ps --format '{{.Names}}' | grep -q "wcpay_db"; then
        return 1
    fi
    return 0
}

# Start infrastructure if not running
ensure_infra() {
    if ! check_infra; then
        log_info "Starting shared infrastructure..."
        (cd "$REPO_ROOT" && npm run infra:up)
    fi
}

# Find an available port
find_available_port() {
    for port in $(seq $PORT_RANGE_START $PORT_RANGE_END); do
        if ! lsof -i ":$port" > /dev/null 2>&1; then
            echo $port
            return 0
        fi
    done
    return 1
}

# Get list of occupied ports
get_occupied_ports() {
    local ports=""
    for port in $(seq $PORT_RANGE_START $PORT_RANGE_END); do
        if lsof -i ":$port" > /dev/null 2>&1; then
            ports="$ports $port"
        fi
    done
    echo $ports
}

# Generate worktree ID from name
generate_worktree_id() {
    echo "$1" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9]/_/g'
}

# Check if worktree exists
worktree_exists() {
    local name=$1
    local path="$WORKTREES_PARENT/$name"
    git -C "$REPO_ROOT" worktree list --porcelain | grep -q "worktree $path"
}

# Create worktree
cmd_create() {
    local name=$1
    local base_branch=${2:-develop}

    if [[ -z "$name" ]]; then
        log_error "Usage: npm run worktree:create <name> [base-branch]"
        exit 1
    fi

    local worktree_path="$WORKTREES_PARENT/$name"
    local worktree_id=$(generate_worktree_id "$name")

    # Validate name doesn't already exist
    if worktree_exists "$name"; then
        log_error "Worktree '$name' already exists at $worktree_path"
        log_error "To remove it first: npm run worktree:remove $name"
        exit 1
    fi

    if [[ -d "$worktree_path" ]]; then
        log_error "Directory already exists at $worktree_path"
        exit 1
    fi

    log_info "Creating worktree '${BOLD}$name${NC}${BLUE}' from '${BOLD}$base_branch${NC}${BLUE}'..."

    local total_steps=7

    # Step 1: Ensure infrastructure is running
    log_step 1 $total_steps "Checking infrastructure... "
    ensure_infra
    echo "done"

    # Step 2: Create git worktree with a new branch
    log_step 2 $total_steps "Creating git worktree... "
    if ! git -C "$REPO_ROOT" worktree add -b "$name" "$worktree_path" "$base_branch" 2>/dev/null; then
        log_error "Failed to create git worktree."
        log_error "Ensure '$base_branch' exists and branch '$name' doesn't already exist."
        exit 1
    fi
    echo "done"

    # Verify the branch has the new shared infrastructure Docker architecture
    if ! grep -q "wcpay-network" "$worktree_path/docker-compose.yml" 2>/dev/null || \
       ! grep -q "external: true" "$worktree_path/docker-compose.yml" 2>/dev/null; then
        log_error "Branch '$base_branch' has the old Docker architecture (standalone db/phpMyAdmin)."
        log_error "This command requires the new shared infrastructure setup."
        log_error ""
        log_error "Options:"
        log_error "  1. Use a branch that has the new Docker architecture"
        log_error "  2. Merge the Docker refactor into '$base_branch' first"
        log_error "  3. Set up this worktree manually with the old architecture"
        git -C "$REPO_ROOT" worktree remove "$worktree_path" --force 2>/dev/null
        git -C "$REPO_ROOT" branch -D "$name" 2>/dev/null
        exit 1
    fi

    # Step 3: Install npm dependencies
    log_step 3 $total_steps "Installing npm dependencies... "
    (cd "$worktree_path" && npm ci) || {
        log_error "Failed to install npm dependencies"
        git -C "$REPO_ROOT" worktree remove "$worktree_path" --force 2>/dev/null
        git -C "$REPO_ROOT" branch -D "$name" 2>/dev/null
        exit 1
    }
    echo "done"

    # Step 4: Install composer dependencies
    log_step 4 $total_steps "Installing composer dependencies... "
    (cd "$worktree_path" && composer install --ignore-platform-req=php) || {
        log_error "Failed to install composer dependencies"
        git -C "$REPO_ROOT" worktree remove "$worktree_path" --force 2>/dev/null
        git -C "$REPO_ROOT" branch -D "$name" 2>/dev/null
        exit 1
    }
    echo "done"

    # Step 5: Configure port
    log_step 5 $total_steps "Configuring port... "
    local port=$(find_available_port)
    if [[ -z "$port" ]]; then
        local occupied=$(get_occupied_ports)
        log_error "No available ports in range $PORT_RANGE_START-$PORT_RANGE_END"
        log_error "Occupied ports:$occupied"
        git -C "$REPO_ROOT" worktree remove "$worktree_path" --force 2>/dev/null
        git -C "$REPO_ROOT" branch -D "$name" 2>/dev/null
        exit 1
    fi

    # Create .env file
    cat > "$worktree_path/.env" << EOF
WORKTREE_ID=$worktree_id
WORDPRESS_PORT=$port
EOF
    echo "done (port $port)"

    # Step 6: Start Docker container
    log_step 6 $total_steps "Starting Docker container... "
    (cd "$worktree_path" && docker compose up --build -d --quiet-pull) || {
        log_error "Failed to start Docker container"
        exit 1
    }
    echo "done"

    # Step 7: Run WordPress setup
    log_step 7 $total_steps "Setting up WordPress... "
    (cd "$worktree_path" && bash bin/docker-setup.sh) || {
        log_error "WordPress setup failed"
        exit 1
    }

    # Read back the generated .worktree-info.json if it exists
    local info_file="$worktree_path/.worktree-info.json"

    if [[ "$JSON_OUTPUT" == "true" ]]; then
        if [[ -f "$info_file" ]]; then
            cat "$info_file"
        else
            # Generate minimal JSON if setup script didn't create info file
            local created_at=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
            cat << EOF
{
  "version": 1,
  "worktree_id": "$worktree_id",
  "port": $port,
  "url": "http://localhost:$port",
  "admin_url": "http://localhost:$port/wp-admin/",
  "container_name": "wcpay_wp_$worktree_id",
  "created_at": "$created_at",
  "base_branch": "$base_branch",
  "path": "$worktree_path"
}
EOF
        fi
    else
        echo ""
        echo -e "${GREEN}${BOLD}SUCCESS!${NC} Worktree is ready."
        echo ""
        echo -e "  ${BOLD}URL:${NC}       http://localhost:$port"
        echo -e "  ${BOLD}Admin:${NC}     http://localhost:$port/wp-admin/"
        echo -e "  ${BOLD}Login:${NC}     admin / admin"
        echo -e "  ${BOLD}Path:${NC}      $worktree_path"
        echo ""
    fi
}

# Status command
cmd_status() {
    local worktrees=()
    local orphan_containers=()

    # Get all git worktrees
    while IFS= read -r line; do
        if [[ $line =~ ^worktree\ (.+)$ ]]; then
            worktrees+=("${BASH_REMATCH[1]}")
        fi
    done < <(git -C "$REPO_ROOT" worktree list --porcelain)

    # Get all wcpay WordPress containers
    local containers
    containers=$(docker ps -a --filter "name=wcpay_wp_" --format '{{.Names}}:{{.Status}}' 2>/dev/null || true)

    # Build status data
    local json_worktrees="["
    local first=true

    if [[ "$JSON_OUTPUT" != "true" ]]; then
        echo ""
        echo -e "${BOLD}Worktree Status${NC}"
        echo "==============="
        printf "  ${BOLD}%-20s %-6s %-30s %s${NC}\n" "NAME" "PORT" "URL" "STATUS"
    fi

    for wt_path in "${worktrees[@]}"; do
        local wt_name=$(basename "$wt_path")
        local port=""
        local status="unknown"
        local url=""
        local container_name=""
        local worktree_id=""

        # Check for .env file
        if [[ -f "$wt_path/.env" ]]; then
            source "$wt_path/.env"
            port=${WORDPRESS_PORT:-""}
            worktree_id=${WORKTREE_ID:-$(generate_worktree_id "$wt_name")}
        else
            worktree_id=$(generate_worktree_id "$wt_name")
        fi

        container_name="wcpay_wp_$worktree_id"

        # Check container status
        if docker ps --format '{{.Names}}' 2>/dev/null | grep -q "^${container_name}$"; then
            status="running"
        elif docker ps -a --format '{{.Names}}' 2>/dev/null | grep -q "^${container_name}$"; then
            status="stopped"
        else
            status="no container"
        fi

        if [[ -n "$port" ]]; then
            url="http://localhost:$port"
        fi

        # Check for .worktree-info.json
        local info_file="$wt_path/.worktree-info.json"
        local info_json="{}"
        if [[ -f "$info_file" ]]; then
            info_json=$(cat "$info_file")
        fi

        if [[ "$JSON_OUTPUT" != "true" ]]; then
            local display_name="$wt_name"
            if [[ "$wt_path" == "$REPO_ROOT" ]]; then
                display_name="$wt_name (main)"
            fi
            printf "  %-20s %-6s %-30s %s\n" "$display_name" "${port:-n/a}" "${url:-n/a}" "$status"
        else
            if [[ "$first" != "true" ]]; then
                json_worktrees+=","
            fi
            first=false
            local url_json="null"
            if [[ -n "$url" ]]; then
                url_json="\"$url\""
            fi
            json_worktrees+=$(cat << EOF
{
    "name": "$wt_name",
    "path": "$wt_path",
    "port": ${port:-null},
    "url": $url_json,
    "container_name": "$container_name",
    "status": "$status",
    "is_main": $([ "$wt_path" == "$REPO_ROOT" ] && echo "true" || echo "false")
  }
EOF
)
        fi
    done

    json_worktrees+="]"

    # Find orphan containers (containers without matching worktrees)
    local json_orphans="["
    first=true

    while IFS=: read -r container_full_name container_status; do
        [[ -z "$container_full_name" ]] && continue

        # Extract worktree ID from container name
        local container_wt_id="${container_full_name#wcpay_wp_}"
        local found=false

        for wt_path in "${worktrees[@]}"; do
            local wt_name=$(basename "$wt_path")
            local wt_id

            if [[ -f "$wt_path/.env" ]]; then
                source "$wt_path/.env"
                wt_id=${WORKTREE_ID:-$(generate_worktree_id "$wt_name")}
            else
                wt_id=$(generate_worktree_id "$wt_name")
            fi

            if [[ "$container_wt_id" == "$wt_id" ]]; then
                found=true
                break
            fi
        done

        if [[ "$found" == "false" ]]; then
            orphan_containers+=("$container_full_name")
            if [[ "$first" != "true" ]]; then
                json_orphans+=","
            fi
            first=false
            json_orphans+="\"$container_full_name\""
        fi
    done <<< "$containers"

    json_orphans+="]"

    # Output orphan warnings
    if [[ ${#orphan_containers[@]} -gt 0 ]]; then
        if [[ "$JSON_OUTPUT" != "true" ]]; then
            echo ""
            echo -e "${YELLOW}Warnings:${NC}"
            for orphan in "${orphan_containers[@]}"; do
                echo "  - Orphan container: $orphan (no matching worktree)"
            done
            echo ""
            echo "  To clean up orphan containers, stop them with:"
            echo "    docker stop <container_name> && docker rm <container_name>"
        fi
    fi

    if [[ "$JSON_OUTPUT" == "true" ]]; then
        cat << EOF
{
  "worktrees": $json_worktrees,
  "orphan_containers": $json_orphans
}
EOF
    else
        echo ""
    fi
}

# Remove worktree
cmd_remove() {
    local name=$1

    if [[ -z "$name" ]]; then
        log_error "Usage: npm run worktree:remove <name> [--force]"
        exit 1
    fi

    local worktree_path="$WORKTREES_PARENT/$name"

    # Check if this is the main checkout
    if [[ "$worktree_path" == "$REPO_ROOT" ]]; then
        log_error "Cannot remove the main checkout"
        exit 1
    fi

    # Check if worktree exists
    if ! worktree_exists "$name"; then
        log_error "Worktree '$name' does not exist"
        exit 1
    fi

    log_info "Removing worktree '${BOLD}$name${NC}${BLUE}'..."

    # Load worktree config
    local worktree_id=""
    if [[ -f "$worktree_path/.env" ]]; then
        source "$worktree_path/.env"
        worktree_id=${WORKTREE_ID:-$(generate_worktree_id "$name")}
    else
        worktree_id=$(generate_worktree_id "$name")
    fi

    # Step 1: Stop and remove container
    log_info "Stopping Docker container..."
    local container_name="wcpay_wp_$worktree_id"
    if docker ps -a --format '{{.Names}}' | grep -q "^${container_name}$"; then
        (cd "$worktree_path" && docker compose down 2>/dev/null) || true
    fi

    # Step 2: Drop test database
    if check_infra; then
        local test_db_name="wcpay_tests_${worktree_id}"
        log_info "Checking for test database: ${test_db_name}"
        local db_exists=$(docker exec wcpay_db mysql -uroot -pwordpress -e "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '${test_db_name}';" 2>/dev/null | grep -c "${test_db_name}" || true)
        if [[ "$db_exists" -gt 0 ]]; then
            log_info "Dropping test database: ${test_db_name}"
            docker exec wcpay_db mysql -uroot -pwordpress -e "DROP DATABASE IF EXISTS \`${test_db_name}\`;" 2>/dev/null
        fi
    fi

    # Step 3: Remove .env and .worktree-info.json
    [[ -f "$worktree_path/.env" ]] && rm "$worktree_path/.env"
    [[ -f "$worktree_path/.worktree-info.json" ]] && rm "$worktree_path/.worktree-info.json"

    # Step 4: Remove git worktree
    log_info "Removing git worktree..."
    if [[ "$FORCE_MODE" == "true" ]]; then
        git -C "$REPO_ROOT" worktree remove "$worktree_path" --force
    else
        git -C "$REPO_ROOT" worktree remove "$worktree_path"
    fi

    if [[ "$JSON_OUTPUT" == "true" ]]; then
        echo "{\"success\": true, \"removed\": \"$name\"}"
    else
        log_success "Worktree '$name' removed successfully."
    fi
}

# Show usage
show_usage() {
    cat << EOF
Usage: docker-worktree.sh <command> [options]

Commands:
  create <name> [base-branch]  Create a new worktree with Docker environment
                               Default base-branch: develop

  status                       Show status of all worktrees and containers

  remove <name>                Remove a worktree and its Docker resources

Options:
  --json                       Output machine-readable JSON
  --force                      Force removal (for remove command)

Examples:
  npm run worktree:create feature-abc
  npm run worktree:create feature-abc develop
  npm run worktree:status
  npm run worktree:status -- --json
  npm run worktree:remove feature-abc
  npm run worktree:remove feature-abc -- --force
EOF
}

# Main entry point
main() {
    local command=$1
    shift || true

    case $command in
        create)
            cmd_create "$@"
            ;;
        status)
            cmd_status "$@"
            ;;
        remove)
            cmd_remove "$@"
            ;;
        help|--help|-h)
            show_usage
            ;;
        *)
            log_error "Unknown command: $command"
            show_usage
            exit 1
            ;;
    esac
}

main "$@"
