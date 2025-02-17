/**
 * External dependencies
 */
import test, { Page, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import {
	activateMulticurrency,
	addCurrency,
	deactivateMulticurrency,
	disablePaymentMethods,
	enablePaymentMethods,
	restoreCurrencies,
} from '../../../utils/merchant';
import { getShopper, getMerchant } from '../../../utils/helpers';
import {
	disableCardTestingProtection,
	enableCardTestingProtection,
} from '../../../utils/devtools';
import {
	addToCartFromShopPage,
	changeAccountCurrency,
	emptyCart,
	expectFraudPreventionToken,
	fillBillingAddress,
	focusPlaceOrderButton,
	placeOrder,
} from '../../../utils/shopper';
import { config } from '../../../config/default';
import { goToCheckout } from '../../../utils/shopper-navigation';

test.describe(
	'Enable UPE with deferred intent creation',
	{ tag: '@critical' },
	() => {
		let wasMultiCurrencyEnabled = false;
		let merchantPage: Page, shopperPage: Page;
		test.beforeAll( async ( { browser } ) => {
			// Open browsers.
			merchantPage = ( await getMerchant( browser ) ).merchantPage;
			shopperPage = ( await getShopper( browser ) ).shopperPage;

			// Prepare merchant side of tests.
			wasMultiCurrencyEnabled = await activateMulticurrency(
				merchantPage
			);
			await addCurrency( merchantPage, 'EUR' );
			await enablePaymentMethods( merchantPage, [ 'bancontact' ] );
			// Prepare shopper side of tests.
			await changeAccountCurrency(
				shopperPage,
				config.addresses.customer.billing,
				'EUR'
			);
			await emptyCart( shopperPage );
		} );

		test.afterAll( async () => {
			// Restore shopper side of tests.
			await emptyCart( shopperPage );

			// Restore merchant side of tests
			await disablePaymentMethods( merchantPage, [ 'bancontact' ] );
			await restoreCurrencies( merchantPage );
			if ( ! wasMultiCurrencyEnabled ) {
				await deactivateMulticurrency( merchantPage );
			}
		} );

		[ false, true ].forEach( ( ctpEnabled ) => {
			test.describe(
				`Card testing protection enabled: ${ ctpEnabled }`,
				() => {
					test.beforeAll( async () => {
						if ( ctpEnabled ) {
							await enableCardTestingProtection( merchantPage );
						}
					} );
					test.afterAll( async () => {
						if ( ctpEnabled ) {
							await disableCardTestingProtection( merchantPage );
						}
					} );
					test( 'should successfully place order with Bancontact', async () => {
						await addToCartFromShopPage( shopperPage );
						await goToCheckout( shopperPage );
						await fillBillingAddress(
							shopperPage,
							config.addresses[ 'upe-customer' ].billing.be
						);
						await expectFraudPreventionToken(
							shopperPage,
							ctpEnabled
						);
						await shopperPage.getByText( 'Bancontact' ).click();
						await focusPlaceOrderButton( shopperPage );
						await placeOrder( shopperPage );
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
		} );
	}
);
