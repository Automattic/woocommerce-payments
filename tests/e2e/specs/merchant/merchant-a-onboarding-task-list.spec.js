/**
 * External dependencies
 */
const { merchant } = require( '@woocommerce/e2e-utils' );

describe( 'Onboarding > WooCommerce Setup Wizard & Task List', () => {
	beforeAll( async () => {
		await merchant.login();
	} );

	afterAll( async () => {
		await merchant.logout();
	} );

	it( 'can setup shipping', async () => {
		await merchant.runSetupWizard();
		await page.waitForSelector( 'div.woocommerce-homescreen' );

		await page.evaluate( () => {
			document
				.querySelector( '.woocommerce-list__item-title' )
				.scrollIntoView();
		} );
		// Query for all tasks on the list
		const taskListItems = await page.$$( '.woocommerce-list__item-title' );
		expect( taskListItems.length ).toBeInRange( 5, 6 );

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

	it( 'can dismiss tax setup', async () => {
		await merchant.runSetupWizard();
		await page.waitForSelector( 'div.woocommerce-homescreen' );

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
} );
