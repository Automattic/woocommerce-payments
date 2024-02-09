/**
 * External dependencies
 */
import { test, expect, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { useMerchant } from '../../utils/helpers';

test.describe( 'payment gateways disable confirmation', () => {
	useMerchant();

	const getToggle = ( page: Page ) =>
		page.getByRole( 'link', {
			name: '"WooPayments" payment method is currently',
		} );

	const getModalHeading = ( page: Page ) =>
		page.getByRole( 'heading', { name: 'Disable WooPayments' } );

	const getSaveButton = ( page: Page ) =>
		page.getByRole( 'button', { name: 'Save changes' } );

	const getCancelButton = ( page: Page ) =>
		page.getByRole( 'button', { name: 'Cancel' } );

	const getDisableButton = ( page: Page ) =>
		page.getByRole( 'button', { name: 'Disable' } );

	const waitForToggleLoading = ( page: Page ) =>
		page
			.locator( '.woocommerce-input-toggle--loading' )
			.waitFor( { state: 'hidden' } );

	test.beforeEach( async ( { page } ) => {
		await page.goto(
			'/wp-admin/admin.php?page=wc-settings&tab=checkout&section'
		);

		// If WCPay enabled, disable it
		if ( ( await getToggle( page ).innerText() ) === 'Yes' ) {
			// Click the "Disable WCPay" toggle button
			await getToggle( page ).click();

			// Modal should be displayed
			await expect( getModalHeading( page ) ).toBeVisible();
		}
	} );

	test.afterAll( async ( { browser } ) => {
		// Ensure WCPay is enabled after the tests, even if they fail
		const page = await browser.newPage();
		await page.goto(
			'/wp-admin/admin.php?page=wc-settings&tab=checkout&section'
		);

		if ( ( await getToggle( page ).innerText() ) === 'No' ) {
			await getToggle( page ).click();
			await waitForToggleLoading( page );
			await getSaveButton( page ).click();
		}

		await expect( getToggle( page ) ).toHaveText( 'Yes' );
	} );

	test( 'should show the confirmation modal when disabling WCPay', async ( {
		page,
	} ) => {
		// Clicking "Cancel" should not disable WCPay
		await getCancelButton( page ).click();

		// After clicking "Cancel", the modal should close and WCPay should still be enabled, even after refresh
		await expect( getModalHeading( page ) ).not.toBeVisible();
		await getSaveButton( page ).click();
		await expect( getToggle( page ) ).toHaveText( 'Yes' );
	} );

	test( 'should disable WCPay after confirming, then enable again without confirming', async ( {
		page,
	} ) => {
		// Clicking "Disable" should disable WCPay
		await getDisableButton( page ).click();

		// After clicking "Disable", the modal should close
		await expect( getModalHeading( page ) ).not.toBeVisible();

		// and refreshing the page should show WCPay become disabled
		await waitForToggleLoading( page );
		await getSaveButton( page ).click();

		// now we can re-enable it with no issues
		await getToggle( page ).click();
		await waitForToggleLoading( page );
		await getSaveButton( page ).click();
		await expect( getToggle( page ) ).toHaveText( 'Yes' );
	} );

	test( 'should show the modal even after clicking the cancel button multiple times', async ( {
		page,
	} ) => {
		// Clicking "Cancel" should not disable WCPay
		await getCancelButton( page ).click();

		// After clicking "Cancel", the modal should close and WCPay should still be enabled
		await expect( getModalHeading( page ) ).not.toBeVisible();
		await expect( getToggle( page ) ).not.toHaveClass(
			'woocommerce-input-toggle--disabled'
		);

		// trying again to disable it - the modal should display again
		await getToggle( page ).click();
		await expect( getModalHeading( page ) ).toBeVisible();
	} );
} );
