/**
 * @format
 */

/**
 * External dependencies
 */

const {
	merchant,
	verifyAndPublish,
	evalAndClick,
	uiUnblocked,
} = require( '@woocommerce/e2e-utils' );
const {
	fillCardDetails,
	confirmCardAuthentication,
} = require( '../utils/payments' );

const config = require( 'config' );
const baseUrl = config.get( 'url' );

import { uiLoaded } from './helpers';

const SHOP_MY_ACCOUNT_PAGE = baseUrl + 'my-account/';
const MY_ACCOUNT_PAYMENT_METHODS = baseUrl + 'my-account/payment-methods';
const WC_ADMIN_BASE_URL = baseUrl + 'wp-admin/';
const MY_ACCOUNT_SUBSCRIPTIONS = baseUrl + 'my-account/subscriptions';
const WCPAY_DISPUTES =
	baseUrl + 'wp-admin/admin.php?page=wc-admin&path=/payments/disputes';
const WCPAY_DEPOSITS =
	baseUrl + 'wp-admin/admin.php?page=wc-admin&path=/payments/deposits';
const WCPAY_TRANSACTIONS =
	baseUrl + 'wp-admin/admin.php?page=wc-admin&path=/payments/transactions';
const WC_SUBSCRIPTIONS_PAGE =
	baseUrl + 'wp-admin/edit.php?post_type=shop_subscription';
const ACTION_SCHEDULER = baseUrl + 'wp-admin/tools.php?page=action-scheduler';

export const RUN_SUBSCRIPTIONS_TESTS =
	'1' !== process.env.SKIP_WC_SUBSCRIPTIONS_TESTS;

export const RUN_ACTION_SCHEDULER_TESTS =
	'1' !== process.env.SKIP_WC_ACTION_SCHEDULER_TESTS;

// The generic flows will be moved to their own package soon (more details in p7bje6-2gV-p2), so we're
// keeping our customizations grouped here so it's easier to extend the flows once the move happens.
export const shopperWCP = {
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

	goToSubscriptions: async () => {
		await page.goto( MY_ACCOUNT_SUBSCRIPTIONS, {
			waitUntil: 'networkidle0',
		} );
	},

	/**
	 * Happy path for adding a new payment method in 'My Account > Payment methods' page.
	 * It can handle 3DS and 3DS2 flows.
	 *
	 * @param {*} cardType Card type as defined in the `test.json` file. Examples: `basic`, `3ds2`, `declined`.
	 * @param {*} card Card object that you want to add as the new payment method.
	 */
	addNewPaymentMethod: async ( cardType, card ) => {
		const cardIs3DS =
			cardType.toUpperCase().includes( '3DS' ) &&
			! cardType.toLowerCase().includes( 'declined' );

		await expect( page ).toClick( 'a', {
			text: 'Add payment method',
		} );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
		await fillCardDetails( page, card );
		await expect( page ).toClick( 'button', {
			text: 'Add payment method',
		} );

		if ( cardIs3DS ) {
			await confirmCardAuthentication( page, cardType );
		}
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
	},
};

// The generic flows will be moved to their own package soon (more details in p7bje6-2gV-p2), so we're
// keeping our customizations grouped here so it's easier to extend the flows once the move happens.
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
		await Promise.all( [
			evalAndClick( 'a.components-button.is-primary' ),
			page.waitForNavigation( { waitUntil: 'networkidle0' } ),
			uiLoaded(),
		] );
	},

	openAcceptDispute: async () => {
		await Promise.all( [
			page.removeAllListeners( 'dialog' ),
			evalAndClick( 'button.components-button.is-secondary' ),
			page.on( 'dialog', async ( dialog ) => {
				await dialog.accept();
			} ),
			uiUnblocked(),
			page.waitForNavigation( { waitUntil: 'networkidle0' } ),
			uiLoaded(),
		] );
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

	/**
	 * Create a subscription product with an optional signup fee
	 *
	 * @param productName
	 * @param includeSignupFee defaults to `false`
	 * @param includeFreeTrial defaults to `false`
	 * @return id of the created subscription product
	 *
	 */

	createSubscriptionProduct: async (
		productName,
		includeSignupFee = false,
		includeFreeTrial = false
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

		if ( includeFreeTrial ) {
			await expect( page ).toFill( '#_subscription_trial_length', '14' );
		}

		await verifyAndPublish();

		// Return the id of this subscription product
		const postSubstring = page.url().match( /post=\d+/ )[ 0 ];
		const productId = postSubstring.replace( 'post=', '' );
		return productId;
	},

	openDisputes: async () => {
		await page.goto( WCPAY_DISPUTES, {
			waitUntil: 'networkidle0',
		} );
		await uiLoaded();
	},

	openDeposits: async () => {
		await page.goto( WCPAY_DEPOSITS, {
			waitUntil: 'networkidle0',
		} );
		await uiLoaded();
	},

	openTransactions: async () => {
		await page.goto( WCPAY_TRANSACTIONS, {
			waitUntil: 'networkidle0',
		} );
		await uiLoaded();
	},

	openActionScheduler: async () => {
		await page.goto( ACTION_SCHEDULER, {
			waitUntil: 'networkidle0',
		} );
	},

	openWCPSettings: async () => {
		await merchant.openSettings( 'checkout', 'woocommerce_payments' );
	},

	wcpSettingsSaveChanges: async () => {
		await expect( page ).toClick( '.save-settings-section button' );
		await expect( page ).toClick( '.components-snackbar', {
			timeout: 30000,
		} );
	},
};
