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
#   - Copies changelog.txt from the release branch. develop only changes it
#     through these syncs, so the release branch has the full shipped history.
#   - Replaces (or inserts) the release's changelog block in readme.txt. Only
#     that block is copied, because develop edits the rest of the readme.
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

"$SCRIPT_DIR/bump-version.sh" "$VERSION"

git show "$RELEASE_REF:changelog.txt" > changelog.txt

# The FAQ above `== Changelog ==` also uses `= ... =` headings, so only look
# for version blocks below it.
git show "$RELEASE_REF:readme.txt" | awk -v v="$VERSION" '
	/^== Changelog ==$/ { in_section = 1; next }
	in_section && !found && index($0, "= " v " - ") == 1 { found = 1; print; next }
	found && /^= [0-9]/ { found = 0; stop = 1 }
	found && !stop { print }
' > "$TMP/block"
if [ ! -s "$TMP/block" ]; then
	echo "error: no $VERSION block in $RELEASE_REF:readme.txt" >&2
	exit 1
fi
awk -v v="$VERSION" -v block_file="$TMP/block" '
	function emit(  line) {
		while ((getline line < block_file) > 0) print line
		done = 1
	}
	/^== Changelog ==$/ { in_section = 1; print; next }
	skipping && /^= [0-9]/ { skipping = 0 }
	skipping { next }
	in_section && !done && /^= [0-9]/ {
		emit()
		if (index($0, "= " v " - ") == 1) { skipping = 1; next }
	}
	{ print }
	END { if (!done) emit() }
' readme.txt > "$TMP/readme.txt"
mv "$TMP/readme.txt" readme.txt

# Walk the history rather than diffing against the branch point: cherry-picked
# fixes add their entry files after the code freeze, so the branch point never
# had them. Files still on the release tip were put back (e.g. a reverted
# changelog commit) and haven't shipped.
git log "HEAD..$RELEASE_REF" --diff-filter=D --name-only --format= -- changelog/ \
	| sort -u \
	| awk '!/\/\.gitkeep$/' \
	| while read -r entry; do
		if ! git cat-file -e "$RELEASE_REF:$entry" 2> /dev/null; then
			rm -f "$entry"
		fi
	done
