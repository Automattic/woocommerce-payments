/**
 * External dependencies
 */
import type { BrowserContext, Page } from '@playwright/test';
import { test, expect, getAuthState } from '../../../fixtures/auth';

/**
 * Internal dependencies
 */
import { config } from '../../../config/default';
import * as merchant from '../../../utils/merchant';
import * as shopper from '../../../utils/shopper';
import { goToCheckoutWCB } from '../../../utils/shopper-navigation';

// Skip: Alipay payments require specific Stripe account configuration that
// is not available in the QIT test environment. The payment method can be
// enabled in settings but checkout fails with "Invalid or missing payment details".
test.describe.skip( 'Alipay Checkout', { tag: '@shopper' }, () => {
	let merchantContext: BrowserContext;
	let merchantPage: Page;
	let shopperContext: BrowserContext;
	let shopperPage: Page;
	let wasMulticurrencyEnabled = false;

	test.beforeAll( async ( { browser } ) => {
		merchantContext = await browser.newContext( {
			storageState: await getAuthState( browser, 'admin' ),
		} );
		merchantPage = await merchantContext.newPage();

		shopperContext = await browser.newContext( {
			storageState: await getAuthState( browser, 'customer' ),
		} );
		shopperPage = await shopperContext.newPage();

		// Alipay may not work correctly with multi-currency enabled
		wasMulticurrencyEnabled = await merchant.isMulticurrencyEnabled(
			merchantPage
		);
		if ( wasMulticurrencyEnabled ) {
			await merchant.deactivateMulticurrency( merchantPage );
		}

		await merchant.enablePaymentMethods( merchantPage, [ 'alipay' ] );
	} );

	test.afterAll( async () => {
		if ( shopperPage ) {
			await shopper.emptyCart( shopperPage );
		}

		if ( merchantPage ) {
			await merchant.disablePaymentMethods( merchantPage, [ 'alipay' ] );
			if ( wasMulticurrencyEnabled ) {
				await merchant.activateMulticurrency( merchantPage );
			}
		}

		await merchantContext?.close();
		await shopperContext?.close();
	} );

	test.beforeEach( async () => {
		await shopper.emptyCart( shopperPage );
	} );

	test(
		'checkout on shortcode checkout page',
		{ tag: '@critical' },
		async () => {
			await shopper.setupProductCheckout(
				shopperPage,
				[ [ config.products.belt, 1 ] ],
				config.addresses.customer.billing
			);

			await shopper.selectPaymentMethod( shopperPage, 'Alipay' );
			await shopper.placeOrder( shopperPage );

			// Verify redirect to Stripe's Alipay test page
			await expect( shopperPage ).toHaveURL( /.*stripe\.com.*alipay/ );

			await shopperPage.getByText( 'Authorize Test Payment' ).click();

			await expect(
				shopperPage.getByRole( 'heading', {
					name: 'Order received',
				} )
			).toBeVisible();
			await expect(
				shopperPage.getByRole( 'img', {
					name: 'Alipay',
				} )
			).toBeVisible();
		}
	);

	test.describe(
		'checkout on block-based checkout page',
		{ tag: [ '@critical', '@blocks' ] },
		() => {
			test( 'completes payment successfully', async () => {
				await shopper.setupProductCheckout(
					shopperPage,
					[ [ config.products.cap, 1 ] ],
					config.addresses.customer.billing
				);
				await goToCheckoutWCB( shopperPage );
				await shopper.fillBillingAddressWCB(
					shopperPage,
					config.addresses.customer.billing
				);

				await shopperPage
					.getByRole( 'radio', {
						name: 'Alipay',
					} )
					.click();

				await shopper.placeOrderWCB( shopperPage, false );

				// Verify redirect to Stripe's Alipay test page
				await expect( shopperPage ).toHaveURL(
					/.*stripe\.com.*alipay/
				);

				await shopperPage.getByText( 'Authorize Test Payment' ).click();

				await expect(
					shopperPage.getByRole( 'heading', {
						name: 'Order received',
					} )
				).toBeVisible();
				await expect(
					shopperPage.getByRole( 'img', {
						name: 'Alipay',
					} )
				).toBeVisible();
			} );
		}
	);
} );
