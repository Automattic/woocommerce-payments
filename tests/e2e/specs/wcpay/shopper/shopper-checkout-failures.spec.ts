/**
 * External dependencies
 */
import { test, expect, Page, Browser } from '@playwright/test';

/**
 * Internal dependencies
 */

import { config } from '../../../config/default';
import * as shopper from '../../../utils/shopper';
import { getMerchant, getShopper } from '../../../utils/helpers';

test.describe(
	'Shopper > Checkout > Failures with various cards',
	{ tag: '@critical' },
	() => {
		let shopperPage: Page;
		let browser: Browser;

		const waitForBanner = async ( errorText: string ) => {
			await expect( shopperPage.getByText( errorText ) ).toBeVisible();
		};

		test.beforeAll( async ( { browser: b } ) => {
			browser = b;
			shopperPage = ( await getShopper( browser ) ).shopperPage;

			// Add a product to cart once — failure tests never complete
			// the order so the cart persists across all tests.
			await shopper.addToCartFromShopPage( shopperPage );
		} );

		test.beforeEach( async () => {
			// Navigate to checkout and select payment method. Billing fields
			// are remembered by WooCommerce for the logged-in customer after
			// the first setupCheckout call fills them.
			await shopper.setupCheckout( shopperPage );
			await shopper.selectPaymentMethod( shopperPage );
		} );

		// Reload after each test to prevent state leaking between tests.
		test.afterEach( async () => {
			await shopperPage.reload();
		} );

		test( 'should throw an error that the card was simply declined', async () => {
			await shopper.fillCardDetails( shopperPage, config.cards.declined );
			await shopper.placeOrder( shopperPage );

			await waitForBanner( 'Error: Your card was declined.' );

			// Verify the failed order has a note about the decline in WC admin.
			const { merchantPage, merchantContext } = await getMerchant(
				browser
			);
			try {
				await merchantPage.goto(
					'/wp-admin/admin.php?page=wc-orders&status=wc-failed',
					{ waitUntil: 'load' }
				);
				// Click the most recent failed order link.
				await merchantPage
					.locator( '.wp-list-table tbody tr' )
					.first()
					.locator( 'a.order-view' )
					.click();
				await merchantPage.waitForLoadState( 'load' );
				await expect(
					merchantPage
						.locator( '#woocommerce-order-notes .note_content' )
						.first()
				).toContainText( /declined/i );
			} finally {
				await merchantContext.close();
			}
		} );

		test( 'should throw an error that the card expiration date is in the past', async () => {
			await shopper.fillCardDetails(
				shopperPage,
				config.cards[ 'declined-expired' ]
			);
			await shopper.placeOrder( shopperPage );

			await waitForBanner( 'Error: Your card has expired.' );
		} );

		test( 'should throw an error that the card CVV number is invalid', async () => {
			await shopper.fillCardDetails(
				shopperPage,
				config.cards[ 'invalid-cvv-number' ]
			);

			await shopperPage.keyboard.press( 'Tab' );

			const frameHandle = await shopperPage.waitForSelector(
				'#payment .payment_method_woocommerce_payments .wcpay-upe-element iframe'
			);

			const stripeFrame = await frameHandle.contentFrame();
			const cvcErrorText = await stripeFrame.locator(
				'p#Field-cvcError'
			);

			await expect( cvcErrorText ).toHaveText(
				'Your card\u2019s security code is incomplete.'
			);
		} );

		test( 'should throw an error that the card was declined due to insufficient funds', async () => {
			await shopper.fillCardDetails(
				shopperPage,
				config.cards[ 'declined-funds' ]
			);
			await shopper.placeOrder( shopperPage );

			await waitForBanner( 'Error: Your card has insufficient funds.' );
		} );

		test( 'should throw an error that the card was declined due to expired card', async () => {
			await shopper.fillCardDetails(
				shopperPage,
				config.cards[ 'declined-expired' ]
			);
			await shopper.placeOrder( shopperPage );

			await waitForBanner( 'Error: Your card has expired.' );
		} );

		test( 'should throw an error that the card was declined due to incorrect CVC number', async () => {
			await shopper.fillCardDetails(
				shopperPage,
				config.cards[ 'declined-cvc' ]
			);
			await shopper.placeOrder( shopperPage );

			await waitForBanner(
				"Error: Your card's security code is incorrect."
			);
		} );

		test( 'should throw an error that the card was declined due to processing error', async () => {
			await shopper.fillCardDetails(
				shopperPage,
				config.cards[ 'declined-processing' ]
			);
			await shopper.placeOrder( shopperPage );

			await waitForBanner(
				'Error: An error occurred while processing your card. Try again in a little bit.'
			);
		} );

		test( 'should throw an error that the card was declined due to incorrect card number', async () => {
			await shopper.fillCardDetails(
				shopperPage,
				config.cards[ 'declined-incorrect' ]
			);

			const frameHandle = await shopperPage.waitForSelector(
				'#payment .payment_method_woocommerce_payments .wcpay-upe-element iframe'
			);

			const stripeFrame = await frameHandle.contentFrame();
			const numberErrorText = await stripeFrame.locator(
				'p#Field-numberError'
			);

			expect( numberErrorText ).toHaveText(
				'Your card number is invalid.'
			);
		} );

		test( 'should throw an error that the card was declined due to invalid 3DS card', async () => {
			await shopper.fillCardDetails(
				shopperPage,
				config.cards[ 'declined-3ds' ]
			);
			await shopper.placeOrder( shopperPage );

			await shopper.confirmCardAuthentication( shopperPage, false );

			await waitForBanner(
				'We are unable to authenticate your payment method. Please choose a different payment method and try again.'
			);
		} );
	}
);

test.describe(
	'Shopper > Checkout > Retry after failure without page refresh',
	{ tag: '@critical' },
	() => {
		let shopperPage: Page;
		let browser: Browser;

		test.beforeAll( async ( { browser: b } ) => {
			browser = b;
			shopperPage = ( await getShopper( browser ) ).shopperPage;
			await shopper.emptyCart( shopperPage );
			await shopper.addToCartFromShopPage( shopperPage );
		} );

		test( 'should successfully complete order after retrying with a valid card without refreshing the page', async () => {
			await shopper.setupCheckout( shopperPage );
			await shopper.selectPaymentMethod( shopperPage );

			// First attempt: declined card.
			await shopper.fillCardDetails( shopperPage, config.cards.declined );
			await shopper.placeOrder( shopperPage );
			await expect(
				shopperPage.getByText( 'Error: Your card was declined.' )
			).toBeVisible();

			// Second attempt: valid card, same page, no refresh.
			// Regression scenario from issue #160 / PR #133.
			await shopper.fillCardDetails( shopperPage, config.cards.basic );
			await shopper.placeOrder( shopperPage );
			await expect(
				shopperPage.getByText( 'Order received' ).first()
			).toBeVisible();
		} );
	}
);
