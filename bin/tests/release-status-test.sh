#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
REPO_ROOT="$( cd "$SCRIPT_DIR/../.." && pwd )"

PASS=0
FAIL=0

assert_eq() {
	if [ "$1" = "$2" ]; then
		echo "ok - $3"
		PASS=$((PASS + 1))
	else
		echo "not ok - $3 (got: $1, want: $2)"
		FAIL=$((FAIL + 1))
	fi
}

make_fixture() {
	local dir
	dir="$(mktemp -d "${TMPDIR:-/tmp}/release-status-test.XXXXXX")"
	(
		cd "$dir"
		git init -q -b main
		git config user.email t@t.t
		git config user.name t
		mkdir -p changelog bin
		touch changelog/.gitkeep
		cat > woocommerce-payments.php <<'PHP'
<?php
/**
 * Plugin Name: WooPayments
 * Version: 10.7.0
 */
PHP
		echo '{"name":"f","version":"10.7.0"}' > package.json
		echo '{"name":"f","version":"10.7.0","lockfileVersion":3,"packages":{"":{"name":"f","version":"10.7.0"}}}' > package-lock.json
		echo 'Stable tag: 10.7.0' > readme.txt
		cat > changelog.txt <<'TXT'
= 10.7.0 - 2026-04-15 =
* Fix - something
TXT
		cp "$REPO_ROOT/bin/bump-version.sh" bin/bump-version.sh
		cp "$REPO_ROOT/bin/release-status.sh" bin/release-status.sh
		chmod +x bin/*.sh
		git add .
		git commit -q -m init
		git checkout -q -b release/10.7.0
	)
	echo "$dir"
}

write_gh_shim() {
	# Writes a gh shim that dispatches by subcommand and (for run list) workflow arg.
	local shimdir="$1"
	mkdir -p "$shimdir"
	cat > "$shimdir/gh" <<'SH'
#!/usr/bin/env bash
case "$1 $2" in
	"run list")
		WF=""
		for a in "$@"; do
			case "$a" in --workflow=*) WF="${a#--workflow=}" ;; esac
		done
		case "$WF" in
			*e2e*) cat "${GH_E2E_FIXTURE:-$GH_RUN_LIST_FIXTURE}" ;;
			*build-zip*) cat "${GH_BUILD_FIXTURE:-$GH_RUN_LIST_FIXTURE}" ;;
			*) cat "${GH_RUN_LIST_FIXTURE:-/dev/null}" ;;
		esac
		;;
	"pr list") cat "${GH_PR_LIST_FIXTURE:-/dev/null}" ;;
	*) echo "[]" ;;
esac
SH
	chmod +x "$shimdir/gh"
}

# Happy path: all workflows success, PR open and mergeable.
DIR=$(make_fixture)
write_gh_shim "$DIR/.shim"
cat > "$DIR/.run-list.json" <<'JSON'
[{"status":"completed","conclusion":"success","url":"https://example/run/1"}]
JSON
cat > "$DIR/.pr-list.json" <<'JSON'
[{"number":10998,"url":"https://example/pr/10998","state":"OPEN","mergeable":"MERGEABLE"}]
JSON

export GH_RUN_LIST_FIXTURE="$DIR/.run-list.json"
export GH_PR_LIST_FIXTURE="$DIR/.pr-list.json"
export PATH="$DIR/.shim:$PATH"

OUT=$( cd "$DIR" && ./bin/release-status.sh --json --branch release/10.7.0 ) || true
assert_eq "$(echo "$OUT" | jq -r .branch)" "release/10.7.0" "branch reported"
assert_eq "$(echo "$OUT" | jq -r .version.consistent)" "true" "version consistent"
assert_eq "$(echo "$OUT" | jq -r .pr.number)" "10998" "PR number reported"
assert_eq "$(echo "$OUT" | jq -r .ready)" "true" "ready=true all green"
rm -rf "$DIR"
unset GH_RUN_LIST_FIXTURE GH_PR_LIST_FIXTURE GH_E2E_FIXTURE GH_BUILD_FIXTURE

# Failure: e2e run is conclusion=failure.
DIR=$(make_fixture)
write_gh_shim "$DIR/.shim"
cat > "$DIR/.run-list.json" <<'JSON'
[{"status":"completed","conclusion":"success","url":"https://example/run/ok"}]
JSON
cat > "$DIR/.e2e.json" <<'JSON'
[{"status":"completed","conclusion":"failure","url":"https://example/e2e/fail"}]
JSON
cat > "$DIR/.pr-list.json" <<'JSON'
[{"number":10998,"url":"https://example/pr/10998","state":"OPEN","mergeable":"MERGEABLE"}]
JSON

export GH_RUN_LIST_FIXTURE="$DIR/.run-list.json"
export GH_E2E_FIXTURE="$DIR/.e2e.json"
export GH_PR_LIST_FIXTURE="$DIR/.pr-list.json"
export PATH="$DIR/.shim:$PATH"

set +e
OUT=$( cd "$DIR" && ./bin/release-status.sh --json --branch release/10.7.0 )
RC=$?
set -e
assert_eq "$(echo "$OUT" | jq -r .ready)" "false" "ready=false when e2e fails"
assert_eq "$(echo "$OUT" | jq -r '.blockers | length > 0')" "true" "blockers populated"
assert_eq "$RC" "1" "non-zero exit when not ready"
rm -rf "$DIR"

# Stale version: branch is release/10.7.0 but all files still at 10.6.0, and
# changelog.txt / readme.txt are missing entries for 10.7.0. bump-version.sh
# --check still reports files as internally consistent, so ready must be gated
# by the changelog/readme checks.
DIR=$(make_fixture)
(
	cd "$DIR"
	# Roll every version file back to 10.6.0 — consistent among themselves, but
	# mismatched with the branch name.
	sed -i.bak 's/Version: 10.7.0/Version: 10.6.0/' woocommerce-payments.php && rm -f woocommerce-payments.php.bak
	echo '{"name":"f","version":"10.6.0"}' > package.json
	echo '{"name":"f","version":"10.6.0","lockfileVersion":3,"packages":{"":{"name":"f","version":"10.6.0"}}}' > package-lock.json
	echo 'Stable tag: 10.6.0' > readme.txt
	: > changelog.txt
)
write_gh_shim "$DIR/.shim"
cat > "$DIR/.run-list.json" <<'JSON'
[{"status":"completed","conclusion":"success","url":"https://example/run/ok"}]
JSON
cat > "$DIR/.pr-list.json" <<'JSON'
[{"number":10998,"url":"https://example/pr/10998","state":"OPEN","mergeable":"MERGEABLE"}]
JSON

export GH_RUN_LIST_FIXTURE="$DIR/.run-list.json"
export GH_PR_LIST_FIXTURE="$DIR/.pr-list.json"
export PATH="$DIR/.shim:$PATH"

set +e
OUT=$( cd "$DIR" && ./bin/release-status.sh --json --branch release/10.7.0 )
RC=$?
set -e
assert_eq "$(echo "$OUT" | jq -r .ready)" "false" "ready=false when files are stale"
assert_eq "$(echo "$OUT" | jq -r '[.blockers[] | select(contains("changelog.txt"))] | length > 0')" "true" "blockers include changelog.txt entry missing"
assert_eq "$(echo "$OUT" | jq -r '[.blockers[] | select(contains("readme.txt"))]  | length > 0')" "true" "blockers include readme.txt stable tag stale"
assert_eq "$RC" "1" "non-zero exit when stale"
rm -rf "$DIR"
unset GH_RUN_LIST_FIXTURE GH_PR_LIST_FIXTURE GH_E2E_FIXTURE GH_BUILD_FIXTURE

echo "# passed $PASS/$((PASS + FAIL))"
[ "$FAIL" -eq 0 ]
