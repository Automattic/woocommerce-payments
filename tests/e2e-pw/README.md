# Playwright end-to-end tests 🎭

Playwright e2e tests can be found in the `./tests/e2e-pw/specs` directory. These will run in parallel with the existing Puppeteer e2e tests and are intended to replace them as they are migrated.

## Setup local e2e environment

See [tests/e2e/README.md](/tests/e2e/README.md) for detailed e2e environment setup instructions.

1. `npm run test:e2e-setup`
1. `npm run test:e2e-up`

## Running Playwright e2e tests

-   `npm run test:e2e-pw` headless run from within a linux docker container.
-   `npm run test:e2e-pw-ui` runs tests in interactive UI mode from within a linux docker container – recommended for authoring tests and re-running failed tests.
-   `npm run test:e2e-pw keyword` runs tests only with a specific keyword in the file name, e.g. `dispute` or `checkout`.
-   `npm run test:e2e-pw --update-snapshots` updates snapshots.

## FAQs

**How do I wait for a page or element to load?**

Since [Playwright automatically waits](https://playwright.dev/docs/actionability) for elements to be present in the page before interacting with them, you probably don't need to explicitly wait for elements to load. For example, all of the following locators will automatically wait for the element to be present and stable before asserting or interacting with it:

```ts
await expect( page.getByRole( 'heading', { name: 'Sign up' } ) ).toBeVisible();
await page.getByRole( 'checkbox', { name: 'Subscribe' } ).check();
await page.getByRole( 'button', { name: /submit/i } ).click();
```

In some cases, you may need to wait for the page to reach a certain load state before interacting with it. You can use `await page.waitForLoadState( 'domcontentloaded' );` to wait for the page to finish loading.

**What is the best way to target elements in the page?**

Prefer the use of [user-facing attribute or test-id locators](https://playwright.dev/docs/locators#locating-elements) to target elements in the page. This will make the tests more resilient to changes to implementation details, such as class names.

```ts
// Prefer locating by role, label, text, or test id when possible. See https://playwright.dev/docs/locators
await page.getByRole( 'button', { name: 'All deposits' } ).click();
await page.getByLabel( 'Select a deposit status' ).selectOption( 'Pending' );
await expect( page.getByText( 'Order received' ) ).toBeVisible();
await page.getByTestId( 'accept-dispute-button' ).click();

// Use CSS selectors as a last resort
await page.locator( 'button.components-button.is-secondary' );
```

**How do I create a visual regression test?**

Visual regression tests are captured by the [`toHaveScreenshot()` function](https://playwright.dev/docs/api/class-pageassertions#page-assertions-to-have-screenshot-2). This function takes a screenshot of a page or element and compares it to a reference image. If the images are different, the test will fail.

```ts
await expect( page ).toHaveScreenshot();

await expect(
	page.getByRole( 'button', { name: 'All deposits' } )
).toHaveScreenshot();
```

**How can I act as shopper or merchant in a test?**

1. To switch between `shopper` and `merchant` role in a test, use the `getShopper` and `getMerchant` function:

```ts
import { getShopper, getMerchant } from './utils/helpers';

test( 'should do things as shopper and merchant', async ( { browser } ) => {
	const { shopperPage } = await getShopper( browser );
	const { merchantPage } = await getMerchant( browser );

	// do things as shopper
	await shopperPage.goto( '/cart/' );

	// do things as merchant
	await merchantPage.goto( '/wp-admin/admin.php?page=wc-settings' );
} );
```

2. To act as `shopper` or `merchant` for an entire test suite (`describe`), use the helper function `useShopper` or `useMerchant` from `tests/e2e-pw/utils/helpers.ts`:

```ts
import { useShopper } from '../utils/helpers';

test.describe( 'Sign in as customer', () => {
	useShopper();
	test( 'Load customer my account page', async ( { page } ) => {
		// do things as shopper
		await page.goto( '/my-account' );
	} );
} );
```

**How can I investigate and interact with a test failures?**

-   **Github Action test runs**
    -   View GitHub checks in the "Checks" tab of a PR
    -   Click on the "E2E Playwright Tests" job to see the job summary
    -   Download the `playwright-report.zip` artifact, extract and copy the `playwright-report` directory to the root of the WooPayments repository
    -   Run `npx playwright show-report` to open the report in a browser
-   **Local test runs**:
    -   Local test reports will output in the `playwright-report` directory
    -   Run `npx playwright show-report` to open the report in a browser
