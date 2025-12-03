/**
 * External dependencies
 */
import { test, expect, getAuthState } from '../../../fixtures/auth';
import type { BrowserContext, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { config } from '../../../config/default';
import * as shopper from '../../../utils/shopper';
import * as shopperNavigation from '../../../utils/shopper-navigation';
import * as devtools from '../../../utils/devtools';

// TODO: Card testing protection via WP-CLI doesn't work the same as the Dev Tools plugin.
// The Dev Tools plugin uses filters/hooks (option_wcpay_account_data, woocommerce_payments_account_refreshed)
// that aren't available in the QIT environment. The cardTestingPreventionEnabled: true case needs
// the QIT devtools implementation to be updated to properly simulate the Dev Tools plugin behavior.
const cardTestingPreventionStates = [
	{ cardTestingPreventionEnabled: false },
	// { cardTestingPreventionEnabled: true },
];

test.describe(
	'Shopper > Pay for Order',
	{ tag: [ '@shopper', '@critical' ] },
	() => {
		let merchantContext: BrowserContext;
		let merchantPage: Page;
		let shopperContext: BrowserContext;
		let shopperPage: Page;

		test.beforeAll( async ( { browser } ) => {
			merchantContext = await browser.newContext( {
				storageState: await getAuthState( browser, 'admin' ),
			} );
			merchantPage = await merchantContext.newPage();

			shopperContext = await browser.newContext( {
				storageState: await getAuthState( browser, 'customer' ),
			} );
			shopperPage = await shopperContext.newPage();
		} );

		test.afterAll( async () => {
			await devtools.disableCardTestingProtection();
			await merchantContext?.close();
			await shopperContext?.close();
		} );

		cardTestingPreventionStates.forEach(
			( { cardTestingPreventionEnabled } ) => {
				test( `should be able to pay for a failed order with card testing protection ${ cardTestingPreventionEnabled }`, async () => {
					if ( cardTestingPreventionEnabled ) {
						await devtools.enableCardTestingProtection();
					} else {
						await devtools.disableCardTestingProtection();
					}

					await shopper.addToCartFromShopPage( shopperPage );
					await shopper.setupCheckout( shopperPage );
					await shopper.selectPaymentMethod( shopperPage );
					await shopper.fillCardDetails(
						shopperPage,
						config.cards.declined
					);
					await shopper.placeOrder( shopperPage );

					// Error message can vary between "Your card was declined" and "Your payment was not processed"
					await expect(
						shopperPage
							.getByText(
								/Your card was declined|Your payment was not processed/
							)
							.first()
					).toBeVisible();

					await shopperNavigation.goToOrders( shopperPage );
					const payForOrderButton = shopperPage
						.locator( '.woocommerce-button.button.pay', {
							hasText: 'Pay',
						} )
						.first();
					await payForOrderButton.click();

					await expect(
						shopperPage.getByRole( 'heading', {
							name: 'Pay for order',
						} )
					).toBeVisible();
					await shopper.fillCardDetails(
						shopperPage,
						config.cards.basic
					);

					const token = await shopperPage.evaluate( () => {
						return ( window as any ).wcpayFraudPreventionToken;
					} );

					if ( cardTestingPreventionEnabled ) {
						expect( token ).not.toBeUndefined();
					} else {
						expect( token ).toBeUndefined();
					}

					await shopper.placeOrder( shopperPage );

					await expect(
						shopperPage.getByText( 'Order received' ).first()
					).toBeVisible();
				} );
			}
		);
	}
);
