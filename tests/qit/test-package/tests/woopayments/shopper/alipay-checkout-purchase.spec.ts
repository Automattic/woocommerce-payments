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
import { verifyOrderAndRefund } from '../../../utils/merchant-orders';

const checkoutWithAlipay = async ( page: Page ): Promise< string > => {
	await shopper.setupProductCheckout(
		page,
		[ [ config.products.belt, 1 ] ],
		config.addresses.customer.billing
	);

	await shopper.selectPaymentMethod( page, 'Alipay' );
	await shopper.placeOrder( page );

	await expect( page.getByText( /Alipay test payment page/ ) ).toBeVisible();

	await page.getByText( 'Authorize Test Payment' ).click();

	await expect(
		page.getByRole( 'heading', { name: 'Order received' } )
	).toBeVisible();

	const orderId = page.url().match( /\/order-received\/(\d+)\// )?.[ 1 ];
	if ( ! orderId ) {
		throw new Error(
			`Expected an order-received URL with an order ID, got: ${ page.url() }`
		);
	}
	return orderId;
};

test.describe( 'Alipay Checkout', { tag: '@shopper' }, () => {
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
			await checkoutWithAlipay( shopperPage );

			await expect(
				shopperPage.getByRole( 'img', {
					name: 'Alipay',
				} )
			).toBeVisible();
		}
	);

	test( 'merchant can see and refund an Alipay order', async () => {
		const orderId = await checkoutWithAlipay( shopperPage );

		await verifyOrderAndRefund( merchantPage, orderId );
	} );

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

				const alipayRadio = shopperPage.getByRole( 'radio', {
					name: 'Alipay',
				} );
				await alipayRadio.click();
				await expect( alipayRadio ).toBeChecked();

				await shopper.placeOrderWCB( shopperPage, false );

				await expect(
					shopperPage.getByText( /Alipay test payment page/ )
				).toBeVisible();

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
