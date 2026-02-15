# Claude Code Local Instructions - WooPayments

> **Purpose:** AI-specific behavioral instructions for this repository.
> **PRIORITY:** These instructions OVERRIDE default behavior when conflicts arise.

## Warning Filtering Rules

**NEVER report:**
- `DEPRECATED: Passing an array...` (PHPCS config warnings)
- `npm warn Unknown project config` (npm/pnpm notices)
- `WARN Unsupported engine` (fix silently with `nvm use <major>`, then continue)
- Successful command execution noise

**ALWAYS report:**
- Actual code errors/failures (non-warning)
- Test failures with details
- Manual fixes needed after auto-fix attempts
- Commands that fail (exit code indicates failure, not warnings)

## PHP Linting Reporting

| Outcome                    | Report to User                            |
|----------------------------|-------------------------------------------|
| All files pass (exit 0)    | "All files pass linting"                  |
| Manual fixes required      | List specific violations with line numbers |
| PHPCS deprecation warnings | DO NOT mention (filter these out)         |

## CodeRabbit CLI Review Workflow

**CORE RULE:** Use CodeRabbit ONLY when user explicitly requests it.

### Session Initialization Logic

**IF** starting work on code changes (edits, commits, or PRs)
**THEN** ask user: "Would you like me to use CodeRabbit to review the changes in this session?"

**IF** user confirms yes
- **THEN** set session mode to `use_coderabbit = true`
- Use CodeRabbit throughout entire session

**IF** user declines or doesn't respond
- **THEN** set session mode to `use_coderabbit = false`
- Skip CodeRabbit for entire session

### Usage Decision Matrix

| User Action                          | Use CodeRabbit? |
|--------------------------------------|-----------------|
| Explicitly requests CodeRabbit       | YES             |
| Mentions "CodeRabbit" in prompt      | YES             |
| Requests "AI-assisted code review"   | YES             |
| Confirms when asked at session start | YES             |
| Declines when asked                  | NO              |
| No mention and not asked yet         | NO              |
| Regular commit/PR without request    | NO              |

### Authentication (One-time Setup)

**PREREQUISITE:** Authentication in Claude Code is independent from CLI authentication.

**Authentication Sequence:**
1. Run `coderabbit auth login`
2. Receive URL from command output
3. Direct user: "Open the URL in your browser"
4. Direct user: "Log in to CodeRabbit and copy the token"
5. Wait for user to paste token back
6. Authentication persists across all Claude Code sessions

**Verification:**
```bash
coderabbit auth status
```

### Review Execution Workflow

**Standard iteration loop:**

```bash
# Step 1: Initial review (run in background)
coderabbit review --prompt-only --type uncommitted
```

**WHILE** CodeRabbit reports issues:
1. Apply all suggested fixes from review
2. Re-run `coderabbit review --prompt-only --type uncommitted`
3. Report issues found to user
4. **REPEAT** until clean

**WHEN** CodeRabbit reports no issues:
- Report: "Code passes CodeRabbit review"
- Proceed with commit/PR

### Reporting Requirements

**FOR EACH** review iteration:
- Report issues found by CodeRabbit
- List fixes applied
- Confirm when review passes

## WCPay Server (Transact Platform)

**Location:** `/Users/vladolaru/Work/a8c/transact-platform-server`

### Useful Commands (run from server repo)
```bash
npm run update-fees-fixtures   # Update payment method fees (requires SSH to wpcomsandbox)
npm run stripe                 # Stripe CLI
npm run db:seed                # Seed database with fixtures
```

### Testing Amazon Pay
1. Update version header to `10.6.0` in `woocommerce-payments.php` line 14
2. Run `npm run update-fees-fixtures` in transact-platform-server
3. Enable feature flag: `wp option update _wcpay_feature_amazon_pay 1`
4. Enable Amazon Pay in Payments > Settings

## Related Documentation

- Repository root: Various package-specific CLAUDE.md files
