/**
 * External dependencies
 */
const {
	merchant,
	completeOnboardingWizard,
	withRestApi,
} = require( '@woocommerce/e2e-utils' );

describe( 'Onboarding > WooCommerce Setup Wizard', () => {
	beforeAll( async () => {
		await merchant.login();
		await withRestApi.resetOnboarding();
		await withRestApi.deleteAllShippingZones();
	} );

	afterAll( async () => {
		await merchant.logout();
	} );

	it( 'can complete onboarding when visiting the first time', async () => {
		await completeOnboardingWizard();
	} );
} );

describe( 'Onboarding > WooCommerce Task List', () => {
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

		// Click on "Proceed" button to save shipping settings
		await page.click( 'button.is-primary' );

		// Verify success message from the response
		await expect( page ).toMatchElement(
			'div.components-snackbar__content',
			'Your shipping rates have been updated'
		);

		// Click "No, thanks" to get back to the task list
		await expect( page ).toClick( 'button.components-button.is-tertiary' );
	} );
} );
