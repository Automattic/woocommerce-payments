/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { useMerchant } from '../../utils/helpers';
import * as devtools from '../../utils/devtools';
import { goToConnect } from '../../utils/merchant-navigation';

test.describe( 'Admin merchant progressive onboarding', () => {
	useMerchant();

	test.beforeAll( async ( { browser } ) => {
		const page = await browser.newPage();
		await devtools.enableActAsDisconnectedFromWCPay( page );
	} );

	test.afterAll( async ( { browser } ) => {
		const page = await browser.newPage();
		await devtools.disableActAsDisconnectedFromWCPay( page );
	} );

	test( 'should pass merchant flow without any errors', async ( {
		page,
	} ) => {
		// Open connect account page and click the primary CTA to start onboarding.
		await goToConnect( page );
		// Start onboarding process
		await page
			.getByRole( 'button', { name: 'Verify business details' } )
			.click();
		// Pick Individual business entity
		await page
			.getByRole( 'button', {
				name: 'What type of legal entity is',
			} )
			.click();
		await page.getByRole( 'option', { name: 'Individual' } ).click();
		// Pick Software MCC
		await page.getByLabel( 'Select an option' ).click();
		await page.getByText( 'Software' ).click();
		// Accept terms and conditions
		await page.getByRole( 'button', { name: 'Continue' } ).click();
		// Pick annual revenue
		await page
			.getByRole( 'button', {
				name: 'What is your estimated annual',
			} )
			.click();
		await page.getByRole( 'option', { name: 'Less than $250k' } ).click();
		// Pick estimated time to launch
		await page
			.getByRole( 'button', { name: 'What is the estimated timeline' } )
			.click();
		await page.getByRole( 'option', { name: 'Within 1 month' } ).click();
		await page.getByRole( 'button', { name: 'Continue' } ).click();

		// Check that Stripe Embedded KYC iframe is loaded.
		await expect(
			page.locator(
				'iframe[data-testid="stripe-connect-ui-layer-stripe-connect-account-onboarding"]'
			)
		).toBeAttached();
	} );
} );
