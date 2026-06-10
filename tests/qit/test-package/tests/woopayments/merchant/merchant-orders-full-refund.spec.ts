/**
 * Internal dependencies
 */
import { test, expect } from '../../../fixtures/auth';
import { goToOrder, goToPaymentDetails } from '../../../utils/merchant';
import { submitFullRefund } from '../../../utils/merchant-orders';
import { placeOrderWithCurrency } from '../../../utils/shopper';

test.describe(
	'WooCommerce Payments - Full Refund',
	{ tag: '@merchant' },
	() => {
		let orderId: string;
		let paymentIntentId: string;

		test(
			'should process a full refund for an order',
			{ tag: '@critical' },
			async ( { adminPage, customerPage } ) => {
				// Place an order to refund later and get the order ID so we can open it in the merchant view
				orderId = await placeOrderWithCurrency( customerPage, 'USD' );

				// Open the order
				await goToOrder( adminPage, orderId );

				await submitFullRefund( adminPage );

				// Get and store the payment intent ID for the next test
				paymentIntentId = await adminPage
					.locator( '#order_data' )
					.getByRole( 'link', {
						name: /pi_/,
					} )
					.innerText();
			}
		);

		test( 'should be able to view a refunded transaction', async ( {
			adminPage,
		} ) => {
			// Navigate to payment details using the payment intent ID from the previous test
			await goToPaymentDetails( adminPage, paymentIntentId );

			// Verify timeline events - use regex to match optional currency code suffix
			await expect(
				adminPage.getByText(
					new RegExp(
						`A payment of \\$\\d+\\.\\d{2}(?: USD)? was successfully refunded\\.`
					)
				)
			).toBeVisible();

			await expect(
				adminPage.getByText( 'Payment status changed to Refunded.' )
			).toBeVisible();
		} );
	}
);
