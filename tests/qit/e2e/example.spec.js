/*
 * This is an example E2E test. You can write your own tests, or generate them with Codegen.
 *
 * Read more about it on our documentation: https://qit.woo.com/docs/custom-tests/generating-tests
 */

/* eslint-disable wpcalypso/import-docblock */

import { test, expect } from '@playwright/test';
import qit from '/qitHelpers';

test( 'I can see my plugin menu', async ( { page } ) => {
	// Log-in as admin.
	await qit.loginAsAdmin( page );
	// View WordPress Core "Dashboard" heading
	await expect(
		page.getByRole( 'heading', { name: 'Dashboard' } )
	).toBeVisible();
	// Click on my menu on the sidebar.
	// await page.getByRole('link', { name: 'My Plugin Menu', exact: true }).click();
	// await page.waitForLoadState('networkidle');
	// Assert I see my welcome message when I click it.
	// await expect(page.locator('h3')).toContainText('Welcome to My Plugin!');
} );
