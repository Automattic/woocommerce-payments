/**
 * Internal dependencies
 */
import { test, expect } from '../../../fixtures/auth';
import { config } from '../../../config/default';
import { goToOrder } from '../../../utils/merchant';
import {
	addToCartFromShopPage,
	fillBillingAddress,
	fillCardDetails,
	placeOrder,
} from '../../../utils/shopper';
import { goToCheckout } from '../../../utils/shopper-navigation';

test.describe(
	'Disputes > View dispute details via disputed order notice',
	{ tag: '@merchant' },
	() => {
		let orderId: string;

		test.beforeEach( async ( { customerPage } ) => {
			// Place an order to dispute later
			await addToCartFromShopPage( customerPage );

			await goToCheckout( customerPage );
			await fillBillingAddress(
				customerPage,
				config.addresses.customer.billing
			);
			await fillCardDetails(
				customerPage,
				config.cards[ 'disputed-fraudulent' ]
			);
			await placeOrder( customerPage );

			// Get the order ID
			const orderIdField = customerPage.locator(
				'.woocommerce-order-overview__order.order > strong'
			);
			orderId = await orderIdField.innerText();
		} );

		test( 'should navigate to dispute details when disputed order notice button clicked', async ( {
			adminPage,
		} ) => {
			await goToOrder( adminPage, orderId );

			// If WC < 7.9, return early since the order dispute notice is not present.
			const orderPaymentDetailsContainerVisible = await adminPage
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
			await adminPage
				.getByRole( 'button', {
					name: 'Respond now',
				} )
				.click();

			// Verify we see the dispute details on the transaction details page.
			await expect(
				adminPage.getByText(
					'The cardholder claims this is an unauthorized transaction.',
					{ exact: true }
				)
			).toBeVisible();

			// Visual regression test for the dispute notice.
			// TODO: This visual regression test is not flaky, but we should revisit the approach.
			// await expect(
			// 	adminPage.locator( '.dispute-notice' )
			// ).toHaveScreenshot();
		} );
	}
);
