/* eslint-disable jest/no-export, jest/no-disabled-tests */

/**
 * Internal dependencies
 */
const {
	merchant,
	completeOnboardingWizard,
	withRestApi,
} = require( '@woocommerce/e2e-utils' );

describe( 'Onboarding > Setup onboarding wizard', () => {
	beforeAll( async () => {
		await merchant.login();
		await withRestApi.resetOnboarding();
		await withRestApi.deleteAllShippingZones();
	} );

	it( 'can complete onboarding when visiting the first time', async () => {
		await completeOnboardingWizard();
	} );
} );

describe( 'Onboarding > Setup task list', () => {
	it( 'can setup shipping', async () => {
		await page.evaluate( () => {
			document
				.querySelector( '.woocommerce-list__item-title' )
				.scrollIntoView();
		} );

		// Click on "Set up shipping" task to move to the next step
		const [ setupTaskListItem ] = await page.$x(
			'//div[contains(text(),"Set up shipping")]'
		);
		await setupTaskListItem.click();

		// Wait for "Proceed" button to become active
		await page.waitForSelector( 'button.is-primary:not(:disabled)' );
		await page.waitFor( 3000 );

		// Click on "Proceed" button to save shipping settings
		await page.click( 'button.is-primary' );
		await page.waitFor( 3000 );
	} );
} );
