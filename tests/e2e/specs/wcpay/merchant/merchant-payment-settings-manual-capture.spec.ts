/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';
/**
 * Internal dependencies
 */
import { useMerchant } from '../../../utils/helpers';
import { goToWooPaymentsSettings } from '../../../utils/merchant-navigation';

test.describe(
	'As a merchant, I should be prompted a confirmation modal when I try to activate the manual capture',
	{ tag: '@critical' },
	() => {
		useMerchant();

		test.beforeEach( async ( { page } ) => {
			await goToWooPaymentsSettings( page );
			await page.getByTestId( 'capture-later-checkbox' ).click();
		} );

		test( 'should show the confirmation dialog when enabling the manual capture', async ( {
			page,
		} ) => {
			await expect(
				page.getByText(
					/must be captured on the order details screen within 7 days of authorization/i
				)
			).toBeVisible( {
				timeout: 10000,
			} );
		} );

		test( 'should not show the confirmation dialog when disabling the manual capture', async ( {
			page,
		} ) => {
			await page
				.getByRole( 'button', { name: 'Enable manual capture' } )
				.click();
			await page.getByTestId( 'capture-later-checkbox' ).click();
			await expect( page.locator( '.wcpay-modal' ) ).not.toBeVisible();

			await expect(
				page.getByRole( 'checkbox', { name: 'Bancontact' } )
			).not.toBeDisabled();
		} );

		test( 'should show the non-card methods disabled when manual capture is enabled', async ( {
			page,
		} ) => {
			await page
				.getByRole( 'button', { name: 'Enable manual capture' } )
				.click();
			await expect(
				page.getByRole( 'checkbox', { name: 'Bancontact' } )
			).toBeDisabled();
			await expect(
				page
					.locator( '#payment-methods' )
					.getByText(
						/Manual capture is enabled, so any payment methods that don't support it have been automatically disabled/i
					)
			).toBeVisible();
		} );
	}
);
