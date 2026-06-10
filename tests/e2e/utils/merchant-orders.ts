/**
 * External dependencies
 */
import { expect, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { goToOrder, goToPaymentDetails } from './merchant-navigation';

interface RefundOptions {
	orderAmount: string;
	reason?: string;
}

/**
 * Submits a full refund via the WooPayments refund UI and asserts the success
 * notice. Assumes the merchant page is already on the order edit screen.
 */
export const submitFullRefund = async (
	page: Page,
	{ orderAmount, reason = 'No longer wanted' }: RefundOptions
): Promise< void > => {
	await page.getByRole( 'button', { name: 'Refund' } ).click();
	await page.getByLabel( 'Refund amount' ).fill( orderAmount );
	await page.getByLabel( 'Reason for refund' ).fill( reason );

	const refundButton = page.getByRole( 'button', {
		name: `Refund ${ orderAmount } via WooPayments`,
	} );
	await expect( refundButton ).toBeVisible();

	// The refund triggers a native confirm() dialog. Register the handler per
	// invocation (page.once) so repeated calls on a reused merchant page do not
	// accumulate duplicate listeners.
	page.once( 'dialog', ( dialog ) => dialog.accept() );
	await refundButton.click();
	await page.waitForLoadState( 'load' );

	await expect(
		page.getByRole( 'cell', { name: `-${ orderAmount }` } )
	).toHaveCount( 2 );
	await expect(
		page.getByText(
			`A refund of ${ orderAmount } was successfully processed using WooPayments. Reason: ${ reason }`
		)
	).toBeVisible();
};

interface VerifyOptions {
	orderAmount: string;
	reason?: string;
}

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
	{ orderAmount, reason = 'No longer wanted' }: VerifyOptions
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
	await submitFullRefund( page, { orderAmount, reason } );

	await goToPaymentDetails( page, paymentIntentId );
	await expect(
		page.getByText(
			`A payment of ${ orderAmount } was successfully refunded.`
		)
	).toBeVisible();
	await expect(
		page.getByText( 'Payment status changed to Refunded.' )
	).toBeVisible();
	await expect( page.getByText( `Reason: ${ reason }` ) ).toBeVisible();
};
