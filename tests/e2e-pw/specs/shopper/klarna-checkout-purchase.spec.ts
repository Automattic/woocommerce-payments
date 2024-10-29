/**
 * External dependencies
 */
import { test, expect, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import * as shopper from '../../utils/shopper';
import { getMerchant, getShopper } from '../../utils/helpers';
import * as merchant from '../../utils/merchant';
import { config } from '../../config/default';
import {
	goToProductPageBySlug,
	goToShop,
} from '../../utils/shopper-navigation';

test.describe( 'Klarna Checkout', () => {
	let merchantPage: Page;
	let shopperPage: Page;
	let wasMulticurrencyEnabled: boolean;

	test.beforeAll( async ( { browser } ) => {
		shopperPage = ( await getShopper( browser ) ).shopperPage;
		merchantPage = ( await getMerchant( browser ) ).merchantPage;
		wasMulticurrencyEnabled = await merchant.isMulticurrencyEnabled(
			merchantPage
		);
		if ( wasMulticurrencyEnabled ) {
			await merchant.deactivateMulticurrency( merchantPage );
		}
		await merchant.enablePaymentMethods( merchantPage, [ 'klarna' ] );
	} );

	test.afterAll( async () => {
		await shopper.emptyCart( shopperPage );

		await merchant.disablePaymentMethods( merchantPage, [ 'klarna' ] );

		if ( wasMulticurrencyEnabled ) {
			await merchant.activateMulticurrency( merchantPage );
		}
	} );

	test( 'shows the message in the product page', async () => {
		await goToProductPageBySlug( shopperPage, 'belt' );

		// Since we cant' control the exact contents of the iframe, we just make sure it's there.
		await expect(
			shopperPage
				.frameLocator( '#payment-method-message iframe' )
				.locator( 'body' )
		).not.toBeEmpty();
	} );

	test( 'allows to use Klarna as a payment method', async () => {
		await goToShop( shopperPage );
		await shopper.setupProductCheckout( shopperPage, [ [ 'Belt', 1 ] ], {
			...config.addresses.customer.billing,
			// these are Klarna-specific values:
			// https://docs.klarna.com/resources/test-environment/sample-customer-data/#united-states-of-america
			email: 'customer@email.us',
			phone: '+13106683312',
			firstname: 'Test',
			lastname: 'Person-us',
		} );

		await shopperPage
			.locator( '.wc_payment_methods' )
			.getByText( 'Klarna' )
			.click();

		await shopper.placeOrder( shopperPage );

		// Since we don't have control over the html in the Klarna playground page,
		// verifying the redirect is all we can do consistently without introducing a
		// flaky test.
		await expect( shopperPage ).toHaveURL( /.*klarna\.com/ );
	} );
} );
