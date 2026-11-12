#!/usr/bin/env bash

# Prints the PHPUnit database name for a worktree ID.
#
# MySQL rejects database names over 64 characters, and worktree IDs come from directory names, which
# can be much longer. Short names stay as they are so existing test databases keep being reused. Long
# ones keep a readable prefix and end in a hash of the full ID, so each worktree gets a stable, unique
# name.
#
# Usage: bin/test-db-name.sh <worktree-id>

set -e

WORKTREE_ID="${1-default}"
MAX_LENGTH=64
NAME="wcpay_tests_${WORKTREE_ID}"

if [ ${#NAME} -le $MAX_LENGTH ]; then
	echo "$NAME"
	exit 0
fi

# cksum ships with both macOS and Linux, unlike sha1sum/shasum.
HASH=$(printf '%08x' "$(printf '%s' "$WORKTREE_ID" | cksum | cut -d' ' -f1)")
PREFIX="wcpay_tests_"
PREFIX_LENGTH=$((MAX_LENGTH - ${#PREFIX} - 1 - ${#HASH}))
echo "${PREFIX}${WORKTREE_ID:0:$PREFIX_LENGTH}_${HASH}"
