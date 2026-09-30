/**
 * External dependencies
 */
import { expect, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { goToOrder, goToPaymentDetails } from './merchant-navigation';

interface RefundOptions {
	reason?: string;
}

/**
 * Submits a full refund via the WooPayments refund UI and asserts it succeeded.
 * Assumes the merchant page is already on the order edit screen.
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
	// invocation (page.once) so repeated calls on a reused merchant page do not
	// accumulate duplicate listeners.
	page.once( 'dialog', ( dialog ) => dialog.accept() );
	await refundButton.click();
	await page.waitForLoadState( 'load' );

	// The refund posts an order note and flips the order to "Refunded". The note
	// wording depends on how the method settles — card refunds synchronously
	// ("was successfully processed"), while methods like Bancontact/Alipay/BNPL
	// settle asynchronously ("is pending") — so match on the common substrings.
	const refundNote = page
		.locator( '#woocommerce-order-notes .note_content' )
		.filter( { hasText: 'A refund of' } )
		.filter( { hasText: 'using WooPayments' } )
		.filter( { hasText: `Reason: ${ reason }` } );
	await expect( refundNote ).toBeVisible();
	await expect( page.locator( '#order_status' ) ).toHaveValue(
		'wc-refunded'
	);
};

/**
 * Verifies a placed order is visible to the merchant and can be fully refunded:
 * opens the order (Orders page), fully refunds it there (asserting the refund
 * note and Refunded status via submitFullRefund), then confirms the order's
 * transaction is visible on the WooPayments payment-details page.
 *
 * "Transactions" is asserted via the per-transaction payment-details page only,
 * not the (async-synced, flaky) Transactions list. The refund timeline on that
 * page is not asserted because async methods settle after the test runs.
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

	// Refund on this freshly-loaded order page, before navigating anywhere else:
	// loading another page and returning invalidates the order's refund nonce,
	// which makes the refund request fail.
	await submitFullRefund( page, { reason } );

	// Transactions: the order's transaction is visible on the payment-details page.
	// The refund itself is already verified on the order (note + Refunded status);
	// the refund timeline here is skipped because async methods settle later.
	await goToPaymentDetails( page, paymentIntentId );
	await expect(
		page.locator( '.payment-details-summary__amount' )
	).toBeVisible();
};
