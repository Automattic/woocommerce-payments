#!/usr/bin/env bash
#
# release-status.sh — JSON release-readiness aggregator for WooPayments.
#
# Combines bump-version.sh output, local changelog state, and gh CLI queries
# for the release-pr, build-zip, and e2e workflows plus the release PR state.
# Emits one JSON document with a `ready` boolean and a `blockers` array.

set -euo pipefail

JSON=0
BRANCH=""

while [ $# -gt 0 ]; do
	case "$1" in
		--json) JSON=1 ;;
		--branch=*) BRANCH="${1#--branch=}" ;;
		--branch)
			shift
			BRANCH="${1:-}"
			;;
		-h | --help)
			echo "release-status.sh [--branch release/X.Y.Z] [--json]"
			exit 0
			;;
		*)
			echo "unknown arg: $1" >&2
			exit 3
			;;
	esac
	shift || true
done

# Default to JSON when stdout is not a TTY so CI/agents get structured output.
if [ "$JSON" -eq 0 ] && [ ! -t 1 ]; then JSON=1; fi

if [ -z "$BRANCH" ]; then
	BRANCH=$(git for-each-ref --sort=-committerdate --format='%(refname:short)' refs/remotes/origin/release/ 2> /dev/null | head -n1 | sed 's|^origin/||')
	if [ -z "$BRANCH" ]; then
		BRANCH=$(git rev-parse --abbrev-ref HEAD)
	fi
fi

VERSION="${BRANCH#release/}"
# Escape regex metacharacters (just `.` in practice for semver) so that
# e.g. 10.6.0 doesn't accidentally match 10x6x0.
VERSION_RE="${VERSION//./\\.}"

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
VERSION_JSON=$("$SCRIPT_DIR/bump-version.sh" --check --json 2> /dev/null || true)
if [ -z "$VERSION_JSON" ]; then VERSION_JSON='{"consistent":false}'; fi

# Changelog state
ENTRIES_PENDING=0
if [ -d changelog ]; then
	ENTRIES_PENDING=$(find changelog -maxdepth 1 -type f ! -name '.gitkeep' ! -name 'README*' 2> /dev/null | wc -l | tr -d ' ')
fi
CHANGELOG_HAS_RELEASE=false
if grep -qE "^= $VERSION_RE - [0-9]{4}-[0-9]{2}-[0-9]{2} =" changelog.txt 2> /dev/null; then CHANGELOG_HAS_RELEASE=true; fi
README_HAS_RELEASE=false
if grep -qE "^Stable tag: $VERSION_RE\$" readme.txt 2> /dev/null; then README_HAS_RELEASE=true; fi
CHANGELOG_VALID=true
if [ -x ./vendor/bin/changelogger ]; then
	./vendor/bin/changelogger validate > /dev/null 2>&1 || CHANGELOG_VALID=false
fi

gh_run_for() {
	# $1 = workflow file, $2 = branch. Returns one JSON object; null-valued on error.
	local wf="$1" br="$2" raw
	raw=$(gh run list --workflow="$wf" --branch="$br" --limit 1 --json status,conclusion,url 2> /dev/null || echo '[]')
	echo "$raw" | jq 'if (type=="array") then (.[0] // {status:null,conclusion:null,url:null}) else . end'
}

RELEASE_PR_RUN=$(gh_run_for "release-pr.yml" "$BRANCH")
BUILD_ZIP_RUN=$(gh_run_for "build-zip-and-run-smoke-tests.yml" "$BRANCH")
E2E_RUN=$(gh_run_for "e2e-test.yml" "$BRANCH")

PR_RAW=$(gh pr list --head "$BRANCH" --state open --json number,url,state,mergeable --limit 1 2> /dev/null || echo '[]')
PR_JSON=$(echo "$PR_RAW" | jq 'if (type=="array" and length>0) then .[0] else {number:null,url:null,state:null,mergeable:null} end')

