# Playwright JSON Test Results Report

> Save Playwright test results as a JSON file

A Playwright JSON test reporter to create test reports that follow the CTRF standard.

[Common Test Report Format](https://ctrf.io) ensures the generation of uniform JSON test reports, independent of programming languages or test framework in use.

<div align="center">
<div style="padding: 1.5rem; border-radius: 8px; margin: 1rem 0; border: 1px solid #30363d;">
<span style="font-size: 23px;">💚</span>
<h3 style="margin: 1rem 0;">CTRF tooling is open source and free to use</h3>
<p style="font-size: 16px;">You can support the project with a follow and a star</p>

<div style="margin-top: 1.5rem;">
<a href="https://github.com/ctrf-io/playwright-ctrf-json-reporter">
<img src="https://img.shields.io/github/stars/ctrf-io/playwright-ctrf-json-reporter?style=for-the-badge&color=2ea043" alt="GitHub stars">
</a>
<a href="https://github.com/ctrf-io">
<img src="https://img.shields.io/github/followers/ctrf-io?style=for-the-badge&color=2ea043" alt="GitHub followers">
</a>
</div>
</div>
<p style="font-size: 14px; margin: 1rem 0;">

Contributions are very welcome! <br/>
Explore more <a href="https://www.ctrf.io/integrations">integrations</a> <br/>
<a href="https://app.formbricks.com/s/cmefs524mhlh1tl01gkpvefrb">Let us know your thoughts</a>.

</p>
</div>

## Features

![Static Badge](https://img.shields.io/badge/official-red?label=ctrf&labelColor=green)
[![build](https://github.com/ctrf-io/playwright-ctrf-json-report/actions/workflows/main.yaml/badge.svg)](https://github.com/ctrf-io/playwright-ctrf-json-report/actions/workflows/main.yaml)
![NPM Downloads](https://img.shields.io/npm/d18m/playwright-ctrf-json-reporter?logo=npm)
![npm bundle size](https://img.shields.io/bundlephobia/minzip/playwright-ctrf-json-reporter?label=Size)
![GitHub Repo stars](https://img.shields.io/github/stars/ctrf-io/playwright-ctrf-json-report)

- Generate JSON test reports that are [CTRF](https://ctrf.io) compliant
- Customizable output options, minimal or comprehensive reports
- Straightforward integration with Playwright
- Enhanced test insights with detailed test information, environment details, and more.

```json
{
  "results": {
    "tool": {
      "name": "playwright"
    },
    "summary": {
      "tests": 1,
      "passed": 1,
      "failed": 0,
      "pending": 0,
      "skipped": 0,
      "other": 0,
      "start": 1706828654274,
      "stop": 1706828655782
    },
    "tests": [
      {
        "name": "ctrf should generate the same report with any tool",
        "status": "passed",
        "duration": 100
      }
    ],
    "environment": {
      "appName": "MyApp",
      "buildName": "MyBuild",
      "buildNumber": "1"
    }
  }
}
```

## Installation

```bash
npm install --save-dev playwright-ctrf-json-reporter
```

Add the reporter to your playwright.config.ts file:

```javascript
reporter: [
  ['list'], // You can combine multiple reporters
  ['playwright-ctrf-json-reporter', {}]
],
```

Run your tests:

```bash
npx playwright test
```

You'll find a JSON file named `ctrf-report.json` in the `ctrf` directory.

## Reporter Options

The reporter supports several configuration options:

```javascript
reporter: [
    ['playwright-ctrf-json-reporter', {
        outputFile: 'custom-name.json', // Optional: Output file name. Defaults to 'ctrf-report.json'.
        outputDir: 'custom-directory',  // Optional: Output directory path. Defaults to '.' (project root).
        minimal: true,                  // Optional: Generate a minimal report. Defaults to 'false'. Overrides screenshot and testType when set to true
        screenshot: false,              // Optional: Include screenshots in the report. Defaults to 'false'.
        annotations: false,             // Optional: Include annotations in the report. Defaults to 'false'.
        testType: 'e2e',                // Optional: Specify the test type (e.g., 'api', 'e2e'). Defaults to 'e2e'.
        appName: 'MyApp',               // Optional: Specify the name of the application under test.
        appVersion: '1.0.0',            // Optional: Specify the version of the application under test.
        osPlatform: 'linux',            // Optional: Specify the OS platform.
        osRelease: '18.04',             // Optional: Specify the OS release version.
        osVersion: '5.4.0',             // Optional: Specify the OS version.
        buildName: 'MyApp Build',       // Optional: Specify the build name.
        buildNumber: '100',             // Optional: Specify the build number.
        buildUrl: "https://ctrf.io",    // Optional: Specify the build url.
        repositoryName: "ctrf-json",    // Optional: Specify the repository name.
        repositoryUrl: "https://gh.io", // Optional: Specify the repository url.
        branchName: "main",             // Optional: Specify the branch name.
        testEnvironment: "staging"      // Optional: Specify the test environment (e.g. staging, production).
    }]
  ],
```

A comprehensive report is generated by default, with the exception of screenshots, which you must explicitly set to true.

To disable stdio use Playwright built in `quiet` configuration option.

## Merge reports

When running tests in parallel, each test shard has its own test report. If you want to have a combined report showing all the test results from all the shards, you can merge them.

The [ctrf-cli](https://github.com/ctrf-io/ctrf-cli) package provides a method to merge multiple ctrf json files into a single file.

After executing your tests, use the following command:

```sh
npx ctrf merge <directory>
```

Replace directory with the path to the directory containing the CTRF reports you want to merge.

## Test Object Properties

The test object in the report includes the following [CTRF properties](https://ctrf.io/docs/schema/test):

| Name          | Type             | Required | Details                                                                             |
| ------------- | ---------------- | -------- | ----------------------------------------------------------------------------------- |
| `name`        | String           | Required | The name of the test.                                                               |
| `status`      | String           | Required | The outcome of the test. One of: `passed`, `failed`, `skipped`, `pending`, `other`. |
| `duration`    | Number           | Required | The time taken for the test execution, in milliseconds.                             |
| `start`       | Number           | Optional | The start time of the test as a Unix epoch timestamp.                               |
| `stop`        | Number           | Optional | The end time of the test as a Unix epoch timestamp.                                 |
| `suite`       | String           | Optional | The suite or group to which the test belongs.                                       |
| `message`     | String           | Optional | The failure message if the test failed.                                             |
| `trace`       | String           | Optional | The stack trace captured if the test failed.                                        |
| `snippet`     | String           | Optional | The code snippet that was executed during the test if the test failed.              |
| `rawStatus`   | String           | Optional | The original playwright status of the test before mapping to CTRF status.           |
| `tags`        | Array of Strings | Optional | The tags retrieved from the test name                                               |
| `type`        | String           | Optional | The type of test (e.g., `api`, `e2e`).                                              |
| `filepath`    | String           | Optional | The file path where the test is located in the project.                             |
| `retries`     | Number           | Optional | The number of retries attempted for the test.                                       |
| `flaky`       | Boolean          | Optional | Indicates whether the test result is flaky.                                         |
| `browser`     | String           | Optional | The browser used for the test.                                                      |
| `attachments` | Array of Objects | Optional | The attachments attached to the test.                                               |
| `stdout`      | Array of Strings | Optional | The standard output of the test.                                                    |
| `stderr`      | Array of Strings | Optional | The standard error of the test.                                                     |
| `screenshot`  | String           | Optional | A base64 encoded screenshot taken during the test.                                  |
| `steps`       | Array of Objects | Optional | Individual steps in the test, especially for BDD-style testing.                     |

## Advanced usage

Some features require additional setup or usage considerations.

### Annotations

By setting `annotations: true` you can include annotations in the test extra property.

### Screenshots

You can include base-64 screenshots in your test report, you'll need to capture and attach screenshots in your Playwright tests:

```javascript
import { test, expect } from '@playwright/test'

test('basic test', async ({ page }, testInfo) => {
  await page.goto('https://playwright.dev')
  const screenshot = await page.screenshot({ quality: 50, type: 'jpeg' })
  await testInfo.attach('screenshot', {
    body: screenshot,
    contentType: 'image/jpeg',
  })
})
```

#### Supported Formats

Both JPEG and PNG formats are supported, only the last screenshot attached from each test will be included in the report.

#### Size Considerations

Base64-encoded image data can greatly increase the size of your report, it's recommended to use screenshots with a lower quality setting (less than 50%) to reduce file size, particularly if you are generating JPEG images.

### Browser

You can include browser information in your test report. You will need to extend Playwright's test object to capture and attach browser metadata for each test:

```javascript
// tests/helpers.ts
import { test as _test, expect } from '@playwright/test';
import os from 'os';

export const test = _test.extend<{ _autoAttachMetadata: void }>({
    _autoAttachMetadata: [async ({ browser, browserName }, use, testInfo) => {
        // BEFORE: Generate an attachment for the test with the required info
        await testInfo.attach('metadata.json', {
            body: JSON.stringify({
                name: browserName,
                version: browser.version(),
            })
        })

        // ---------------------------------------------------------
        await use(/** our test doesn't need this fixture direcly */);
        // ---------------------------------------------------------

        // AFTER: There's nothing to cleanup in this fixutre
    }, { auto: true }],
})

export { expect };
```

Replace the standard Playwright test import with the custom test fixture in your test files:

```javascript
// tests/my-test.spec.ts
import { test, expect } from './helpers' // Adjust the path as necessary

test('example test', async ({ page }) => {
  // ... your test logic ...
})
```

The browser metadata file must be called metadata.json and contain properties name and version in the body.

## What is CTRF?

CTRF is a universal JSON test report schema that addresses the lack of a standardized format for JSON test reports.

**Consistency Across Tools:** Different testing tools and frameworks often produce reports in varied formats. CTRF ensures a uniform structure, making it easier to understand and compare reports, regardless of the testing tool used.

**Language and Framework Agnostic:** It provides a universal reporting schema that works seamlessly with any programming language and testing framework.

**Facilitates Better Analysis:** With a standardized format, programatically analyzing test outcomes across multiple platforms becomes more straightforward.

## Support Us

If you find this project useful, consider giving it a GitHub star ⭐ It means a lot to us.
