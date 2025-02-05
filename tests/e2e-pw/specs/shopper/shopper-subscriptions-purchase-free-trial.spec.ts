/**
 * External dependencies
 */
import test, { Page, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { shouldRunSubscriptionsTests } from '../../utils/constants';
import { describeif, getMerchant, getShopper } from '../../utils/helpers';
import { config } from '../../config/default';
import {
	confirmCardAuthentication,
	emptyCart,
	fillCardDetails,
	setupCheckout,
} from '../../utils/shopper';
import {
	goToCart,
	goToProductPageBySlug,
} from '../../utils/shopper-navigation';
import { goToOrder, goToSubscriptions } from '../../utils/merchant-navigation';
import {
	activateMulticurrency,
	deactivateMulticurrency,
	isMulticurrencyEnabled,
} from '../../utils/merchant';

const nowLocal = new Date();
const nowUTC = new Date(
	nowLocal.getUTCFullYear(),
	nowLocal.getUTCMonth(),
	nowLocal.getUTCDate()
);
const formatter = new Intl.DateTimeFormat( 'en-US', {
	dateStyle: 'long',
} );
const renewalDate = nowUTC.setDate( nowUTC.getDate() + 14 );
const renewalDateFormatted = formatter.format( renewalDate );
const productName = 'Subscription free trial product';
const productSlug = 'subscription-free-trial-product';
const customerBilling = config.addresses.customer.billing;
let orderId: string, subscriptionId: string;

describeif( shouldRunSubscriptionsTests )(
	'Shopper: Subscriptions - Purchase Free Trial',
	() => {
		let wasMultiCurrencyEnabled = false;
		let merchantPage: Page;

		test.beforeAll( async ( { browser } ) => {
			merchantPage = ( await getMerchant( browser ) ).merchantPage;
			wasMultiCurrencyEnabled = await isMulticurrencyEnabled(
				merchantPage
			);
			if ( wasMultiCurrencyEnabled ) {
				await deactivateMulticurrency( merchantPage );
			}
		} );

		test.afterAll( async () => {
			if ( wasMultiCurrencyEnabled ) {
				await activateMulticurrency( merchantPage );
			}
		} );

		test( 'Shopper should be able to purchase a free trial', async ( {
			browser,
		} ) => {
			const { shopperPage } = await getShopper( browser );
			// Just to be sure, empty the cart
			await emptyCart( shopperPage );

			// Open the subscription product, and verify that the
			// 14-day free trial is shown in the product description
			await goToProductPageBySlug( shopperPage, productSlug );
			await expect(
				shopperPage
					.locator( '.product' )
					.getByText( '/ month with a 14-day free trial' )
			).toBeVisible();

			// Add it to the cart and verify that the cart page shows the free trial details
			await shopperPage
				.getByRole( 'button', { name: 'Sign up now' } )
				.click();
			await goToCart( shopperPage );
			await expect(
				shopperPage
					.getByText( '/ month with a 14-day free trial' )
					.first()
			).toBeVisible();

			// Also verify that the first renewal is 14 days from now
			await expect(
				shopperPage.getByText(
					`First renewal: ${ renewalDateFormatted }`
				)
			).toBeVisible();

			// Verify that the order total is $0.00
			await expect(
				shopperPage.getByRole( 'cell', { name: /^Total: \$0\.00/ } )
			).toBeVisible();

			// Proceed to the checkout page and verify that the 14-day free trial is shown in the product line item,
			// and that the first renewal date is 14 days from now.
			await setupCheckout( shopperPage, customerBilling );
			await expect(
				shopperPage
					.locator( '#order_review' )
					.getByText( '/ month with a 14-day free trial' )
			).toBeVisible();
			await expect(
				shopperPage.getByText(
					`First renewal: ${ renewalDateFormatted }`
				)
			).toBeVisible();

			// Pay using a 3DS card
			const card = config.cards[ '3dsOTP' ];
			await fillCardDetails( shopperPage, card );
			await shopperPage
				.getByRole( 'button', { name: 'Sign up now' } )
				.click();
			await shopperPage.frames()[ 0 ].waitForLoadState( 'load' );
			await confirmCardAuthentication( shopperPage, true );
			await shopperPage.frames()[ 0 ].waitForLoadState( 'networkidle' );
			await shopperPage.waitForLoadState( 'networkidle' );
			await expect(
				shopperPage.getByRole( 'heading', {
					name: 'Order received',
				} )
			).toBeVisible();

			// Get the order ID so we can open it in the merchant view
			orderId = (
				await shopperPage.getByText( 'Order number:' ).innerText()
			 )
				.replace( /[^0-9]/g, '' )
				.trim();
			subscriptionId = (
				await shopperPage
					.getByLabel( 'View subscription number' )
					.textContent()
			 )
				.trim()
				.replace( '#', '' );
		} );

		test( 'Merchant should be able to create an order with "Setup Intent"', async () => {
			await goToOrder( merchantPage, orderId );
			await expect(
				merchantPage.locator( '.woocommerce-order-data__meta' )
			).toContainText( 'seti_' );

			await goToSubscriptions( merchantPage );
			const subscriptionRow = merchantPage.getByRole( 'row', {
				name: '#' + subscriptionId,
			} );
			await expect( subscriptionRow.locator( 'mark' ) ).toHaveText(
				'Active'
			);
			await expect(
				subscriptionRow.getByRole( 'cell', { name: productName } )
			).toBeVisible();
			await expect(
				subscriptionRow.getByRole( 'cell', { name: /\$9.99 \/ month/ } )
			).toBeVisible();
			await expect(
				subscriptionRow.getByText( renewalDateFormatted )
			).toHaveCount( 2 );
		} );
	}
);
