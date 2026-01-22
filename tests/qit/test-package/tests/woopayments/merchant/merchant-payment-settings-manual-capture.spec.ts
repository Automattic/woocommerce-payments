/**
 * External dependencies
 */
import { test, expect } from '../../../fixtures/auth';

/**
 * Internal dependencies
 */
import { goToWooPaymentsSettings } from '../../../utils/merchant';

test.describe(
	'As a merchant, I should be prompted a confirmation modal when I try to activate the manual capture',
	{ tag: [ '@merchant', '@critical' ] },
	() => {
		test.beforeEach( async ( { adminPage } ) => {
			await goToWooPaymentsSettings( adminPage );

			// Verify we're on the WooPayments settings page
			await expect(
				adminPage.getByRole( 'heading', { name: 'WooPayments' } )
			).toBeVisible();

			// Reset manual capture to disabled state before each test
			const manualCaptureCheckbox = adminPage.getByTestId(
				'capture-later-checkbox'
			);
			const isChecked = await manualCaptureCheckbox.isChecked();

			if ( isChecked ) {
				await manualCaptureCheckbox.click();
				// Wait for any modal and dismiss it if needed
				const saveButton = adminPage.getByRole( 'button', {
					name: /save changes/i,
				} );
				if ( await saveButton.isVisible().catch( () => false ) ) {
					await saveButton.click();
					await adminPage.waitForTimeout( 1000 );
				}
			}

			// Now click to set up the initial state for the test
			await adminPage.getByTestId( 'capture-later-checkbox' ).click();
		} );

		test( 'should show the confirmation dialog when enabling the manual capture', async ( {
			adminPage,
		} ) => {
			// The beforeEach already clicked the checkbox, so we should see the modal
			await expect(
				adminPage.getByText(
					'Payments must be captured within 7 days or the authorization will expire and money will be returned to the shopper'
				)
			).toBeVisible( {
				timeout: 10000,
			} );
		} );

		test( 'should not show the confirmation dialog when disabling the manual capture', async ( {
			adminPage,
		} ) => {
			// First confirm the modal to enable manual capture
			await adminPage
				.getByRole( 'button', { name: 'Enable manual capture' } )
				.click();

			// Wait for the modal to close and settings to update
			await adminPage.waitForTimeout( 1000 );

			// Now disable manual capture
			await adminPage.getByTestId( 'capture-later-checkbox' ).click();

			// Verify no modal appears when disabling
			await expect(
				adminPage.locator( '.wcpay-modal' )
			).not.toBeVisible();

			// Verify non-card payment methods are re-enabled (like Bancontact)
			await expect(
				adminPage.getByRole( 'checkbox', { name: 'Bancontact' } )
			).not.toBeDisabled();
		} );

		test( 'should show the non-card methods disabled when manual capture is enabled', async ( {
			adminPage,
		} ) => {
			// Confirm the modal to enable manual capture
			await adminPage
				.getByRole( 'button', { name: 'Enable manual capture' } )
				.click();

			// Wait for the settings to update
			await adminPage.waitForTimeout( 1000 );

			// Verify Bancontact is disabled when manual capture is enabled
			await expect(
				adminPage.getByRole( 'checkbox', { name: 'Bancontact' } )
			).toBeDisabled();

			// Verify the warning message is shown
			await expect(
				adminPage.getByText(
					'Bancontact is not available to your customers when the "manual capture" setting is enabled.'
				)
			).toBeVisible();
		} );
	}
);
