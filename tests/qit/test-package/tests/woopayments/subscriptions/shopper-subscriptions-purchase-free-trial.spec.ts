/**
 * External dependencies
 */
import qit from '@qit/helpers';

/**
 * Internal dependencies
 */
import { test, expect } from '../../../fixtures/auth';
import { config } from '../../../config/default';
import {
	confirmCardAuthentication,
	emptyCart,
	fillCardDetails,
	setupCheckout,
} from '../../../utils/shopper';
import {
	goToProductPageBySlug,
	goToCart,
} from '../../../utils/shopper-navigation';
import {
	goToOrder,
	goToSubscriptions,
	activateMulticurrency,
	deactivateMulticurrency,
	isMulticurrencyEnabled,
} from '../../../utils/merchant';

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
const customerBilling = config.addresses[ 'subscriptions-customer' ].billing;

test.describe(
	'Subscriptions > Shopper purchase free trial subscription',
	{ tag: [ '@shopper', '@subscriptions', '@critical' ] },
	() => {
		let wasMultiCurrencyEnabled = false;
		let orderId: string;
		let subscriptionId: string;

		test.beforeAll( async ( { adminPage, customerPage } ) => {
			// Delete existing subscriptions customer if exists to ensure clean state
			try {
				await qit.wp(
					`user delete $( wp user get ${ customerBilling.email } --field=ID 2>/dev/null ) --yes 2>/dev/null || true`,
					true
				);
			} catch ( e ) {
				// User might not exist, continue
			}

			wasMultiCurrencyEnabled = await isMulticurrencyEnabled( adminPage );
			if ( wasMultiCurrencyEnabled ) {
				await deactivateMulticurrency( adminPage );
			}
		} );

		test.afterAll( async ( { adminPage } ) => {
			if ( wasMultiCurrencyEnabled ) {
				await activateMulticurrency( adminPage );
			}
		} );

		test( 'Shopper should be able to purchase a free trial', async ( {
			customerPage,
		} ) => {
			// Just to be sure, empty the cart
			await emptyCart( customerPage );

			// Open the subscription product, and verify that the
			// 14-day free trial is shown in the product description
			await goToProductPageBySlug( customerPage, productSlug );
			await expect(
				customerPage
					.locator( '.product' )
					.getByText( '/ month with a 14-day free trial' )
			).toBeVisible();

			// Add it to the cart and verify that the cart page shows the free trial details
			await customerPage
				.getByRole( 'button', { name: 'Add to cart', exact: true } )
				.click();
			await goToCart( customerPage );
			await expect(
				customerPage
					.getByText( '/ month with a 14-day free trial' )
					.first()
			).toBeVisible();

			// Also verify that the first renewal is 14 days from now
			await expect(
				customerPage.getByText(
					`First renewal: ${ renewalDateFormatted }`
				)
			).toBeVisible();

			// Verify that the order total is $0.00
			await expect(
				customerPage
					.getByRole( 'row', {
						name: 'Total $0.00',
						exact: true,
					} )
					.locator( 'td' )
			).toBeVisible();

			// Proceed to the checkout page and verify that the 14-day free trial is shown in the product line item,
			// and that the first renewal date is 14 days from now.
			await setupCheckout( customerPage, customerBilling );
			await expect(
				customerPage
					.locator( '#order_review' )
					.getByText( '/ month with a 14-day free trial' )
			).toBeVisible();
			await expect(
				customerPage.getByText(
					`First renewal: ${ renewalDateFormatted }`
				)
			).toBeVisible();

			// Pay using a 3DS card
			const card = config.cards[ '3dsOTP' ];
			await fillCardDetails( customerPage, card );
			await customerPage
				.getByRole( 'button', { name: 'Place order', exact: true } )
				.click();
			const firstFrame = customerPage.frames()[ 0 ];
			await firstFrame.waitForLoadState( 'load' );
			await confirmCardAuthentication( customerPage, true );
			await firstFrame.waitForLoadState( 'networkidle' );
			await customerPage.waitForLoadState( 'networkidle' );
			await expect(
				customerPage.getByRole( 'heading', {
					name: 'Order received',
				} )
			).toBeVisible();

			// Get the order ID so we can open it in the merchant view
			orderId = ( await customerPage.getByText( 'Order number:' ).innerText() )
				.replace( /[^0-9]/g, '' )
				.trim();
			subscriptionId = (
				await customerPage
					.getByLabel( 'View subscription number' )
					.textContent()
			)
				?.trim()
				.replace( '#', '' ) ?? '';
		} );

		test( 'Merchant should be able to create an order with "Setup Intent"', async ( {
			adminPage,
		} ) => {
			await goToOrder( adminPage, orderId );
			await expect(
				adminPage.locator( '.woocommerce-order-data__meta' )
			).toContainText( 'seti_' );

			await goToSubscriptions( adminPage );
			const subscriptionRow = adminPage.getByRole( 'row', {
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
