/**
 * @format
 */

/**
 * External dependencies
 */

const {
	merchant,
	shopper,
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
const MY_ACCOUNT_EDIT = baseUrl + 'my-account/edit-account';
const MY_ACCOUNT_ORDERS = SHOP_MY_ACCOUNT_PAGE + 'orders/';
const WCPAY_DISPUTES =
	baseUrl + 'wp-admin/admin.php?page=wc-admin&path=/payments/disputes';
const WCPAY_DEPOSITS =
	baseUrl + 'wp-admin/admin.php?page=wc-admin&path=/payments/deposits';
const WCPAY_TRANSACTIONS =
	baseUrl + 'wp-admin/admin.php?page=wc-admin&path=/payments/transactions';
const WCPAY_MULTI_CURRENCY =
	baseUrl + 'wp-admin/admin.php?page=wc-settings&tab=wcpay_multi_currency';
const WCPAY_PAYMENT_SETTINGS =
	baseUrl +
	'wp-admin/admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments';
const WC_SUBSCRIPTIONS_PAGE =
	baseUrl + 'wp-admin/edit.php?post_type=shop_subscription';
const ACTION_SCHEDULER = baseUrl + 'wp-admin/tools.php?page=action-scheduler';
const WP_ADMIN_PAGES = baseUrl + 'wp-admin/edit.php?post_type=page';
const WCB_CHECKOUT = baseUrl + 'checkout-wcb/';
const WCPAY_DEV_TOOLS = baseUrl + 'wp-admin/admin.php?page=wcpaydev';
const SHOP_CART_PAGE = baseUrl + 'cart/';

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

	goToOrders: async () => {
		await page.goto( MY_ACCOUNT_ORDERS, {
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

	changeAccountCurrencyTo: async ( currencyToSet ) => {
		await page.goto( MY_ACCOUNT_EDIT, {
			waitUntil: 'networkidle0',
		} );

		await page.select( '#wcpay_selected_currency', currencyToSet );
		await expect( page ).toClick( 'button', {
			text: 'Save changes',
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
		await clearAndFillInput(
			'#billing-country .components-form-token-field__input',
			customerBillingDetails.country
		);
		await clearAndFillInput( '#billing-city', customerBillingDetails.city );
		await clearAndFillInput(
			'#billing-state .components-form-token-field__input',
			customerBillingDetails.state
		);
		await clearAndFillInput(
			'#billing-postcode',
			customerBillingDetails.postcode
		);
	},

	emptyCart: async () => {
		await page.goto( SHOP_CART_PAGE, {
			waitUntil: 'networkidle0',
		} );

		// Remove products if they exist
		if ( null !== ( await page.$$( '.remove' ) ) ) {
			let products = await page.$$( '.remove' );
			while ( products && 0 < products.length ) {
				for ( const product of products ) {
					await product.click();
					await uiUnblocked();
				}
				products = await page.$$( '.remove' );
			}
		}

		// Remove coupons if they exist
		if ( null !== ( await page.$( '.woocommerce-remove-coupon' ) ) ) {
			await page.click( '.woocommerce-remove-coupon' );
			await uiUnblocked();
		}

		await page.waitForSelector( '.cart-empty.woocommerce-info' );
		await expect( page ).toMatchElement( '.cart-empty.woocommerce-info', {
			text: 'Your cart is currently empty.',
		} );
	},

	addToCartBySlug: async ( productSlug ) => {
		await page.goto( config.get( 'url' ) + `product/${ productSlug }`, {
			waitUntil: 'networkidle0',
		} );
		await shopper.addToCart();
	},

	validateCheckoutError: async () => {
		const errorMessage =
			"Error: There's a problem with this payment. Please try again or use a different payment method.";

		await page.waitForSelector( '.woocommerce-error' );
		await expect(
			page
		).toMatchElement(
			'div.woocommerce-NoticeGroup > ul.woocommerce-error > li',
			{ text: errorMessage }
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
			await expect( page ).toClick( 'label[for="_wcpay_feature_upe"]' );
		}

		const isSplitUPEEnabled = await page.$(
			'#_wcpay_feature_upe_split:checked'
		);

		if ( isSplitUPEEnabled ) {
			// Deactivate the Split UPE checkout.
			await expect( page ).toClick(
				'label[for="_wcpay_feature_upe_split"]'
			);
		}

		const isAdditionalPaymentsActive = await page.$(
			'#_wcpay_feature_upe_additional_payment_methods:checked'
		);

		if ( ! isAdditionalPaymentsActive ) {
			await expect( page ).toClick(
				'label[for="_wcpay_feature_upe_additional_payment_methods"]'
			);
		}

		await expect( page ).toClick( 'input#submit' );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
	},

	activateSplitUpe: async () => {
		await page.goto( WCPAY_DEV_TOOLS, {
			waitUntil: 'networkidle0',
		} );

		if ( ! ( await page.$( '#_wcpay_feature_upe_split:checked' ) ) ) {
			await expect( page ).toClick(
				'label[for="_wcpay_feature_upe_split"]'
			);
		}

		const isAdditionalPaymentsActive = await page.$(
			'#_wcpay_feature_upe_additional_payment_methods:checked'
		);

		if ( ! isAdditionalPaymentsActive ) {
			await expect( page ).toClick(
				'label[for="_wcpay_feature_upe_additional_payment_methods"]'
			);
		}

		await expect( page ).toClick( 'input#submit' );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
	},

	activateUPEWithDefferedIntentCreation: async () => {
		await page.goto( WCPAY_DEV_TOOLS, {
			waitUntil: 'networkidle0',
		} );

		// uncheck UPE
		if ( await page.$( '#_wcpay_feature_upe:checked' ) ) {
			await expect( page ).toClick( 'label', {
				text: 'Enable UPE checkout (legacy)',
			} );
		}

		// uncheck split UPE
		if ( await page.$( '#_wcpay_feature_upe_split:checked' ) ) {
			await expect( page ).toClick( 'label', {
				text: 'Enable Split UPE checkout',
			} );
		}

		// check enhanced UPE
		if (
			! ( await page.$( '#_wcpay_feature_upe_deferred_intent:checked' ) )
		) {
			await expect( page ).toClick( 'label', {
				text: 'Enable Split UPE checkout with deferred intent creation',
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

	deactivateUPEWithDefferedIntentCreation: async () => {
		await page.goto( WCPAY_DEV_TOOLS, {
			waitUntil: 'networkidle0',
		} );

		if ( await page.$( '#_wcpay_feature_upe_deferred_intent:checked' ) ) {
			await expect( page ).toClick( 'label', {
				text: 'Enable Split UPE checkout with deferred intent creation',
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

	deactivateUpe: async () => {
		await page.goto( WCPAY_DEV_TOOLS, {
			waitUntil: 'networkidle0',
		} );

		if ( await page.$( '#_wcpay_feature_upe:checked' ) ) {
			await expect( page ).toClick( 'label[for="_wcpay_feature_upe"]' );
		}

		const isAdditionalPaymentsActive = await page.$(
			'#_wcpay_feature_upe_additional_payment_methods:checked'
		);

		if ( isAdditionalPaymentsActive ) {
			await expect( page ).toClick(
				'label[for="_wcpay_feature_upe_additional_payment_methods"]'
			);
		}

		await expect( page ).toClick( 'input#submit' );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
	},

	deactivateSplitUpe: async () => {
		await page.goto( WCPAY_DEV_TOOLS, {
			waitUntil: 'networkidle0',
		} );

		if ( await page.$( '#_wcpay_feature_upe_split:checked' ) ) {
			await expect( page ).toClick(
				'label[for="_wcpay_feature_upe_split"]'
			);
		}

		const isAdditionalPaymentsActive = await page.$(
			'#_wcpay_feature_upe_additional_payment_methods:checked'
		);

		if ( isAdditionalPaymentsActive ) {
			await expect( page ).toClick(
				'label[for="_wcpay_feature_upe_additional_payment_methods"]'
			);
		}

		await expect( page ).toClick( 'input#submit' );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
	},

	enablePaymentMethod: async ( paymentMethods ) => {
		await page.goto( WCPAY_PAYMENT_SETTINGS, {
			waitUntil: 'networkidle0',
		} );

		for ( const paymentMethod of paymentMethods ) {
			// Skip fraud protection tools tour.
			const tourKitDismissButton = await page.$(
				`button.woocommerce-tour-kit-step-controls__close-btn`
			);
			if ( tourKitDismissButton ) {
				await tourKitDismissButton.evaluate( ( button ) =>
					button.click()
				);
			}

			await page.$eval( paymentMethod, ( method ) => method.click() );
			await new Promise( ( resolve ) => setTimeout( resolve, 2000 ) );
		}

		await expect( page ).toClick( 'button', {
			text: 'Save changes',
		} );
	},

	disablePaymentMethod: async ( paymentMethods ) => {
		await page.goto( WCPAY_PAYMENT_SETTINGS, {
			waitUntil: 'networkidle0',
		} );

		for ( const paymentMethod of paymentMethods ) {
			// Skip fraud protection tools tour.
			const tourKitDismissButton = await page.$(
				`button.woocommerce-tour-kit-step-controls__close-btn`
			);
			if ( tourKitDismissButton ) {
				await tourKitDismissButton.evaluate( ( button ) =>
					button.click()
				);
			}
			await page.$eval( paymentMethod, ( method ) => method.click() );
			await expect( page ).toClick( 'button', {
				text: 'Remove',
			} );
			await new Promise( ( resolve ) => setTimeout( resolve, 2000 ) );
		}

		await expect( page ).toClick( 'button', {
			text: 'Save changes',
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
			evalAndClick(
				'div.wcpay-dispute-details a.components-button.is-primary'
			),
			page.waitForNavigation( { waitUntil: 'networkidle0' } ),
			uiLoaded(),
		] );
	},

	openAcceptDispute: async () => {
		await Promise.all( [
			page.removeAllListeners( 'dialog' ),
			evalAndClick(
				'div.wcpay-dispute-details button.components-button.is-secondary'
			),
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

	openMultiCurrency: async () => {
		await page.goto( WCPAY_MULTI_CURRENCY, {
			waitUntil: 'networkidle0',
		} );
		await uiLoaded();
	},

	openOrderAnalytics: async () => {
		await merchant.openAnalyticsPage( 'orders' );
		await uiLoaded();
	},

	openActionScheduler: async ( status, search ) => {
		let pageUrl = ACTION_SCHEDULER;

		if ( 'undefined' !== typeof status ) {
			pageUrl += '&status=' + status;
		}

		if ( 'undefined' !== typeof search ) {
			pageUrl += '&s=' + search;
		}

		await page.goto( pageUrl, {
			waitUntil: 'networkidle0',
		} );
	},

	openWCPSettings: async () => {
		await merchant.openSettings( 'checkout', 'woocommerce_payments' );
	},

	skipFraudProtectionTour: async () => {
		const tourKitDismissButton = await page.$(
			`button.woocommerce-tour-kit-step-controls__close-btn`
		);
		if ( tourKitDismissButton ) {
			await tourKitDismissButton.evaluate( ( button ) => button.click() );
		}
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
		await page.waitFor( 500 );
		await expect( page ).toClick( 'button.editor-post-publish-button' );
		await page.waitForSelector(
			'.components-snackbar__content',
			'Page updated.'
		);
	},

	setCheckboxByTestId: async ( testId ) => {
		const checkbox = await page.$( `[data-testid="${ testId }"]` );
		const checkboxStatus = await (
			await checkbox.getProperty( 'checked' )
		 ).jsonValue();
		if ( true !== checkboxStatus ) {
			await checkbox.click();
		}
	},

	unsetCheckboxByTestId: async ( testId ) => {
		const checkbox = await page.$( `[data-testid="${ testId }"]` );
		const checkboxStatus = await (
			await checkbox.getProperty( 'checked' )
		 ).jsonValue();
		if ( true === checkboxStatus ) {
			await checkbox.click();
		}
	},

	activateWooPay: async () => {
		await page.goto( WCPAY_DEV_TOOLS, {
			waitUntil: 'networkidle0',
		} );

		if ( ! ( await page.$( '#override_woopay_eligible:checked' ) ) ) {
			await expect( page ).toClick(
				'label[for="override_woopay_eligible"]'
			);

			await expect( page ).toSelect(
				'select[name="override_woopay_eligible_value"]',
				'true'
			);
		}

		await expect( page ).toClick( 'input#submit' );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
	},

	deactivateWooPay: async () => {
		await page.goto( WCPAY_DEV_TOOLS, {
			waitUntil: 'networkidle0',
		} );

		if ( await page.$( '#override_woopay_eligible:checked' ) ) {
			await expect( page ).toClick(
				'label[for="override_woopay_eligible"]'
			);
			await expect( page ).toSelect(
				'select[name="override_woopay_eligible_value"]',
				'false'
			);
		}

		await expect( page ).toClick( 'input#submit' );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
	},

	openAdvancedFraudProtectionSettings: async () => {
		const advancedLevelRadioSelector =
			'#fraud-protection-level-select_advanced-level';
		const advancedLevelConfigureLinkSelector =
			'.fraud-protection__advanced-level-container > a';

		await expect( page ).toClick( advancedLevelRadioSelector );
		await expect( page ).toClick( advancedLevelConfigureLinkSelector );

		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
	},

	openLatestBlockedOrder: async () => {
		await merchantWCP.openTransactions();

		await expect( page ).toClick(
			'.components-tab-panel__tabs-item.blocked-list'
		);
		await uiLoaded();
		await page.waitFor( 1000 );
		await expect( page ).toClick(
			'.woocommerce-table__table tbody > tr:nth-child(2) > th > a'
		);
	},

	checkTransactionStatus: async ( expectedStatus ) => {
		const statusChipSelector = '.payment-details-summary .chip';

		await page.waitForSelector( '.payment-details-summary' );
		await page.waitForSelector( statusChipSelector );

		await expect( page ).toMatchElement( statusChipSelector, {
			text: expectedStatus,
		} );
	},

	checkFraudOutcomeEntry: async ( expectedOutcome ) => {
		await page.waitForSelector( '.woocommerce-timeline' );

		await expect( page ).toMatchElement( '.fraud-outcome-ruleset-item', {
			text: expectedOutcome,
		} );
	},

	disableAllFraudProtectionRules: async () => {
		await merchantWCP.openWCPSettings();
		await merchantWCP.skipFraudProtectionTour();
		await merchantWCP.openAdvancedFraudProtectionSettings();

		const rulesToToggle = [
			'international-ip-address-card',
			'ip-address-mismatch',
			'address-mismatch-card',
			'purchase-price-threshold-card',
			'order-items-threshold-card',
		];

		for ( const rule of rulesToToggle ) {
			const ruleSelector = `#${ rule } .is-checked .components-form-toggle__input`;

			const ruleToggle = await page.$( ruleSelector );

			if ( ruleToggle ) {
				await page.$eval( ruleSelector, ( method ) => method.click() );
			}
		}
	},

	toggleFraudProtectionRule: async ( ruleName, settings = null ) => {
		await page.$eval(
			`#${ ruleName }-card .components-form-toggle__input`,
			( method ) => method.click()
		);

		if ( settings ) {
			for ( const [ selector, value ] of Object.entries( settings ) ) {
				const settingInput = await page.waitForSelector( selector );

				await settingInput.click( { clickCount: 3 } );
				await page.keyboard.press( 'Backspace' );

				if ( '' !== value ) {
					await page.keyboard.type( value );
				}

				await page.waitFor( 500 );
			}
		}

		await expect( page ).toClick(
			'.fraud-protection-advanced-settings__footer button'
		);

		await page.waitForSelector( '.components-snackbar' );
		await expect( page ).toMatchElement( '.components-snackbar', {
			text: 'Settings saved',
			timeout: 60000,
		} );
	},
};
