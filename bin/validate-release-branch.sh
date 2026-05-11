#!/usr/bin/env bash
#
# validate-release-branch.sh — strict pre-flight validator for a release branch.
#
# Checks are pass/fail; no "soft" mode. Invoked from release-pr.yml (which runs
# only on release branches) and manually by release leads before merging.

set -euo pipefail

JSON=0
for arg in "$@"; do
	case "$arg" in
		--json) JSON=1 ;;
		-h | --help)
			echo "validate-release-branch.sh [--json]"
			exit 0
			;;
		*)
			echo "unknown arg: $arg" >&2
			exit 3
			;;
	esac
done

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

BRANCH=$(git rev-parse --abbrev-ref HEAD)
VERSION=""
if [[ "$BRANCH" =~ ^release/([0-9]+\.[0-9]+\.[0-9]+(-[A-Za-z0-9.-]+)?)$ ]]; then
	VERSION="${BASH_REMATCH[1]}"
fi
# Escape regex metacharacters (just `.` in practice for semver) so that
# e.g. 10.6.0 doesn't accidentally match 10x6x0.
VERSION_RE="${VERSION//./\\.}"

# Parallel indexed arrays (bash 3.2 compatible — no associative arrays).
NAMES=(
	branch_name
	working_tree_clean
	version_consistent
	changelog_valid
	stable_tag_matches
	changelog_has_release_entry
	no_pending_changelog_entries
)
PASSES=()
DETAILS=()

push_check() {
	PASSES+=("$1")
	DETAILS+=("${2:-}")
}

# 1. branch name
if [ -n "$VERSION" ]; then
	push_check true ""
else
	push_check false "branch '$BRANCH' does not match release/X.Y.Z"
fi

# 2. working tree clean
if [ -z "$(git status --porcelain)" ]; then
	push_check true ""
else
	push_check false "uncommitted changes present"
fi

# 3. version consistent across files
if "$SCRIPT_DIR/bump-version.sh" --check > /dev/null 2>&1; then
	push_check true ""
else
	push_check false "bump-version.sh --check failed"
fi

# 4. changelogger validate (optional — skip if vendor bin missing)
if [ -x ./vendor/bin/changelogger ]; then
	if ./vendor/bin/changelogger validate > /dev/null 2>&1; then
		push_check true ""
	else
		push_check false "changelogger validate failed"
	fi
else
	push_check true "skipped: vendor/bin/changelogger not installed"
fi

# 5. stable tag matches branch version
if [ -n "$VERSION" ]; then
	STABLE=$(grep -E '^Stable tag:' readme.txt 2> /dev/null | head -n1 | sed -E 's/^Stable tag:[[:space:]]*//' || true)
	if [ "$STABLE" = "$VERSION" ]; then
		push_check true ""
	else
		push_check false "readme.txt stable tag '$STABLE' != '$VERSION'"
	fi
else
	push_check false "branch version unknown"
fi

# 6. changelog.txt has entry for this version
if [ -n "$VERSION" ] && grep -qE "^= $VERSION_RE - [0-9]{4}-[0-9]{2}-[0-9]{2} =" changelog.txt 2> /dev/null; then
	push_check true ""
else
	push_check false "no '= $VERSION - YYYY-MM-DD =' entry in changelog.txt"
fi

# 7. no pending changelog entries (files should have been consumed by release-pr.yml)
PENDING=0
if [ -d changelog ]; then
	PENDING=$(find changelog -maxdepth 1 -type f ! -name '.gitkeep' ! -name 'README*' 2> /dev/null | wc -l | tr -d ' ')
fi
if [ "$PENDING" -eq 0 ]; then
	push_check true ""
else
	push_check false "$PENDING files remain in changelog/"
fi

OVERALL=true
for p in "${PASSES[@]}"; do
	[ "$p" = "true" ] || OVERALL=false
done

if [ "$JSON" -eq 1 ]; then
	CHECKS_JSON="{}"
	i=0
	for name in "${NAMES[@]}"; do
		pass="${PASSES[$i]}"
		detail="${DETAILS[$i]}"
		if [ -n "$detail" ]; then
			CHECKS_JSON=$(echo "$CHECKS_JSON" | jq \
				--arg k "$name" \
				--argjson pass "$pass" \
				--arg detail "$detail" \
				'.[$k] = {pass:$pass, detail:$detail}')
		else
			CHECKS_JSON=$(echo "$CHECKS_JSON" | jq \
				--arg k "$name" \
				--argjson pass "$pass" \
				'.[$k] = {pass:$pass}')
		fi
		i=$((i + 1))
	done
	jq -n \
		--arg branch "$BRANCH" \
		--argjson pass "$OVERALL" \
		--argjson checks "$CHECKS_JSON" \
		'{branch:$branch, checks:$checks, pass:$pass}'
else
	echo "branch: $BRANCH"
	i=0
	for name in "${NAMES[@]}"; do
		printf '  %-32s %s %s\n' "$name" "${PASSES[$i]}" "${DETAILS[$i]}"
		i=$((i + 1))
	done
	echo "pass: $OVERALL"
fi

[ "$OVERALL" = "true" ]
