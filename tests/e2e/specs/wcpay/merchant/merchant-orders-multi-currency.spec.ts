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

test.describe( 'Admin Multi-Currency Orders', () => {
	let wasMulticurrencyEnabled: boolean;
	let merchantPage: Page;
	let shopperPage: Page;
	let eurOrderId: string;
	let orderAmount: string;

	test.beforeAll( async ( { browser } ) => {
		merchantPage = ( await getMerchant( browser ) ).merchantPage;
		shopperPage = ( await getShopper( browser ) ).shopperPage;

		wasMulticurrencyEnabled = await activateMulticurrency( merchantPage );
		await addCurrency( merchantPage, 'EUR' );

		// Place an order in EUR
		eurOrderId = await shopper.placeOrderWithCurrency( shopperPage, 'EUR' );

		// Get the order total for refund tests
		orderAmount =
			( await shopperPage
				.locator(
					'.woocommerce-order-overview__total .woocommerce-Price-amount'
				)
				.textContent() ) ?? '';
	} );

	test.afterAll( async () => {
		await restoreCurrencies( merchantPage );
		await shopper.emptyCart( shopperPage );

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

		// Click refund button
		await merchantPage
			.getByRole( 'button', {
				name: 'Refund',
			} )
			.click();

		// Fill refund details with the EUR amount
		await merchantPage.getByLabel( 'Refund amount' ).fill( orderAmount );
		await merchantPage
			.getByLabel( 'Reason for refund' )
			.fill( 'Multi-currency refund test' );

		// Verify the refund button shows the correct currency (EUR)
		const refundButton = merchantPage.getByRole( 'button', {
			name: `Refund ${ orderAmount } via WooPayments`,
		} );
		await expect( refundButton ).toBeVisible();
		await expect( refundButton ).toContainText( '€' );

		// Click refund and handle confirmation dialog
		merchantPage.once( 'dialog', ( dialog ) => dialog.accept() );
		await refundButton.click();

		// Wait for refund to process
		await merchantPage.waitForLoadState( 'networkidle' );

		// Verify refund details show EUR currency
		await expect(
			merchantPage.getByRole( 'cell', {
				name: new RegExp( `-${ orderAmount }` ),
			} )
		).toBeVisible();

		// Verify refund note contains the EUR amount
		await expect(
			merchantPage.getByText(
				new RegExp(
					`A refund of ${ orderAmount } was successfully processed using WooPayments`
				)
			)
		).toBeVisible();
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
		await expect(
			merchantPage.getByText(
				new RegExp(
					`A payment of ${ orderAmount } was successfully refunded`
				)
			)
		).toBeVisible();

		// Verify the payment status changed to Refunded
		await expect(
			merchantPage.getByText( 'Payment status changed to Refunded.' )
		).toBeVisible();
	} );
} );
