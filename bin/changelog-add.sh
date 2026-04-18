#!/bin/bash
#
# Non-interactive wrapper for the Jetpack Changelogger add command.
# Makes it easy for automation (including Claude) to add changelog entries.
#
# Usage:
#   ./bin/changelog-add.sh --type=fix --entry="Fixed a bug"
#   ./bin/changelog-add.sh -t add -e "Added new feature" -s minor
#   ./bin/changelog-add.sh patch fix "Fixed a bug"  # positional: significance type entry
#
# Options:
#   -t, --type         Type of change: add, fix, update, dev (required)
#   -s, --significance Significance: patch, minor, major (default: patch)
#   -e, --entry        Changelog entry text (required)
#   -f, --filename     Custom filename (default: auto-generated from branch)
#   -c, --comment      Optional comment
#   -h, --help         Show this help message
#

set -e

# Default values
SIGNIFICANCE="patch"
TYPE=""
ENTRY=""
FILENAME=""
COMMENT=""

show_help() {
    cat << EOF
Usage: $0 [OPTIONS] or $0 <significance> <type> <entry>

Non-interactive changelog entry creation.

Options:
  -t, --type         Type of change: add, fix, update, dev (required)
  -s, --significance Significance: patch, minor, major (default: patch)
  -e, --entry        Changelog entry text (required)
  -f, --filename     Custom filename (default: auto-generated from branch)
  -c, --comment      Optional comment
  -h, --help         Show this help message

Positional Arguments (alternative to options):
  $0 <significance> <type> <entry>

Examples:
  $0 --type=fix --entry="Fixed payment processing bug"
  $0 -t add -e "Added new payment method" -s minor
  $0 patch fix "Fixed a bug"
  $0 minor add "Added new feature"

Valid types: add, fix, update, dev
Valid significances: patch, minor, major
EOF
    exit 0
}

# Check if positional arguments are provided (significance type entry)
if [[ $# -ge 3 && ! "$1" =~ ^- ]]; then
    SIGNIFICANCE="$1"
    TYPE="$2"
    ENTRY="$3"
else
    # Parse named arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            -t|--type)
                TYPE="$2"
                shift 2
                ;;
            --type=*)
                TYPE="${1#*=}"
                shift
                ;;
            -s|--significance)
                SIGNIFICANCE="$2"
                shift 2
                ;;
            --significance=*)
                SIGNIFICANCE="${1#*=}"
                shift
                ;;
            -e|--entry)
                ENTRY="$2"
                shift 2
                ;;
            --entry=*)
                ENTRY="${1#*=}"
                shift
                ;;
            -f|--filename)
                FILENAME="$2"
                shift 2
                ;;
            --filename=*)
                FILENAME="${1#*=}"
                shift
                ;;
            -c|--comment)
                COMMENT="$2"
                shift 2
                ;;
            --comment=*)
                COMMENT="${1#*=}"
                shift
                ;;
            -h|--help)
                show_help
                ;;
            *)
                echo "Error: Unknown option: $1" >&2
                echo "Use --help for usage information" >&2
                exit 1
                ;;
        esac
    done
fi

# Validate required arguments
if [[ -z "$TYPE" ]]; then
    echo "Error: --type is required (add, fix, update, dev)" >&2
    exit 1
fi

if [[ -z "$ENTRY" ]]; then
    echo "Error: --entry is required" >&2
    exit 1
fi

# Validate type
if [[ ! "$TYPE" =~ ^(add|fix|update|dev)$ ]]; then
    echo "Error: Invalid type '$TYPE'. Must be one of: add, fix, update, dev" >&2
    exit 1
fi

# Validate significance
if [[ ! "$SIGNIFICANCE" =~ ^(patch|minor|major)$ ]]; then
    echo "Error: Invalid significance '$SIGNIFICANCE'. Must be one of: patch, minor, major" >&2
    exit 1
fi

# Build the command arguments array for proper escaping
ARGS=(
    "./vendor/bin/changelogger" "add"
    "--no-interaction"
    "--filename-auto-suffix"
    "--significance=$SIGNIFICANCE"
    "--type=$TYPE"
    "--entry=$ENTRY"
)

if [[ -n "$FILENAME" ]]; then
    ARGS+=("--filename=$FILENAME")
fi

if [[ -n "$COMMENT" ]]; then
    ARGS+=("--comment=$COMMENT")
fi

# Run the command
"${ARGS[@]}"
