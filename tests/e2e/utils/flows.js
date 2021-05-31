/**
 * @format
 */

/**
 * External dependencies
 */

const { merchant, verifyAndPublish } = require( '@woocommerce/e2e-utils' );

const config = require( 'config' );
const baseUrl = config.get( 'url' );

const SHOP_MY_ACCOUNT_PAGE = baseUrl + 'my-account/';
const MY_ACCOUNT_PAYMENT_METHODS = baseUrl + 'my-account/payment-methods';
const WCPAY_DISPUTES =
	baseUrl + 'wp-admin/admin.php?page=wc-admin&path=/payments/disputes';
const WCPAY_DEPOSITS =
	baseUrl + 'wp-admin/admin.php?page=wc-admin&path=/payments/deposits';
const WCPAY_TRANSACTIONS =
	baseUrl + 'wp-admin/admin.php?page=wc-admin&path=/payments/transactions';
const WC_SUBSCRIPTIONS_PAGE =
	baseUrl + 'wp-admin/edit.php?post_type=shop_subscription';

export const RUN_SUBSCRIPTIONS_TESTS =
	'1' !== process.env.SKIP_WC_SUBSCRIPTIONS_TESTS;

/**
 * Internal dependencies
 */
import { uiLoaded } from './helpers';

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
};

// The generic flows will be moved to their own package soon (more details in p7bje6-2gV-p2), so we're
// keeping our customizations grouped here so it's easier to extend the flows once the move happens.
export const merchantWCP = {
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

	openDisputes: async () => {
		await page.goto( WCPAY_DISPUTES, {
			waitUntil: 'networkidle0',
		} );
		await expect( page ).toMatchElement( 'h2', { text: 'Disputes' } );
	},

	openDeposits: async () => {
		await page.goto( WCPAY_DEPOSITS, {
			waitUntil: 'networkidle0',
		} );
		await expect( page ).toMatchElement( 'h2', {
			text: 'Deposit history',
		} );
	},

	openTransactions: async () => {
		await page.goto( WCPAY_TRANSACTIONS, {
			waitUntil: 'networkidle0',
		} );
		await expect( page ).toMatchElement( 'h2', { text: 'Transactions' } );
	},

	viewDisputeFromOrder: async () => {
		const paymentDetailsLink = await page.$eval(
			'p.order_number > a',
			( anchor ) => anchor.getAttribute( 'href' )
		);
		await Promise.all( [
			page.goto( paymentDetailsLink, {
				waitUntil: 'networkidle0',
			} ),
			uiLoaded(),
		] );
		await uiLoaded();
		await expect( page ).toClick(
			'div.woocommerce-timeline-item__body > span > a',
			{
				text: 'View dispute',
			}
		);
		await page.waitForNavigation( { waitUntil: 'networkidle0' } );
		await uiLoaded();
	},
};
