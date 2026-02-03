/**
 * Internal dependencies
 */
import { test, expect } from '../../../fixtures/auth';
import {
	activateMulticurrency,
	addCurrency,
	deactivateMulticurrency,
	restoreCurrencies,
	goToOrder,
	goToPaymentDetailsForOrder,
} from '../../../utils/merchant';
import { placeOrderWithCurrency } from '../../../utils/shopper';
import { goToShop } from '../../../utils/shopper-navigation';

test.describe(
	'Admin Multi-Currency Orders',
	{ tag: '@merchant' },
	() => {
		let wasMulticurrencyEnabled: boolean;
		let eurOrderId: string;
		let refundAmount: string;

		test.beforeAll( async ( { adminPage, customerPage } ) => {
			wasMulticurrencyEnabled = await activateMulticurrency(
				adminPage
			);
			await addCurrency( adminPage, 'EUR' );

			// Place an order in EUR
			eurOrderId = await placeOrderWithCurrency(
				customerPage,
				'EUR'
			);
		} );

		test.afterAll( async ( { adminPage, customerPage } ) => {
			// Reset the shopper's currency preference back to USD
			await goToShop( customerPage, { currency: 'USD' } );

			await restoreCurrencies( adminPage );

			if ( ! wasMulticurrencyEnabled ) {
				await deactivateMulticurrency( adminPage );
			}
		} );

		test( 'order should display in shopper currency', async ( {
			adminPage,
		} ) => {
			await goToOrder( adminPage, eurOrderId );

			// Get prices from order items table and confirm they are in EUR
			const orderItemPrices = await adminPage
				.locator(
					'#woocommerce-order-items .woocommerce-Price-amount'
				)
				.all();

			expect( orderItemPrices.length ).toBeGreaterThan( 0 );

			for ( const priceElement of orderItemPrices ) {
				const priceText = await priceElement.textContent();
				expect( priceText ).toContain( '€' );
			}
		} );

		test( 'transaction page shows shopper currency', async ( {
			adminPage,
		} ) => {
			await goToPaymentDetailsForOrder( adminPage, eurOrderId );

			// Verify that the transaction amount is displayed in EUR
			await expect(
				adminPage.locator(
					'.payment-details-summary__amount'
				)
			).toContainText( '€' );
		} );

		test( 'transaction page shows converted merchant currency', async ( {
			adminPage,
		} ) => {
			await goToPaymentDetailsForOrder( adminPage, eurOrderId );

			// Confirm that the fee breakdown shows USD (merchant's deposit currency)
			const breakdownElement = adminPage.locator(
				'.payment-details-summary__breakdown'
			);

			await expect( breakdownElement ).toContainText( '$' );

			const feesText = await breakdownElement.textContent();
			expect( feesText ).toContain( 'Fee' );
			expect( feesText ).toContain( '$' );
		} );

		test( 'can refund in correct currency', async ( { adminPage } ) => {
			await goToOrder( adminPage, eurOrderId );

			// Click refund button to show the refund UI
			await adminPage
				.getByRole( 'button', { name: 'Refund' } )
				.click();

			// Get the total available to refund amount
			refundAmount =
				( await adminPage
					.getByRole( 'row', {
						name: 'Total available to refund',
					} )
					.locator( '.woocommerce-Price-amount' )
					.textContent() ) ?? '';

			// Set the refund quantity to 1 for the first line item
			await adminPage
				.locator( '.refund_order_item_qty' )
				.first()
				.fill( '1' );

			// Press Tab to trigger the refund amount calculation
			await adminPage.keyboard.press( 'Tab' );

			// Wait for the refund amount field to be populated
			await expect(
				adminPage.getByLabel( 'Refund amount' )
			).not.toHaveValue( '' );

			// Fill in refund reason
			await adminPage
				.getByLabel( 'Reason for refund' )
				.fill( 'Multi-currency refund test' );

			// Find the refund button and verify it shows the EUR amount
			const refundButton = adminPage.getByRole( 'button', {
				name: `Refund ${ refundAmount } via WooPayments`,
			} );
			await expect( refundButton ).toBeVisible();

			// Click refund and handle confirmation dialog
			adminPage.once( 'dialog', ( dialog ) => dialog.accept() );
			await refundButton.click();

			// Wait for refund to process
			await adminPage.waitForLoadState( 'networkidle' );

			// Verify refund details show EUR currency with the correct amount
			await expect(
				adminPage
					.locator( '.refund .woocommerce-Price-amount' )
					.first()
			).toContainText( refundAmount );

			// Verify the refund note contains the EUR amount
			const refundNote = adminPage
				.locator( '#woocommerce-order-notes .note_content' )
				.filter( { hasText: 'A refund of' } )
				.filter( { hasText: 'was successfully processed' } );
			await expect( refundNote ).toContainText( refundAmount );

			// Verify order status is Refunded
			await expect(
				adminPage.locator( '#order_status' )
			).toHaveValue( 'wc-refunded' );
		} );

		test( 'refund displays correctly on transaction page', async ( {
			adminPage,
		} ) => {
			await goToPaymentDetailsForOrder( adminPage, eurOrderId );

			// Verify the refund is shown in the timeline with EUR currency
			const refundTimeline = adminPage.getByText(
				'was successfully refunded'
			);
			await expect( refundTimeline ).toBeVisible();
			await expect( refundTimeline ).toContainText( refundAmount );

			// Verify the payment status changed to Refunded
			await expect(
				adminPage.getByText(
					'Payment status changed to Refunded.'
				)
			).toBeVisible();
		} );
	}
);
