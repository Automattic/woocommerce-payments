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
	selectPaymentMethod,
} from '../../../utils/shopper';
import { config } from '../../../config/default';
import { goToCheckout } from '../../../utils/shopper-navigation';
import { verifyOrderAndRefund } from '../../../utils/merchant-orders';

const checkoutWithBancontact = async (
	page: Page,
	ctpEnabled: boolean
): Promise< string > => {
	await addToCartFromShopPage( page );
	await goToCheckout( page );
	await fillBillingAddress(
		page,
		config.addresses[ 'upe-customer' ].billing.be
	);
	await expectFraudPreventionToken( page, ctpEnabled );
	await selectPaymentMethod( page, 'Bancontact' );

	await focusPlaceOrderButton( page );
	await placeOrder( page );
	await page.getByRole( 'link', { name: 'Authorize Test Payment' } ).click();
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
			test.describe( `Card testing protection enabled: ${ ctpEnabled }`, () => {
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

				// Reload after each test to prevent state leaking between tests.
				test.afterEach( async () => {
					await shopperPage.reload();
				} );

				test( 'should successfully place order with Bancontact', async () => {
					await checkoutWithBancontact( shopperPage, ctpEnabled );
				} );
			} );
		} );

		test( 'merchant can see and refund a Bancontact order', async () => {
			const orderId = await checkoutWithBancontact( shopperPage, false );

			await verifyOrderAndRefund( merchantPage, orderId );
		} );
	}
);
