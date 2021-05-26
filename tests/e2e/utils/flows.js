/**
 * @format
 */

/**
 * External dependencies
 */

const { merchant, verifyAndPublish } = require( '@woocommerce/e2e-utils' );

const config = require( 'config' );
const baseUrl = config.get( 'url' );

import { uiLoaded } from './helpers';

const SHOP_MY_ACCOUNT_PAGE = baseUrl + 'my-account/';
const MY_ACCOUNT_PAYMENT_METHODS = baseUrl + 'my-account/payment-methods';
const WC_ADMIN_BASE_URL = baseUrl + 'wp-admin/';

const WC_SUBSCRIPTIONS_PAGE =
	baseUrl + 'wp-admin/edit.php?post_type=shop_subscription';

export const RUN_SUBSCRIPTIONS_TESTS =
	'1' !== process.env.SKIP_WC_SUBSCRIPTIONS_TESTS;

// The generic flows will be moved to their own package soon (more details in p7bje6-2gV-p2), so we're
// keeping our customizations grouped here so it's easier to extend the flows once the move happens.
export const paymentsShopper = {
	goToPaymentMethods: async () => {
		await page.goto( MY_ACCOUNT_PAYMENT_METHODS, {
			waitUntil: 'networkidle0',
		} );
	},

	logout: async () => {
		await page.goto( SHOP_MY_ACCOUNT_PAGE, {
			waitUntil: 'networkidle0',
		} );

		await expect( page.title() ).resolves.toMatch( 'My account' );
		await Promise.all( [
			page.waitForNavigation( { waitUntil: 'networkidle0' } ),
			page.click(
				'.woocommerce-MyAccount-navigation-link--customer-logout a'
			),
		] );
	},

	deleteSavedPaymentMethod: async ( label ) => {
		const [ paymentMethodRow ] = await page.$x(
			`//tr[contains(., '${ label }')]`
		);
		await expect( paymentMethodRow ).toClick( '.button.delete' );
		await page.waitForNavigation( { waitUntil: 'networkidle0' } );
	},

	selectNewPaymentMethod: async () => {
		if (
			null !==
			( await page.$( '#wc-woocommerce_payments-payment-token-new' ) )
		) {
			await expect( page ).toClick(
				'#wc-woocommerce_payments-payment-token-new'
			);
		}
	},

	toggleSavePaymentMethod: async () => {
		await expect( page ).toClick(
			'#wc-woocommerce_payments-new-payment-method'
		);
	},

	selectSavedPaymentMethod: async ( label ) => {
		await expect( page ).toClick( 'label', { text: label } );
	},

	toggleCreateAccount: async () => {
		await expect( page ).toClick( '#createaccount' );
	},
};

export const merchantWCP = {
	openDisputeDetails: async ( disputeDetailsLink ) => {
		await Promise.all( [
			page.goto( WC_ADMIN_BASE_URL + disputeDetailsLink, {
				waitUntil: 'networkidle0',
			} ),
			uiLoaded(),
		] );
		await uiLoaded();
	},

	openChallengeDispute: async () => {
		await expect( page ).toClick( 'a.components-button.is-primary', {
			text: 'Challenge dispute',
		} );
		await page.waitForNavigation( { waitUntil: 'networkidle0' } );
		await uiLoaded();
	},

	openPaymentDetails: async ( paymentDetailsLink ) => {
		await Promise.all( [
			page.goto( paymentDetailsLink, {
				waitUntil: 'networkidle0',
			} ),
			uiLoaded(),
		] );
		await uiLoaded();
	},

	openSubscriptions: async () => {
		await page.goto( WC_SUBSCRIPTIONS_PAGE, {
			waitUntil: 'networkidle0',
		} );
		await expect( page ).toMatchElement( 'h1', { text: 'Subscriptions' } );
	},

	// Create a subscription product with an optional signup fee
	createSubscriptionProduct: async (
		productName,
		includeSignupFee = false
	) => {
		// Go to "add product" page
		await merchant.openNewProduct();

		// Make sure we're on the add product page
		await expect( page.title() ).resolves.toMatch( 'Add new product' );
		await expect( page ).toFill( '#title', productName );
		await expect( page ).toSelect( '#product-type', 'Simple subscription' );
		await expect( page ).toFill( '#_subscription_price', '9.99' );

		if ( includeSignupFee ) {
			await expect( page ).toFill( '#_subscription_sign_up_fee', '1.99' );
		}

		await verifyAndPublish();
	},
};
