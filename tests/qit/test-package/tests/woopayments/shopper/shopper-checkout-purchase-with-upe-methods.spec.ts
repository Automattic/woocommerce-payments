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
import * as devtools from '../../../utils/devtools';
import { goToCheckout } from '../../../utils/shopper-navigation';
import { verifyOrderAndRefund } from '../../../utils/merchant-orders';

const checkoutWithBancontact = async (
	page: Page,
	ctpEnabled: boolean
): Promise< string > => {
	await shopper.addToCartFromShopPage( page );
	await goToCheckout( page );
	await shopper.fillBillingAddress(
		page,
		config.addresses[ 'upe-customer' ].billing.be
	);
	await shopper.expectFraudPreventionToken( page, ctpEnabled );
	await page.getByText( 'Bancontact' ).click();

	const bancontactRadio = page.locator(
		'#payment_method_woocommerce_payments_bancontact'
	);
	await bancontactRadio.scrollIntoViewIfNeeded();
	await bancontactRadio.check( { force: true } );
	await expect( bancontactRadio ).toBeChecked( { timeout: 10_000 } );

	await shopper.focusPlaceOrderButton( page );
	await shopper.placeOrder( page );
	await page
		.getByRole( 'link', { name: 'Authorize Test Payment' } )
		.click();
	await expect( page.getByText( 'Order received' ).first() ).toBeVisible();

	const orderId = page.url().match( /\/order-received\/(\d+)\// )?.[ 1 ];
	if ( ! orderId ) {
		throw new Error(
			`Expected an order-received URL with an order ID, got: ${ page.url() }`
		);
	}
	return orderId;
};

test.describe(
	'Local payment method checkout with card testing',
	{ tag: [ '@shopper', '@critical' ] },
	() => {
		test.describe.configure( { timeout: 120_000 } );

		let merchantContext: BrowserContext;
		let shopperContext: BrowserContext;
		let merchantPage: Page;
		let shopperPage: Page;
		let wasMultiCurrencyEnabled = false;
		let enabledCurrenciesSnapshot: string[] = [];

		test.beforeAll( async ( { browser } ) => {
			merchantContext = await browser.newContext( {
				storageState: await getAuthState( browser, 'admin' ),
			} );
			merchantPage = await merchantContext.newPage();

			shopperContext = await browser.newContext( {
				storageState: await getAuthState( browser, 'customer' ),
			} );
			shopperPage = await shopperContext.newPage();

			wasMultiCurrencyEnabled = await merchant.activateMulticurrency(
				merchantPage
			);
			enabledCurrenciesSnapshot = await merchant.getEnabledCurrenciesSnapshot(
				merchantPage
			);
			await merchant.addCurrency( merchantPage, 'EUR' );
			await merchant.enablePaymentMethods( merchantPage, [
				'bancontact',
			] );

			await shopper.changeAccountCurrency(
				shopperPage,
				config.addresses.customer.billing,
				'EUR'
			);
			await shopper.emptyCart( shopperPage );
		} );

		test.afterAll( async () => {
			await shopper.emptyCart( shopperPage );
			await shopper.changeAccountCurrency(
				shopperPage,
				config.addresses.customer.billing,
				'USD'
			);
			await merchant.disablePaymentMethods( merchantPage, [
				'bancontact',
			] );
			if ( enabledCurrenciesSnapshot.length ) {
				await merchant.restoreCurrencies(
					merchantPage,
					enabledCurrenciesSnapshot
				);
			}
			if ( ! wasMultiCurrencyEnabled ) {
				await merchant.deactivateMulticurrency( merchantPage );
			}
			await merchantContext?.close();
			await shopperContext?.close();
		} );

		for ( const ctpEnabled of [ false, true ] ) {
			test.describe(
				`Card testing protection enabled: ${ ctpEnabled }`,
				() => {
					test.beforeAll( async () => {
						if ( ctpEnabled ) {
							await devtools.enableCardTestingProtection();
						}
					} );

					test.afterAll( async () => {
						if ( ctpEnabled ) {
							await devtools.disableCardTestingProtection();
						}
					} );

					test.beforeEach( async () => {
						await shopper.emptyCart( shopperPage );
					} );

					test( 'should successfully place order with Bancontact', async () => {
						await checkoutWithBancontact( shopperPage, ctpEnabled );
					} );
				}
			);
		}

		test( 'merchant can see and refund a Bancontact order', async () => {
			const orderId = await checkoutWithBancontact( shopperPage, false );

			await verifyOrderAndRefund( merchantPage, orderId );
		} );
	}
);
