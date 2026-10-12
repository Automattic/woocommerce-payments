#!/usr/bin/env bash
#
# sync-develop-from-release.sh — bring a release branch's version bump and
# changelog onto the current (develop-based) checkout.
#
# Since trunk is no longer merged back into develop, this is the only way the
# release's version and changelog reach develop. It rebuilds the changes from
# the release branch on every run instead of cherry-picking a commit, so it
# stays correct after cherry-picks, release date changes, and develop moving on.
#
#   - Sets the version in woocommerce-payments.php, package.json and readme.txt.
#   - Replaces (or inserts) the release's block in changelog.txt and readme.txt
#     with the one from the release branch. Only that block is copied, so the
#     rest of develop's files stay as they are.
#   - Deletes the changelog entry files the release branch used up, so they
#     don't show up again in the next release.
#
# Usage:
#   sync-develop-from-release.sh <version> [release-ref]
#
# release-ref defaults to origin/release/<version>. Run it from the repository
# root, with the develop-based branch checked out. It only changes files; the
# caller commits them.

set -euo pipefail

VERSION="${1:-}"
RELEASE_REF="${2:-origin/release/$VERSION}"

if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	echo "usage: $0 <x.y.z> [release-ref]" >&2
	exit 3
fi

if ! git rev-parse --verify --quiet "$RELEASE_REF^{commit}" > /dev/null; then
	echo "error: unknown release ref: $RELEASE_REF" >&2
	exit 3
fi

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

# Prints the `= <version> - <date> =` block that follows the first line
# matching <anchor>, up to (not including) the next version header. readme.txt
# needs the anchor because its FAQ also uses `= ... =` headings.
extract_block() {
	awk -v v="$VERSION" -v anchor="$1" '
		!in_section && $0 ~ anchor { in_section = 1; next }
		in_section && !found && index($0, "= " v " - ") == 1 { found = 1; print; next }
		found && /^= [0-9]/ { found = 0; stop = 1 }
		found && !stop { print }
	'
}

# Replaces the <version> block after <anchor> in <file> with <block-file>, or
# inserts it above the newest version when develop doesn't have it yet.
replace_block() {
	local file="$1" anchor="$2" block_file="$3"
	awk -v v="$VERSION" -v anchor="$anchor" -v block_file="$block_file" '
		function emit(  line) {
			while ((getline line < block_file) > 0) print line
			done = 1
		}
		!in_section && $0 ~ anchor { in_section = 1; print; next }
		skipping && /^= [0-9]/ { skipping = 0 }
		skipping { next }
		in_section && !done && /^= [0-9]/ {
			emit()
			if (index($0, "= " v " - ") == 1) { skipping = 1; next }
		}
		{ print }
		END { if (!done) emit() }
	' "$file" > "$TMP/out"
	mv "$TMP/out" "$file"
}

sync_changelog_block() {
	local file="$1" anchor="$2"
	git show "$RELEASE_REF:$file" | extract_block "$anchor" > "$TMP/block"
	if [ ! -s "$TMP/block" ]; then
		echo "error: no $VERSION block in $RELEASE_REF:$file" >&2
		exit 1
	fi
	replace_block "$file" "$anchor" "$TMP/block"
}

"$SCRIPT_DIR/bump-version.sh" "$VERSION"

sync_changelog_block changelog.txt '^\*\*\* '
sync_changelog_block readme.txt '^== Changelog ==$'

git log "HEAD..$RELEASE_REF" --diff-filter=D --name-only --format= -- changelog/ \
	| sort -u \
	| awk '!/\/\.gitkeep$/' \
	| while read -r entry; do
		rm -f "$entry"
	done