# Zip artifact — best-effort v1: mirror build-zip conclusion. A follow-up can
# query the actual artefact listing once we agree on a schema.
ZIP_JSON='{"available":false,"size_bytes":null}'
if [ "$(echo "$BUILD_ZIP_RUN" | jq -r .conclusion)" = "success" ]; then
	ZIP_JSON='{"available":true,"size_bytes":null}'
fi

BLOCKERS=()
[ "$(echo "$VERSION_JSON" | jq -r .consistent)" = "true" ] || BLOCKERS+=("version files inconsistent")
[ "$CHANGELOG_HAS_RELEASE" = "true" ] || BLOCKERS+=("changelog.txt missing entry for $VERSION")
[ "$README_HAS_RELEASE" = "true" ] || BLOCKERS+=("readme.txt stable tag not updated to $VERSION")
[ "$CHANGELOG_VALID" = "true" ] || BLOCKERS+=("changelogger validate failed")
[ "$(echo "$RELEASE_PR_RUN" | jq -r .conclusion)" = "success" ] || BLOCKERS+=("release-pr.yml not success")
[ "$(echo "$BUILD_ZIP_RUN" | jq -r .conclusion)" = "success" ] || BLOCKERS+=("build-zip-and-run-smoke-tests not success")
[ "$(echo "$E2E_RUN" | jq -r .conclusion)" = "success" ] || BLOCKERS+=("e2e-test not success")
[ "$(echo "$PR_JSON" | jq -r '.state')" = "OPEN" ] || BLOCKERS+=("PR not OPEN")
[ "$(echo "$PR_JSON" | jq -r '.mergeable')" = "MERGEABLE" ] || BLOCKERS+=("PR not MERGEABLE")
[ "$(echo "$ZIP_JSON" | jq -r '.available')" = "true" ] || BLOCKERS+=("zip artifact missing")

READY=true
[ ${#BLOCKERS[@]} -eq 0 ] || READY=false

if [ ${#BLOCKERS[@]} -eq 0 ]; then
	BLOCKERS_JSON='[]'
else
	BLOCKERS_JSON=$(printf '%s\n' "${BLOCKERS[@]}" | jq -Rn '[inputs]')
fi

CHANGELOG_JSON=$(jq -n \
	--argjson entries_pending "$ENTRIES_PENDING" \
	--argjson ctx "$CHANGELOG_HAS_RELEASE" \
	--argjson rtx "$README_HAS_RELEASE" \
	--argjson valid "$CHANGELOG_VALID" \
	'{entries_pending:$entries_pending, changelog_txt_has_release:$ctx, readme_txt_has_release:$rtx, valid:$valid}')

OUT=$(jq -n \
	--arg branch "$BRANCH" \
	--arg base "trunk" \
	--argjson version "$VERSION_JSON" \
	--argjson changelog "$CHANGELOG_JSON" \
	--argjson rp "$RELEASE_PR_RUN" \
	--argjson bz "$BUILD_ZIP_RUN" \
	--argjson e2e "$E2E_RUN" \
	--argjson pr "$PR_JSON" \
	--argjson zip "$ZIP_JSON" \
	--argjson ready "$READY" \
	--argjson blockers "$BLOCKERS_JSON" \
	'{branch:$branch, base:$base, version:$version, changelog:$changelog,
	  workflows:{"release-pr":$rp,"build-zip-and-run-smoke-tests":$bz,"e2e-test":$e2e},
	  pr:$pr, zip_artifact:$zip, ready:$ready, blockers:$blockers}')

if [ "$JSON" -eq 1 ]; then
	echo "$OUT"
else
	echo "$OUT" | jq -r '
		"branch: \(.branch)\nready: \(.ready)\n\nworkflows:\n" +
		(.workflows | to_entries | map("  \(.key): \(.value.conclusion // "pending")") | join("\n")) +
		"\n\npr: \(.pr.url // "none") state=\(.pr.state // "?") mergeable=\(.pr.mergeable // "?")\n\n" +
		"blockers:\n" + (.blockers | map("  - \(.)") | join("\n"))'
fi

[ "$READY" = "true" ]
