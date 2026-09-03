# Getting Started: Migrating to QIT Test Packages

A simple, step-by-step guide to migrating your plugin's E2E tests to the new QIT test packages format.

---

## What Are Test Packages?

**Test packages** are the new way to write and share E2E tests for WordPress/WooCommerce plugins.

### Why the Change?

**The Old Way (Legacy Custom Tests)**:
- Tests lived inside your plugin repository
- Used custom configuration files (`qit.yml`)
- Couldn't be shared with other developers
- Relied on special setup scripts

**The New Way (Test Packages)**:
- Tests live in their own separate folder (can be anywhere!)
- Uses standard configuration (`qit-test.json`)
- Can be published and shared with anyone
- Self-contained - includes everything needed to run

### Key Benefits

✅ **Portable** - Copy the folder anywhere, it just works
✅ **Shareable** - Publish to QIT registry for others to use
✅ **Reusable** - Other plugins can run YOUR tests to verify compatibility
✅ **Cleaner** - Keeps tests separate from your plugin code

---

## Before You Start

You'll need:
- Your plugin's existing E2E tests (from the old format)
- QIT CLI installed: `composer global require "woocommerce/qit-cli:*"`
- Node.js and npm installed

**Estimated Time**: 30-45 minutes

> **📘 Need Technical Details?** See [CODE_CHANGES.md](./CODE_CHANGES.md) for detailed before/after code examples and explanations of all the changes needed.

---

## Step-by-Step Migration

### Step 1: Create a New Test Package

Use QIT's scaffold command to create a template:

```bash
# Navigate to where you want the test package
cd /path/to/your/workspace

# Create the package (replace with your plugin name)
qit package:scaffold my-plugin-e2e-tests \
  --package=my-plugin/e2e-tests:1.0.0 \
  --with-schema \
  --no-interaction
```

This creates a folder with everything you need:
```
my-plugin-e2e-tests/
├── qit-test.json          # Package configuration
├── package.json           # Dependencies
├── playwright.config.js   # Test runner config
├── bootstrap/             # Setup scripts
├── tests/                 # Your tests go here
└── results/               # Test results
```

---

### Step 2: Copy Your Test Files

Copy your existing tests to the new package:

```bash
cd my-plugin-e2e-tests

# Copy test files (update paths to match your plugin)
rm -rf tests
cp -r /path/to/my-plugin/tests/qit/e2e/specs ./specs
cp -r /path/to/my-plugin/tests/qit/e2e/utils ./utils
cp -r /path/to/my-plugin/tests/qit/e2e/fixtures ./fixtures
cp -r /path/to/my-plugin/tests/qit/e2e/config ./config
```

---

### Step 3: Copy Bootstrap Scripts

Bootstrap scripts set up your test environment (create users, configure settings, etc.):

```bash
# Copy all bootstrap files
cp /path/to/my-plugin/tests/qit/e2e/bootstrap/*.sh ./bootstrap/
cp /path/to/my-plugin/tests/qit/e2e/bootstrap/*.php ./bootstrap/
```

**IMPORTANT**: Two changes are needed in bootstrap scripts:

1. **Fix paths** - Replace `/qit/bootstrap/` with `./bootstrap/`

2. **Create correct test users** - Your bootstrap must create users matching `config/users.json`:

```bash
# In bootstrap/setup.sh, create users with these exact credentials:
wp user create customer customer@example.com \
    --role=customer --user_pass=password --quiet

wp user create editor editor@example.com \
    --role=editor --user_pass=password --quiet
```

