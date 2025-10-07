/**
 * External dependencies
 */
import { expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { test } from '../../../fixtures/auth';
import { config } from '../../../config/default';
import {
	confirmCardAuthentication,
	emptyCart,
	fillCardDetails,
	setupCheckout,
} from '../../../utils/shopper';
import {
	goToCart,
	goToProductPageBySlug,
} from '../../../utils/shopper-navigation';
import { goToOrder, goToSubscriptions } from '../../../utils/merchant';

// Define subscriptions test guard from legacy pattern
const shouldRunSubscriptionsTests =
	process.env.SKIP_WC_SUBSCRIPTIONS_TESTS !== '1';

// Calculate dates for 14-day free trial
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

test.describe( 'Subscriptions > Purchase Free Trial', () => {
	test.skip(
		! shouldRunSubscriptionsTests,
		'Subscriptions tests are disabled'
	);

	let orderId: string;
	let subscriptionId: string;

	test(
		'Shopper should be able to purchase a free trial subscription',
		{ tag: [ '@critical', '@subscriptions', '@shopper' ] },
		async ( { customerPage } ) => {
			const customerBilling = config.addresses.customer.billing;

			// Empty cart to ensure clean state
			await emptyCart( customerPage );

			// Open the subscription product and verify free trial is shown
			await goToProductPageBySlug( customerPage, productSlug );
			await expect(
				customerPage
					.locator( '.product' )
					.getByText( '/ month with a 14-day free trial' )
			).toBeVisible();

			// Add to cart and verify cart shows free trial details
			await customerPage
				.getByRole( 'button', { name: 'Add to cart', exact: true } )
				.click();
			await goToCart( customerPage );
			await expect(
				customerPage
					.getByText( '/ month with a 14-day free trial' )
					.first()
			).toBeVisible();

			// Verify first renewal date is 14 days from now
			await expect(
				customerPage.getByText(
					`First renewal: ${ renewalDateFormatted }`
				)
			).toBeVisible();

			// Verify order total is $0.00 (free trial)
			await expect(
				customerPage
					.getByRole( 'row', {
						name: 'Total $0.00',
						exact: true,
					} )
					.locator( 'td' )
			).toBeVisible();

			// Proceed to checkout and verify free trial details
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

			// Handle 3DS authentication
			await customerPage.frames()[ 0 ].waitForLoadState( 'load' );
			await confirmCardAuthentication( customerPage, true );
			await customerPage.frames()[ 0 ].waitForLoadState( 'networkidle' );
			await customerPage.waitForLoadState( 'networkidle' );

			// Verify order received
			await expect(
				customerPage.getByRole( 'heading', {
					name: 'Order received',
				} )
			).toBeVisible();

			// Extract order and subscription IDs for merchant verification
			orderId = (
				await customerPage.getByText( 'Order number:' ).innerText()
			 )
				.replace( /[^0-9]/g, '' )
				.trim();
			subscriptionId = (
				await customerPage
					.getByLabel( 'View subscription number' )
					.textContent()
			 )
				.trim()
				.replace( '#', '' );
		}
	);

	test(
		'Merchant should see active subscription with Setup Intent',
		{ tag: [ '@subscriptions', '@merchant' ] },
		async ( { adminPage } ) => {
			// Verify order has Setup Intent (seti_) for free trial
			await goToOrder( adminPage, orderId );
			await expect(
				adminPage.locator( '.woocommerce-order-data__meta' )
			).toContainText( 'seti_' );

			// Navigate to subscriptions and verify subscription details
			await goToSubscriptions( adminPage );
			const subscriptionRow = adminPage.getByRole( 'row', {
				name: '#' + subscriptionId,
			} );

			// Verify subscription is active
			await expect( subscriptionRow.locator( 'mark' ) ).toHaveText(
				'Active'
			);

			// Verify product name
			await expect(
				subscriptionRow.getByRole( 'cell', { name: productName } )
			).toBeVisible();

			// Verify recurring amount
			await expect(
				subscriptionRow.getByRole( 'cell', {
					name: /\$9\.99 \/ month/,
				} )
			).toBeVisible();

			// Verify renewal date appears twice (next payment + end date)
			await expect(
				subscriptionRow.getByText( renewalDateFormatted )
			).toHaveCount( 2 );
		}
	);
} );
