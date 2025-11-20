# Code Changes Reference

This document outlines the specific code changes needed to migrate tests from the legacy custom tests format to test packages.

---

## 1. Bootstrap Path Changes

**Why**: Legacy tests used Docker volume mounts at `/qit/bootstrap/`. Test packages copy files, so paths must be relative.

### Shell Scripts (`bootstrap/setup.sh`)

**Before**:
```bash
wp eval-file /qit/bootstrap/qit-jetpack-connection.php
wp eval-file /qit/bootstrap/qit-jetpack-status.php
```

**After**:
```bash
wp eval-file ./bootstrap/qit-jetpack-connection.php
wp eval-file ./bootstrap/qit-jetpack-status.php
```

### PHP Files (`bootstrap/*.php`)

**Before**:
```php
$command_file = '/qit/bootstrap/class-wp-cli-qit-dev-command.php';
```

**After**:
```php
$command_file = './bootstrap/class-wp-cli-qit-dev-command.php';
```

**How to find and fix**:
```bash
# Find all occurrences
grep -r "/qit/bootstrap" ./bootstrap/

# Fix on macOS
find ./bootstrap -type f -exec sed -i '' 's|/qit/bootstrap|./bootstrap|g' {} +

# Fix on Linux
find ./bootstrap -type f -exec sed -i 's|/qit/bootstrap|./bootstrap|g' {} +
```

---

## 2. QIT Helpers Import Changes

**Why**: Legacy tests used global `/qitHelpers` module. Test packages use local `@qit/helpers` package.

### TypeScript/JavaScript Files

**Before**:
```typescript
import qit from '/qitHelpers';
```

**After**:
```typescript
import qit from '@qit/helpers';
```

**How to find and fix**:
```bash
# Find all occurrences
grep -r "from '/qitHelpers'" . --include="*.ts" --include="*.js"

# Fix on macOS
find . -name "*.ts" -o -name "*.js" | xargs sed -i '' "s|from '/qitHelpers'|from '@qit/helpers'|g"

# Fix on Linux
find . -name "*.ts" -o -name "*.js" | xargs sed -i "s|from '/qitHelpers'|from '@qit/helpers'|g"
```

---

## 3. JSON Import Changes

**Why**: ES modules require import attributes for JSON files.

### Config Files (`config/default.ts`)

**Before**:
```typescript
import { users } from './users.json';

export const config = {
    users: users,
    // ...
};
```

**After**:
```typescript
import usersData from './users.json' with { type: 'json' };

const users = usersData.users;

export const config = {
    users: {
        ...users,
        // ...
    },
    // ...
};
```

**Pattern**:
- Change to default import
- Add `with { type: 'json' }` attribute
- Extract needed properties from imported object

**How to find**:
```bash
grep -r "from.*\.json" . --include="*.ts" --include="*.js"
```

---

## 4. `__dirname` Shim for ES Modules

**Why**: ES modules don't provide `__dirname` global like CommonJS does.

### Utility Files (`utils/helpers.ts`)

**Before**:
```typescript
import path from 'path';

export const merchantStorageFile = path.resolve(
    __dirname,
    '../.auth/merchant.json'
);
```

**After**:
```typescript
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

export const merchantStorageFile = path.resolve(
    __dirname,
    '../.auth/merchant.json'
);
```

**Pattern**:
- Add imports: `fileURLToPath` from 'url'
- Convert `import.meta.url` to file path
- Extract directory name

**How to find**:
```bash
grep -r "__dirname" . --include="*.ts" --include="*.js"
```

---

## 5. Required Dependencies

**Why**: ES modules need Node.js type definitions, and QIT requires Allure reports.

### package.json

**Before**:
```json
{
    "devDependencies": {
        "@playwright/test": "^1.56.1",
        "playwright-ctrf-json-reporter": "^0.0.26"
    }
}
```

