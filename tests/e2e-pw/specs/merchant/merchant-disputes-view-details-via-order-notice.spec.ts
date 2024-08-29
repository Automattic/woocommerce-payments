/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import * as shopper from '../../utils/shopper';
import { config } from '../../config/default';
import { getMerchant, getShopper } from '../../utils/helpers';
import { goToOrder } from '../../utils/merchant-navigation';

test.describe(
	'Disputes > View dispute details via disputed order notice',
	() => {
		let orderId: string;

		test.beforeEach( async ( { browser } ) => {
			const { shopperPage } = await getShopper( browser );
			// Place an order to dispute later
			await shopperPage.goto( '/cart/' );
			await shopper.addCartProduct( shopperPage );

			await shopperPage.goto( '/checkout/' );
			await shopper.fillBillingAddress(
				shopperPage,
				config.addresses.customer.billing
			);
			await shopper.fillCardDetails(
				shopperPage,
				config.cards[ 'disputed-fraudulent' ]
			);
			await shopper.placeOrder( shopperPage );

			// Get the order ID
			const orderIdField = shopperPage.locator(
				'.woocommerce-order-overview__order.order > strong'
			);
			orderId = await orderIdField.innerText();
		} );

		test( 'should navigate to dispute details when disputed order notice button clicked', async ( {
			browser,
		} ) => {
			const { merchantPage } = await getMerchant( browser );
			await goToOrder( merchantPage, orderId );

			// If WC < 7.9, return early since the order dispute notice is not present.
			const orderPaymentDetailsContainerVisible = await merchantPage
				.locator( '#wcpay-order-payment-details-container' )
				.isVisible();
			if ( ! orderPaymentDetailsContainerVisible ) {
				// eslint-disable-next-line no-console
				console.log(
					'Skipping test since the order dispute notice is not present in WC < 7.9'
				);
				return;
			}

			// Click the order dispute notice.
			await merchantPage
				.getByRole( 'button', {
					name: 'Respond now',
				} )
				.click();

			// Verify we see the dispute details on the transaction details merchantPage.
			await expect(
				merchantPage.getByText(
					'The cardholder claims this is an unauthorized transaction.',
					{ exact: true }
				)
			).toBeVisible();

			// Visual regression test for the dispute notice.
			await expect(
				merchantPage.locator( '.dispute-notice' )
			).toHaveScreenshot();
		} );
	}
);
