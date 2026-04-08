/**
 * External dependencies
 */
import { test, expect, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { getMerchant, getShopper } from '../../../utils/helpers';
import {
	activateMulticurrency,
	addCurrency,
	deactivateMulticurrency,
	restoreCurrencies,
} from '../../../utils/merchant';
import * as shopper from '../../../utils/shopper';
import {
	goToOrder,
	goToPaymentDetails,
} from '../../../utils/merchant-navigation';
import { goToShop } from '../../../utils/shopper-navigation';

test.describe( 'Admin Multi-Currency Orders', () => {
	let wasMulticurrencyEnabled: boolean;
	let merchantPage: Page;
	let shopperPage: Page;
	let eurOrderId: string;
	let refundAmount: string;

	test.beforeAll( async ( { browser } ) => {
		merchantPage = ( await getMerchant( browser ) ).merchantPage;
		shopperPage = ( await getShopper( browser ) ).shopperPage;

		wasMulticurrencyEnabled = await activateMulticurrency( merchantPage );
		await addCurrency( merchantPage, 'EUR' );

		// Place an order in EUR
		eurOrderId = await shopper.placeOrderWithCurrency( shopperPage, 'EUR' );
	} );

	test.afterAll( async () => {
		await shopper.emptyCart( shopperPage );

		// Reset the shopper's currency preference back to USD to avoid affecting other tests
		await goToShop( shopperPage, { currency: 'USD' } );

		await restoreCurrencies( merchantPage );

		if ( ! wasMulticurrencyEnabled ) {
			await deactivateMulticurrency( merchantPage );
		}
	} );

	test( 'order should display in shopper currency', async () => {
		await goToOrder( merchantPage, eurOrderId );

		// Get prices from order items table and confirm they are in the shopper currency (EUR)
		const orderItemPrices = await merchantPage
			.locator( '#woocommerce-order-items .woocommerce-Price-amount' )
			.all();

		expect( orderItemPrices.length ).toBeGreaterThan( 0 );

		for ( const priceElement of orderItemPrices ) {
			const priceText = await priceElement.textContent();
			expect( priceText ).toContain( '€' );
		}
	} );

	test( 'transaction page shows shopper currency', async () => {
		await goToOrder( merchantPage, eurOrderId );

		// Get the payment intent ID from the order page
		const paymentIntentId = await merchantPage
			.locator( '#order_data' )
			.getByRole( 'link', { name: /pi_/ } )
			.innerText();

		// Navigate to the payment details page
		await goToPaymentDetails( merchantPage, paymentIntentId );

		// Verify that the transaction amount is displayed in the shopper's currency (EUR)
		await expect(
			merchantPage.locator( '.payment-details-summary__amount' )
		).toContainText( '€' );
	} );

	test( 'transaction page shows converted merchant currency', async () => {
		await goToOrder( merchantPage, eurOrderId );

		// Get the payment intent ID from the order page
		const paymentIntentId = await merchantPage
			.locator( '#order_data' )
			.getByRole( 'link', { name: /pi_/ } )
			.innerText();

		// Navigate to the payment details page
		await goToPaymentDetails( merchantPage, paymentIntentId );

		// Confirm that transaction page shows payment details breakdown in merchant currency (USD)
		await expect(
			merchantPage.locator( '.payment-details-summary__breakdown' )
		).toContainText( '$' );

		// Confirm that transaction page shows fee in merchant currency (USD)
		const feesElement = merchantPage.locator(
			'.payment-details-summary__breakdown'
		);
		const feesText = await feesElement.textContent();
		expect( feesText ).toContain( 'Fee' );
		expect( feesText ).toContain( '$' );
	} );

	test( 'can refund in correct currency', async () => {
		await goToOrder( merchantPage, eurOrderId );

		// Click refund button to show the refund UI
		await merchantPage
			.getByRole( 'button', {
				name: 'Refund',
			} )
			.click();

		// Get the total available to refund amount (e.g., "€21.00")
		refundAmount =
			( await merchantPage
				.getByRole( 'row', { name: 'Total available to refund' } )
				.locator( '.woocommerce-Price-amount' )
				.textContent() ) ?? '';

		// Set the refund quantity to 1 for the first line item (full refund of item)
		await merchantPage
			.locator( '.refund_order_item_qty' )
			.first()
			.fill( '1' );

		// Press Tab to trigger the refund amount calculation
		await merchantPage.keyboard.press( 'Tab' );

		// Wait for the refund amount field to be populated (non-empty)
		await expect(
			merchantPage.getByLabel( 'Refund amount' )
		).not.toHaveValue( '' );

		// Fill in refund reason
		await merchantPage
			.getByLabel( 'Reason for refund' )
			.fill( 'Multi-currency refund test' );

		// Find the refund button and verify it shows the EUR amount
		const refundButton = merchantPage.getByRole( 'button', {
			name: `Refund ${ refundAmount } via WooPayments`,
		} );
		await expect( refundButton ).toBeVisible();

		// Click refund and handle confirmation dialog
		merchantPage.once( 'dialog', ( dialog ) => dialog.accept() );
		await refundButton.click();

		// Wait for the refund to finish processing.
		await merchantPage.waitForLoadState( 'load' );

		// Verify refund details show EUR currency with the correct amount
		await expect(
			merchantPage.locator( '.refund .woocommerce-Price-amount' ).first()
		).toContainText( refundAmount );

		// Finding the refund note, and verify it contains the EUR amount
		const refundNote = merchantPage
			.locator( '#woocommerce-order-notes .note_content' )
			.filter( { hasText: 'A refund of' } )
			.filter( { hasText: 'was successfully processed' } );
		await expect( refundNote ).toContainText( refundAmount );

		// Verify order status is Refunded
		await expect( merchantPage.locator( '#order_status' ) ).toHaveValue(
			'wc-refunded'
		);
	} );

	test( 'refund displays correctly on transaction page', async () => {
		await goToOrder( merchantPage, eurOrderId );

		// Get the payment intent ID from the order page
		const paymentIntentId = await merchantPage
			.locator( '#order_data' )
			.getByRole( 'link', { name: /pi_/ } )
			.innerText();

		// Navigate to the payment details page
		await goToPaymentDetails( merchantPage, paymentIntentId );

		// Verify the refund is shown in the timeline with EUR currency
		const refundTimeline = merchantPage.getByText(
			'was successfully refunded'
		);
		await expect( refundTimeline ).toBeVisible();
		await expect( refundTimeline ).toContainText( refundAmount );

		// Verify the payment status changed to Refunded
		await expect(
			merchantPage.getByText( 'Payment status changed to Refunded.' )
		).toBeVisible();
	} );
} );
