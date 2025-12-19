/**
 * Internal dependencies
 */
import { test, expect } from '../../../fixtures/auth';
import { goToOrder, goToPaymentDetails } from '../../../utils/merchant';
import { placeOrderWithCurrency } from '../../../utils/shopper';

test.describe(
	'WooCommerce Payments - Full Refund',
	{ tag: '@merchant' },
	() => {
		let orderId: string;
		let orderAmount: string;
		let paymentIntentId: string;

		test(
			'should process a full refund for an order',
			{ tag: '@critical' },
			async ( { adminPage, customerPage } ) => {
				// Place an order to refund later and get the order ID so we can open it in the merchant view
				orderId = await placeOrderWithCurrency( customerPage, 'USD' );

				// Get the order total so we can verify the refund amount
				orderAmount = await customerPage
					.locator(
						'.woocommerce-order-overview__total .woocommerce-Price-amount'
					)
					.textContent();

				// Open the order
				await goToOrder( adminPage, orderId );

				// Click refund button
				await adminPage
					.getByRole( 'button', {
						name: 'Refund',
					} )
					.click();

				// Fill refund details
				await adminPage
					.getByLabel( 'Refund amount' )
					.fill( orderAmount );
				await adminPage
					.getByLabel( 'Reason for refund' )
					.fill( 'No longer wanted' );

				const refundButton = await adminPage.getByRole( 'button', {
					name: `Refund ${ orderAmount } via WooPayments`,
				} );

				await expect( refundButton ).toBeVisible();

				// Click refund and handle confirmation dialog
				adminPage.on( 'dialog', ( dialog ) => dialog.accept() );
				await refundButton.click();

				// Wait for refund to process
				await adminPage.waitForLoadState( 'networkidle' );

				// Verify refund details
				await expect(
					adminPage.getByRole( 'cell', {
						name: `-${ orderAmount }`,
					} )
				).toHaveCount( 2 );
				await expect(
					adminPage.getByText(
						`A refund of ${ orderAmount } was successfully processed using WooPayments. Reason: No longer wanted`
					)
				).toBeVisible();

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
		} );
	}
);
