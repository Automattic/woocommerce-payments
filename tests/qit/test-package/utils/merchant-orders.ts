/**
 * External dependencies
 */
import { expect, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { goToOrder, goToPaymentDetails } from './merchant';

interface RefundOptions {
	reason?: string;
}

/**
 * Submits a full refund via the WooPayments refund UI and asserts it succeeded.
 * Assumes the admin page is already on the order edit screen.
 *
 * Drives the line-item quantity rather than typing the total into the price
 * field: WooCommerce then computes the refund amount in the store's own currency
 * format, which avoids locale parsing issues (e.g. EUR "16,00 €" being read as
 * 1600). Assertions stay currency-agnostic for the same reason.
 */
export const submitFullRefund = async (
	page: Page,
	{ reason = 'No longer wanted' }: RefundOptions = {}
): Promise< void > => {
	await page.getByRole( 'button', { name: 'Refund' } ).click();

	// Refund the full ordered quantity of every line item (their `max`).
	const qtyInputs = page.locator( '.refund_order_item_qty' );
	const lineItemCount = await qtyInputs.count();
	for ( let i = 0; i < lineItemCount; i++ ) {
		const max = await qtyInputs.nth( i ).getAttribute( 'max' );
		await qtyInputs.nth( i ).fill( max ?? '1' );
	}
	// Tab triggers WooCommerce's refund-total calculation.
	await page.keyboard.press( 'Tab' );
	await expect( page.getByLabel( 'Refund amount' ) ).not.toHaveValue( '' );

	await page.getByLabel( 'Reason for refund' ).fill( reason );

	const refundButton = page.getByRole( 'button', {
		name: /Refund .+ via WooPayments/,
	} );
	await expect( refundButton ).toBeVisible();

	// The refund triggers a native confirm() dialog. Register the handler per
	// invocation (page.once) so repeated calls on a reused admin page do not
	// accumulate duplicate listeners.
	page.once( 'dialog', ( dialog ) => dialog.accept() );
	await refundButton.click();
	await page.waitForLoadState( 'networkidle' );

	// The refund posts a success order note and flips the order to "Refunded".
	const refundNote = page
		.locator( '#woocommerce-order-notes .note_content' )
		.filter( { hasText: 'A refund of' } )
		.filter( { hasText: 'was successfully processed' } )
		.filter( { hasText: `Reason: ${ reason }` } );
	await expect( refundNote ).toBeVisible();
	await expect( page.locator( '#order_status' ) ).toHaveValue(
		'wc-refunded'
	);
};

/**
 * Verifies a placed order is visible to the merchant and can be fully refunded:
 * opens the order (Orders page), confirms the linked transaction is visible on
 * the WooPayments payment-details page, submits a full refund, then asserts the
 * refunded timeline on the payment-details page.
 *
 * "Transactions" is asserted via the per-transaction payment-details page only,
 * not the (async-synced, flaky) Transactions list.
 */
export const verifyOrderAndRefund = async (
	page: Page,
	orderId: string,
	{ reason = 'No longer wanted' }: RefundOptions = {}
): Promise< void > => {
	// Orders page: order opens and exposes the WooPayments payment intent link.
	await goToOrder( page, orderId );
	const paymentIntentLink = page
		.locator( '#order_data' )
		.getByRole( 'link', { name: /pi_/ } );
	await expect( paymentIntentLink ).toBeVisible();
	const paymentIntentId = await paymentIntentLink.innerText();

	// Transactions: the payment-details page loads and shows the charge amount.
	await goToPaymentDetails( page, paymentIntentId );
	await expect(
		page.locator( '.payment-details-summary__amount' )
	).toBeVisible();

	// Refund from the order, then confirm the refunded timeline on the details page.
	await goToOrder( page, orderId );
	await submitFullRefund( page, { reason } );

	await goToPaymentDetails( page, paymentIntentId );
	await expect(
		page.getByText( /A payment of .+ was successfully refunded\./ )
	).toBeVisible();
	await expect(
		page.getByText( 'Payment status changed to Refunded.' )
	).toBeVisible();
};
