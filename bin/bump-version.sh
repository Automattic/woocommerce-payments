#!/usr/bin/env bash
#
# bump-version.sh — single source of truth for WooPayments release version bumps.
#
# Writes (or verifies) the release version across:
#   - woocommerce-payments.php         ( * Version: X.Y.Z header )
#   - package.json                     ( .version )
#   - package-lock.json                ( .version, .packages[""].version )
#   - readme.txt                       ( Stable tag: X.Y.Z )
#
# Replaces the inline bump block in .github/workflows/release-pr.yml.

set -euo pipefail

usage() {
	cat <<'EOF'
Usage:
  bump-version.sh <version>              Write version across all files
  bump-version.sh <version> --dry-run    Print what would change; write nothing
  bump-version.sh --check                Verify consistency across files
  bump-version.sh --check --json         Emit JSON consistency report
  bump-version.sh <version> --json       Write and emit a JSON summary

Exit codes:
  0  success (write or check consistent)
  1  write/IO error
  2  check found inconsistency
  3  invalid input (bad version, missing args)
EOF
}

VERSION=""
MODE="write"
JSON=0

for arg in "$@"; do
	case "$arg" in
		--check) MODE="check" ;;
		--dry-run) MODE="dry-run" ;;
		--json) JSON=1 ;;
		-h | --help)
			usage
			exit 0
			;;
		-*)
			echo "unknown flag: $arg" >&2
			usage >&2
			exit 3
			;;
		*) VERSION="$arg" ;;
	esac
done

is_semver() { [[ "$1" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[A-Za-z0-9.-]+)?$ ]]; }

if [ "$MODE" != "check" ]; then
	if [ -z "$VERSION" ]; then
		echo "error: version argument required" >&2
		usage >&2
		exit 3
	fi
	if ! is_semver "$VERSION"; then
		echo "error: invalid semver: $VERSION" >&2
		exit 3
	fi
fi

cross_sed() {
	# Cross-platform sed -i (macOS BSD + GNU) — write to .bak then remove.
	local expr="$1"
	local file="$2"
	sed -i.bak "$expr" "$file"
	rm -f "$file.bak"
}

read_php_header() {
	grep -E '^\s*\*\s*Version:' woocommerce-payments.php \
		| head -n1 \
		| sed -E 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//'
}

read_package_json() {
	jq -r .version package.json
}

read_readme_stable_tag() {
	grep -E '^Stable tag:' readme.txt | head -n1 | sed -E 's/^Stable tag:[[:space:]]*//'
}

write_all() {
	local v="$1"

	cross_sed "s/^ \\* Version: .*$/ * Version: $v/" woocommerce-payments.php

	if command -v npm > /dev/null 2>&1 && [ -f package.json ]; then
		# --allow-same-version so reruns with the same version don't fail.
		npm version "$v" --no-git-tag-version --allow-same-version > /dev/null
	else
		local tmp
		tmp="$(mktemp)"
		jq --arg v "$v" '.version = $v' package.json > "$tmp" && mv "$tmp" package.json
		if [ -f package-lock.json ]; then
			tmp="$(mktemp)"
			jq --arg v "$v" '.version = $v | (.packages[""].version = $v)' \
				package-lock.json > "$tmp" && mv "$tmp" package-lock.json
		fi
	fi

	cross_sed "s/^Stable tag: .*$/Stable tag: $v/" readme.txt
}

emit_json_check() {
	jq -n \
		--arg php "$1" --arg pkg "$2" --arg readme "$3" \
		--argjson consistent "$4" \
		'{php_header:$php, package_json:$pkg, readme_txt:$readme, consistent:$consistent}'
}

case "$MODE" in
	write)
		write_all "$VERSION"
		if [ "$JSON" -eq 1 ]; then
			emit_json_check \
				"$(read_php_header)" \
				"$(read_package_json)" \
				"$(read_readme_stable_tag)" \
				true
		fi
		;;
	dry-run)
		echo "would set version to: $VERSION" >&2
		printf 'php_header:   %s -> %s\n' "$(read_php_header)" "$VERSION"
		printf 'package_json: %s -> %s\n' "$(read_package_json)" "$VERSION"
		printf 'readme_txt:   %s -> %s\n' "$(read_readme_stable_tag)" "$VERSION"
		;;
	check)
		php=$(read_php_header)
		pkg=$(read_package_json)
		readme=$(read_readme_stable_tag)
		if [ "$php" = "$pkg" ] && [ "$pkg" = "$readme" ]; then
			if [ "$JSON" -eq 1 ]; then
				emit_json_check "$php" "$pkg" "$readme" true
			else
				echo "consistent: $php"
			fi
			exit 0
		else
			if [ "$JSON" -eq 1 ]; then
				emit_json_check "$php" "$pkg" "$readme" false
			else
				echo "inconsistent: php=$php package=$pkg readme=$readme" >&2
			fi
			exit 2
		fi
		;;
esac
