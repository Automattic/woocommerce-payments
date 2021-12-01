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
	clearAndFillInput,
	setCheckbox,
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
const WP_ADMIN_PAGES = baseUrl + 'wp-admin/edit.php?post_type=page';
const WCB_CHECKOUT = baseUrl + 'checkout-wcb/';
const WCPAY_DEV_TOOLS = `${ config.get(
	'url'
) }wp-admin/admin.php?page=wcpaydev`;

export const RUN_SUBSCRIPTIONS_TESTS =
	'1' !== process.env.SKIP_WC_SUBSCRIPTIONS_TESTS;

export const RUN_ACTION_SCHEDULER_TESTS =
	'1' !== process.env.SKIP_WC_ACTION_SCHEDULER_TESTS;

export const RUN_WC_BLOCKS_TESTS = '1' !== process.env.SKIP_WC_BLOCKS_TESTS;

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
		await expect( page ).toClick( 'a', {
			text: 'Add payment method',
		} );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );

		if (
			null !==
			( await page.$( '#wc-woocommerce_payments-payment-token-new' ) )
		) {
			await setCheckbox( '#wc-woocommerce_payments-payment-token-new' );
		}

		await fillCardDetails( page, card );

		await expect( page ).toClick( 'button', {
			text: 'Add payment method',
		} );

		const cardIs3DS =
			cardType.toUpperCase().includes( '3DS' ) &&
			! cardType.toLowerCase().includes( 'declined' );

		if ( cardIs3DS ) {
			await confirmCardAuthentication( page, cardType );
		}

		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
	},

	openCheckoutWCB: async () => {
		await page.goto( WCB_CHECKOUT, {
			waitUntil: 'networkidle0',
		} );
	},

	fillShippingDetailsWCB: async ( customerShippingDetails ) => {
		await clearAndFillInput( '#email', customerShippingDetails.email );
		await clearAndFillInput(
			'#shipping-first_name',
			customerShippingDetails.firstname
		);
		await clearAndFillInput(
			'#shipping-last_name',
			customerShippingDetails.lastname
		);
		await clearAndFillInput(
			'#shipping-address_1',
			customerShippingDetails.addressfirstline
		);
		await clearAndFillInput(
			'#shipping-city',
			customerShippingDetails.city
		);
		await clearAndFillInput(
			'#shipping-postcode',
			customerShippingDetails.postcode
		);
	},

	fillBillingDetailsWCB: async ( customerBillingDetails ) => {
		await clearAndFillInput( '#email', customerBillingDetails.email );
		await clearAndFillInput(
			'#billing-first_name',
			customerBillingDetails.firstname
		);
		await clearAndFillInput(
			'#billing-last_name',
			customerBillingDetails.lastname
		);
		await clearAndFillInput(
			'#billing-address_1',
			customerBillingDetails.addressfirstline
		);
		await clearAndFillInput( '#billing-city', customerBillingDetails.city );
		await clearAndFillInput(
			'#billing-postcode',
			customerBillingDetails.postcode
		);
	},
};

// The generic flows will be moved to their own package soon (more details in p7bje6-2gV-p2), so we're
// keeping our customizations grouped here so it's easier to extend the flows once the move happens.
export const merchantWCP = {
	activateUpe: async () => {
		await page.goto( WCPAY_DEV_TOOLS, {
			waitUntil: 'networkidle0',
		} );

		if ( ! ( await page.$( '#_wcpay_feature_upe:checked' ) ) ) {
			await expect( page ).toClick( 'label', {
				text: 'Enable UPE checkout',
			} );
		}

		const isAdditionalPaymentsActive = await page.$(
			'#_wcpay_feature_upe_additional_payment_methods:checked'
		);

		if ( ! isAdditionalPaymentsActive ) {
			await expect( page ).toClick( 'label', {
				text: 'Add UPE additional payment methods',
			} );
		}

		await expect( page ).toClick( 'input[type="submit"]' );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
	},

	deactivateUpe: async () => {
		await page.goto( WCPAY_DEV_TOOLS, {
			waitUntil: 'networkidle0',
		} );

		if ( await page.$( '#_wcpay_feature_upe:checked' ) ) {
			await expect( page ).toClick( 'label', {
				text: 'Enable UPE checkout',
			} );
		}

		const isAdditionalPaymentsActive = await page.$(
			'#_wcpay_feature_upe_additional_payment_methods:checked'
		);

		if ( isAdditionalPaymentsActive ) {
			await expect( page ).toClick( 'label', {
				text: 'Add UPE additional payment methods',
			} );
		}

		await expect( page ).toClick( 'input[type="submit"]' );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
	},

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
	 * @param periodTime can be `day`, `week`, `month` or `year`
	 * @param includeSignupFee defaults to `false`
	 * @param includeFreeTrial defaults to `false`
	 * @return id of the created subscription product
	 *
	 */

	createSubscriptionProduct: async (
		productName,
		periodTime,
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
		await expect( page ).toSelect( '#_subscription_period', periodTime );

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
		const snackbarSettingsSaved = '.components-snackbar';

		await expect( page ).toClick( '.save-settings-section button' );
		await expect( page ).toMatchElement( snackbarSettingsSaved, {
			text: 'Settings saved.',
			timeout: 60000,
		} );
		await expect( page ).toClick( snackbarSettingsSaved );
	},

	addNewPageCheckoutWCB: async () => {
		await page.goto( WP_ADMIN_PAGES, {
			waitUntil: 'networkidle0',
		} );

		// Add a new page called "Checkout WCB"
		await page.keyboard.press( 'Escape' ); // to dismiss a dialog if present
		await expect( page ).toClick( '.page-title-action', {
			waitUntil: 'networkidle0',
		} );
		await page.waitForSelector( 'h1.editor-post-title__input' );
		await page.type( 'h1.editor-post-title__input', 'Checkout WCB' );

		// Insert new checkout by WCB (searching for Checkout block and pressing Enter)
		await expect( page ).toClick(
			'button.edit-post-header-toolbar__inserter-toggle'
		);
		await expect( page ).toFill(
			'div.components-search-control__input-wrapper > input.components-search-control__input',
			'Checkout'
		);
		await page.keyboard.press( 'Tab' );
		await page.keyboard.press( 'Tab' );
		await page.keyboard.press( 'Enter' );

		// Dismiss dialog about potentially compatibility issues
		await page.keyboard.press( 'Escape' ); // to dismiss a dialog if present

		// Publish the page
		await expect( page ).toClick(
			'button.editor-post-publish-panel__toggle'
		);
		await expect( page ).toClick( 'button.editor-post-publish-button' );
		await page.waitForSelector(
			'.components-snackbar__content',
			'Page updated.'
		);
	},
};
