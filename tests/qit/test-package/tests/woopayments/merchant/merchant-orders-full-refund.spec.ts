/**
 * Internal dependencies
 */
import { test, expect } from '../../../fixtures/auth';
import {
	activateMulticurrency,
	deactivateMulticurrency,
	goToOrder,
	goToPaymentDetails,
	isMulticurrencyEnabled,
} from '../../../utils/merchant';
import { submitFullRefund } from '../../../utils/merchant-orders';
import { placeOrderWithCurrency } from '../../../utils/shopper';

test.describe(
	'WooCommerce Payments - Full Refund',
	{ tag: '@merchant' },
	() => {
		let orderId: string;
		let orderAmount: string;
		let paymentIntentId: string;
		let wasMulticurrencyEnabled = false;

		test.beforeAll( async ( { adminPage } ) => {
			// The timeline amount carries a currency code when multi-currency is on.
			wasMulticurrencyEnabled = await isMulticurrencyEnabled( adminPage );
			if ( wasMulticurrencyEnabled ) {
				await deactivateMulticurrency( adminPage );
			}
		} );

		test.afterAll( async ( { adminPage } ) => {
			if ( wasMulticurrencyEnabled ) {
				await activateMulticurrency( adminPage );
			}
		} );

		test(
			'should process a full refund for an order',
			{ tag: '@critical' },
			async ( { adminPage, customerPage } ) => {
				// Place an order to refund later and get the order ID so we can open it in the merchant view
				orderId = await placeOrderWithCurrency( customerPage, 'USD' );

				// Get the order total so we can verify the refund amount
				orderAmount =
					( await customerPage
						.locator(
							'.woocommerce-order-overview__total .woocommerce-Price-amount'
						)
						.textContent() ) ?? '';
				expect( orderAmount ).not.toBe( '' );

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

			// Verify timeline events
			await expect(
				adminPage.getByText(
					`A payment of ${ orderAmount } was successfully refunded.`
				)
			).toBeVisible();

			await expect(
				adminPage.getByText( 'Payment status changed to Refunded.' )
			).toBeVisible();

			await expect(
				adminPage.getByText( 'Reason: No longer wanted' )
			).toBeVisible();
		} );
	}
);