**After**:
```json
{
    "devDependencies": {
        "@playwright/test": "^1.56.1",
        "@types/node": "^20.0.0",
        "allure-playwright": "^3.0.0",
        "playwright-ctrf-json-reporter": "^0.0.26"
    }
}
```

**Important**: `allure-playwright` is required for QIT to attach Allure reports to test results.

---

## 6. RestAPI Import

**Why**: `RestAPI` class needs to be imported if used in helper functions.

### Utility Files (`utils/helpers.ts`)

If your helpers use `RestAPI` (like in `getShopper` function):

**Before**:
```typescript
export const getShopper = async (
    browser: Browser,
    asNewCustomer = false,
    baseURL = ''
) => {
    if (asNewCustomer) {
        const restApi = new RestAPI(baseURL); // ❌ RestAPI not imported
        // ...
    }
};
```

**After**:
```typescript
import { RestAPI } from '@qit/helpers'; // ✅ Import RestAPI

export const getShopper = async (
    browser: Browser,
    asNewCustomer = false,
    baseURL = ''
) => {
    if (asNewCustomer) {
        const restApi = new RestAPI(baseURL);
        // ...
    }
};
```

---

## 7. Playwright Reporter Configuration

**Why**: QIT requires specific reporters to be configured for test results and Allure reports.

### playwright.config.js

**Required reporters**:

```javascript
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './specs',

  reporter: [
    ['list'],
    ['html', { open: 'never', outputFolder: './results/html' }],
    ['playwright-ctrf-json-reporter', {
      outputDir: './results',
      outputFile: 'ctrf.json',  // ← Required by QIT
    }],
    ['allure-playwright', {
      resultsDir: './results/allure',  // ← Required for Allure reports
    }],
    ['blob', {
      outputDir: './results/blob',  // ← Required by QIT
    }],
  ],

  use: {
    baseURL: process.env.QIT_SITE_URL || 'http://localhost:8080',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    trace: 'retain-on-failure',
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
```

**Critical**: Without the `allure-playwright` reporter configured with `resultsDir: './results/allure'`, QIT will not attach Allure reports to your test results.

---

## 8. Test Package Manifest - Allure Directory

**Why**: QIT needs to know where to find Allure results to upload them with test reports.

### qit-test.json

Add `allure-dir` to the `results` section:

```json
{
    "$schema": "https://qit.woo.com/json-schema/test-package",
    "package": "my-plugin/e2e-tests",
    "test_type": "e2e",
    "test": {
        "results": {
            "ctrf-json": "./results/ctrf.json",
            "blob-dir": "./results/blob",
            "allure-dir": "./results/allure"
        }
    }
}
```

**Important**:
- The `allure-dir` path must match the `resultsDir` in your `playwright.config.js`
- Without this field, you'll see "No Allure configuration found" and Allure reports won't be uploaded

---

## Summary Checklist

When migrating tests to test packages, check for these code changes:

- [ ] Replace `/qit/bootstrap/` with `./bootstrap/` in all bootstrap scripts
- [ ] Replace `/qitHelpers` with `@qit/helpers` in all test files
- [ ] Add `with { type: 'json' }` to JSON imports
- [ ] Use default imports for JSON files
- [ ] Add `__dirname` shim in files that use it
- [ ] Add `@types/node` and `allure-playwright` to package.json devDependencies
- [ ] Add `allure-playwright` reporter to playwright.config.js with `resultsDir: './results/allure'`
- [ ] Add `"allure-dir": "./results/allure"` to qit-test.json results section
- [ ] Import `RestAPI` from `@qit/helpers` if used
- [ ] Run `npm install` to install dependencies

---

## Why These Changes?

All these changes stem from two fundamental shifts:

1. **Docker volume mounts → File copying**: Test packages are self-contained folders that get copied into the test environment, so absolute paths don't work.

2. **CommonJS → ES Modules**: Test packages use ES modules (`"type": "module"`), which have different syntax and don't provide CommonJS globals like `__dirname`.
