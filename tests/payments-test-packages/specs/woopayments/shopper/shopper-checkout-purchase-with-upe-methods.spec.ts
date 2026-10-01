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
						await shopper.addToCartFromShopPage( shopperPage );
						await goToCheckout( shopperPage );
						await shopper.fillBillingAddress(
							shopperPage,
							config.addresses[ 'upe-customer' ].billing.be
						);
						await shopper.expectFraudPreventionToken(
							shopperPage,
							ctpEnabled
						);
						await shopperPage.getByText( 'Bancontact' ).click();

						const bancontactRadio = shopperPage.locator(
							'#payment_method_woocommerce_payments_bancontact'
						);
						await bancontactRadio.scrollIntoViewIfNeeded();
						await bancontactRadio.check( { force: true } );
						await expect( bancontactRadio ).toBeChecked( {
							timeout: 10_000,
						} );

						await shopper.focusPlaceOrderButton( shopperPage );
						await shopper.placeOrder( shopperPage );
						await shopperPage
							.getByRole( 'link', {
								name: 'Authorize Test Payment',
							} )
							.click();
						await expect(
							shopperPage.getByText( 'Order received' ).first()
						).toBeVisible();
					} );
				}
			);
		}
	}
);