See [CODE_CHANGES.md](./CODE_CHANGES.md#1-bootstrap-path-changes) for details.

---

### Step 4: Add QIT Helpers

The new format uses a local helpers package instead of the global `/qitHelpers`:

1. **Include qit-helpers** - Copy the `qit-helpers` folder from QIT CLI's reference tests into your test package
2. **Update package.json** - Add the dependency:

```json
{
  "dependencies": {
    "@qit/helpers": "file:./qit-helpers"
  }
}
```

3. **Update imports** - Change all imports from `/qitHelpers` to `@qit/helpers` in your test files

---

### Step 5: Fix ES Module Issues

The new format uses ES modules, which require some code changes:

#### A. Add required dependencies

Open `package.json` and add to `devDependencies`:

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

After adding these dependencies, install them:

```bash
npm install
```

#### B. Fix JSON imports

**Find files importing JSON**:
```bash
grep -r "from.*\.json" . --include="*.ts" --include="*.js"
```

**Before** (doesn't work):
```typescript
import { users } from './users.json';
```

**After** (works):
```typescript
import usersData from './users.json' with { type: 'json' };
const users = usersData.users;
```

#### C. Fix `__dirname` usage

**Find files using `__dirname`**:
```bash
grep -r "__dirname" . --include="*.ts" --include="*.js"
```

**For each file found**, add this at the top:
```typescript
import { fileURLToPath } from 'url';
import path from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
```

---

### Step 6: Configure the Package

Edit `qit-test.json` to describe your test package:

```json
{
    "$schema": "https://qit.woo.com/json-schema/test-package",
    "package": "my-plugin/e2e-tests",
    "test_type": "e2e",
    "description": "E2E tests for My Plugin",
    "tags": ["my-plugin", "e2e"],
    "requires": {
        "network": true,
        "secrets": [
            "MY_API_KEY",
            "MY_SECRET_TOKEN"
        ],
        "plugins": {
            "woocommerce": "woocommerce"
        }
    },
    "test": {
        "phases": {
            "globalSetup": [
                "./bootstrap/setup.sh"
            ],
            "setup": [
                "npm ci",
                "npx playwright install chromium --with-deps"
            ],
            "run": [
                "npx playwright test"
            ],
            "teardown": [],
            "globalTeardown": []
        },
        "results": {
            "ctrf-json": "./results/ctrf.json",
            "blob-dir": "./results/blob",
            "allure-dir": "./results/allure"
        }
    },
    "timeout": 1800
}
```

**Key fields to update**:
- `package` - Your package name (format: `plugin-name/package-name`)
- `description` - What your tests do
- `requires.secrets` - Environment variables your tests need
- `requires.plugins` - Plugins that must be installed
- `results.allure-dir` - **Required** for QIT to collect Allure reports

---

### Step 7: Update Playwright Config

Edit `playwright.config.js` to point to your tests:

```javascript
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './specs',  // ← Point to your test directory

  fullyParallel: false,
  workers: 1,

  reporter: [
    ['list'],
    ['html', { open: 'never', outputFolder: './results/html' }],
    ['playwright-ctrf-json-reporter', {
      outputDir: './results',
      outputFile: 'ctrf.json',  // ← Required by QIT
    }],
    ['allure-playwright', {
      resultsDir: './results/allure',  // ← Required for Allure reports in QIT
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

**Must have**:
- `playwright-ctrf-json-reporter` - QIT requires this for test results
- `allure-playwright` - QIT requires this to attach Allure reports
- `blob` reporter - For screenshots/videos

---

### Step 8: Create qit.json (Optional but Recommended)

Create `qit.json` in the test package folder to make running tests easier:

```json
{
    "$schema": "https://qit.woo.com/json-schema/qit",
    "sut": {
        "type": "plugin",
        "slug": "my-plugin",
        "source": {
            "type": "local",
            "path": "../my-plugin/my-plugin.zip"
        }
    },
    "environments": {
        "default": {
            "php": "8.3",
            "wp": "stable",
            "woo": "stable",
            "plugins": ["woocommerce"],
            "themes": ["storefront"],
            "env": {
                "MY_API_KEY": "${MY_API_KEY}",
                "MY_SECRET_TOKEN": "${MY_SECRET_TOKEN}"
            }
        }
    },
    "test_types": {
        "e2e": {
            "default": {
                "test_packages": ["."],
                "environment": "default"
            }
        }
    }
}
```

Update:
- `sut.slug` - Your plugin slug
- `sut.source.path` - Path to your plugin's .zip file
- `env` - Your environment variables

---

## Running Your Tests

### Step 1: Set Up Environment Variables

Your tests need access to secrets (API keys, tokens, etc.). Export them in your terminal:

```bash
export MY_API_KEY=your_actual_key
export MY_SECRET_TOKEN=your_actual_token
# Add any other secrets your tests require
```

**Tip**: You can add these to your shell profile (`~/.bashrc`, `~/.zshrc`) to persist them across sessions.

### Step 2: Build Your Plugin

```bash
cd /path/to/my-plugin
npm run build  # or however you build your plugin
```

### Step 3: Run the Tests

**Option A - Using qit.json (Recommended)**:

If you created `qit.json` in Step 8:

```bash
cd /path/to/my-plugin-e2e-tests
qit run:e2e my-plugin . --config qit.json
```

The environment variables you exported will be automatically picked up from your shell.

**Option B - Quick run without qit.json**:

```bash
cd /path/to/my-plugin-e2e-tests
qit run:e2e my-plugin . \
  --source ../my-plugin/my-plugin.zip \
  --env MY_API_KEY=${MY_API_KEY} \
  --env MY_SECRET_TOKEN=${MY_SECRET_TOKEN}
```

---

## Troubleshooting

> **💡 Tip**: For detailed code examples and find/replace commands, see [CODE_CHANGES.md](./CODE_CHANGES.md)

### Tests can't find files

**Problem**: `Error: '/qit/bootstrap/my-file.php' does not exist`

**Solution**: You missed updating paths in Step 3. All `/qit/bootstrap/` should be `./bootstrap/`. See [CODE_CHANGES.md](./CODE_CHANGES.md#1-bootstrap-path-changes) for find/replace commands.

---

### Can't find /qitHelpers

**Problem**: `Error: Cannot find module '/qitHelpers'`

**Solution**: You missed Step 4. Need to:
1. Copy `qit-helpers/` folder
2. Add `"@qit/helpers": "file:./qit-helpers"` to package.json
3. Change imports from `/qitHelpers` to `@qit/helpers`

See [CODE_CHANGES.md](./CODE_CHANGES.md#2-qit-helpers-import-changes) for find/replace commands.

---

### JSON import errors

**Problem**: `Module "file:///.../users.json" needs an import attribute`

**Solution**: Use default imports with `with { type: 'json' }` attribute. See [CODE_CHANGES.md](./CODE_CHANGES.md#3-json-import-changes) for examples.

---

### __dirname not defined

**Problem**: `ReferenceError: __dirname is not defined`

**Solution**: Add ES module `__dirname` shim. See [CODE_CHANGES.md](./CODE_CHANGES.md#4-__dirname-shim-for-es-modules) for the code.

---

### Package lock out of sync

**Problem**: `npm ci` fails with lock file sync error

**Solution**:
```bash
npm install  # Regenerates package-lock.json
```

---

## Next Steps

### Test Locally

1. Build your plugin
2. Run the test package
3. Fix any failing tests
4. Verify test results are generated

### Publish (Optional)

Share your tests with others:

```bash
qit package:publish ./my-plugin-e2e-tests
```

Now others can run:
```bash
qit run:e2e their-plugin --test-package=my-plugin/e2e-tests:1.0.0
```

This helps verify compatibility between plugins!

---

## Quick Reference

### File Structure
```
my-plugin-e2e-tests/
├── qit-test.json          # What to run
├── qit.json               # How to run locally
├── package.json           # Dependencies
├── playwright.config.js   # Test config
├── bootstrap/             # Setup scripts
│   └── setup.sh
├── qit-helpers/           # Helper utilities
├── specs/                 # Your tests
├── utils/                 # Test utilities
├── fixtures/              # Test fixtures
├── config/                # Test config
└── results/               # Test output
```

### Key Commands

```bash
# Scaffold new package
qit package:scaffold my-tests --package=my-plugin/e2e-tests:1.0.0

# Run tests
qit run:e2e my-plugin . --source ../my-plugin.zip

# Publish package
qit package:publish .

# List published packages
qit package:list
```

### Required Changes Checklist

- [ ] Replace `/qit/bootstrap/` with `./bootstrap/`
- [ ] Add `qit-helpers/` folder
- [ ] Change `/qitHelpers` to `@qit/helpers`
- [ ] Fix JSON imports (add `with { type: 'json' }`)
- [ ] Fix `__dirname` usage
- [ ] Add `@types/node` and `allure-playwright` to package.json
- [ ] Add `allure-playwright` reporter to playwright.config.js
- [ ] Add `"allure-dir": "./results/allure"` to qit-test.json
- [ ] Run `npm install`

---

## Getting Help

- **Code Changes**: See [CODE_CHANGES.md](./CODE_CHANGES.md) for detailed before/after code examples
- **Migration Guide**: See [MIGRATION.md](./MIGRATION.md) for comprehensive migration documentation
- **QIT Documentation**: https://qit.woo.com/docs/test-packages
- **Issues**: https://github.com/woocommerce/qit-cli/issues

---

**That's it!** You now have a portable, shareable test package that can run anywhere. 🎉
