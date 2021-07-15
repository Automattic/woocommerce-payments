/**
 * External dependencies
 */
const {
	merchant,
	completeOnboardingWizard,
	withRestApi,
	IS_RETEST_MODE,
} = require( '@woocommerce/e2e-utils' );

describe( 'Onboarding > WooCommerce Setup Wizard & Task List', () => {
	beforeAll( async () => {
		await merchant.login();
	} );

	afterAll( async () => {
		await merchant.logout();
	} );

	if ( IS_RETEST_MODE ) {
		it( 'can reset onboarding to default settings', async () => {
			await withRestApi.resetOnboarding();
		} );

		it( 'can reset shipping zones to default settings', async () => {
			await withRestApi.deleteAllShippingZones();
		} );

		it( 'can reset to default settings', async () => {
			await withRestApi.resetSettingsGroupToDefault( 'general' );
			await withRestApi.resetSettingsGroupToDefault( 'products' );
			await withRestApi.resetSettingsGroupToDefault( 'tax' );
		} );
	}

	it( 'can complete onboarding when visiting the first time', async () => {
		await completeOnboardingWizard();
	} );

	it( 'can dismiss tax setup', async () => {
		await merchant.runSetupWizard();
		await page.evaluate( () => {
			document
				.querySelector( '.woocommerce-list__item-title' )
				.scrollIntoView();
		} );

		// Click on "Set up tax" task to move to the next step
		const [ setUpTax ] = await page.$x(
			'//div[contains(text(),"Set up tax")]'
		);
		await setUpTax.click();

		// Click to dismiss setting up taxes
		await expect( page ).toClick( 'button.components-button.is-tertiary', {
			text: "I don't charge sales tax",
		} );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
	} );

	it( 'can setup shipping', async () => {
		await page.evaluate( () => {
			document
				.querySelector( '.woocommerce-list__item-title' )
				.scrollIntoView();
		} );

		// Click on "Set up shipping" task to move to the next step
		const [ setUpShipping ] = await page.$x(
			'//div[contains(text(),"Set up shipping")]'
		);
		await setUpShipping.click();

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
