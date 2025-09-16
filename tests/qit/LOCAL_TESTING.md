# Testing WCPay Configuration in QIT

## Overview

QIT tests can run in two modes:

### 1. Basic Mode (Default)

- Shows WCPay "Connect" screen
- Tests basic plugin functionality
- No external dependencies

### 2. Production Mode (With Tokens)

- Uses real WCPay configuration
- Tests against production API
- Requires valid credentials

## Setting Up Production Mode

1. **Copy the template**:

   ```bash
   cp tests/qit/config/default.env tests/qit/config/local.env
   ```

2. **Add real credentials to `tests/qit/config/local.env`**:

   ```bash
   # Production E2E credentials for testing against live environment
   E2E_JP_SITE_ID=your_actual_site_id
   E2E_JP_BLOG_TOKEN=your_actual_blog_token
   E2E_JP_USER_TOKEN=your_actual_user_token
   ```

3. **Run QIT tests**:

   ```bash
   cd tests/qit
   qit run:e2e woocommerce-payments ./e2e --source ../..
   ```

## How It Works

- **Environment Loading**: QIT automatically loads env vars from `config/local.env`
- **Bootstrap Detection**: The setup script checks for `E2E_JP_SITE_ID`
- **Graceful Fallback**: If no credentials, falls back to Connect screen mode

## Output Examples

### Without Credentials (Basic Mode)

```text
No blog ID available - WCPay will show Connect screen
To test with real WCPay connection, set E2E_JP_SITE_ID in tests/qit/config/local.env
```

### With Credentials (Production Mode)

```text
Setting blog ID for WCPay: 123456789
Setting blog token (available)
Setting user token (available)
WCPay configured with blog ID: 123456789
```

This enables comprehensive testing of both scenarios locally! 🚀
