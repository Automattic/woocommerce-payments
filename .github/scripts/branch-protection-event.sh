#!/usr/bin/env bash
# Build the branch-protection change payload from a branch_protection_rule
# event file. Default output: canonical flat JSON (for the internal audit sink).
# With --slack: Slack Block Kit JSON. Using jq guarantees valid, injection-safe
# JSON regardless of rule names / change values. Refs WOOPMNT-6229.
set -euo pipefail

format="canonical"
if [ "${1:-}" = "--slack" ]; then format="slack"; shift; fi

event_file="${1:-${GITHUB_EVENT_PATH:?event file required}}"
ts="$(date -u +'%Y-%m-%dT%H:%M:%SZ')"

canonical="$(
  jq -c \
    --arg ts "$ts" \
    '{
      repository: (.repository.full_name // "unknown"),
      action: (.action // "unknown"),
      rule: (.rule.name // "unknown"),
      actor: (.sender.login // "unknown"),
      timestamp: $ts,
      settings_url: ("https://github.com/" + (.repository.full_name // "") + "/settings/branches"),
      changes: (.changes // {})
    }' "$event_file"
)"

if [ "$format" = "canonical" ]; then
  printf '%s\n' "$canonical"
  exit 0
fi

printf '%s\n' "$canonical" | jq '{
  blocks: [
    { type: "header",
      text: { type: "plain_text", text: ":lock: Branch protection changed", emoji: true } },
    { type: "section",
      fields: [
        { type: "mrkdwn", text: ("*Repository:*\n" + .repository) },
        { type: "mrkdwn", text: ("*Action:*\n" + .action) },
        { type: "mrkdwn", text: ("*Branch rule:*\n" + .rule) },
        { type: "mrkdwn", text: ("*Changed by:*\n" + .actor) },
        { type: "mrkdwn", text: ("*When (UTC):*\n" + .timestamp) }
      ] },
    { type: "section",
      text: { type: "mrkdwn",
        text: ("*Changes:*\n```" + (.changes | tojson) + "```") } },
    { type: "section",
      text: { type: "mrkdwn",
        text: ("<" + .settings_url + "|Review branch protection settings>") } }
  ]
}'
