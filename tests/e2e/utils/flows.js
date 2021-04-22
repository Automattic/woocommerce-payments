/**
 * @format
 */

/**
 * External dependencies
 */
import { pressKeyWithModifier } from '@wordpress/e2e-test-utils';

const config = require( 'config' );
const baseUrl = config.get( 'url' );

const SHOP_PAGE = baseUrl + 'shop';
const SHOP_PRODUCT_PAGE = baseUrl + '?p=';
const SHOP_CART_PAGE = baseUrl + 'cart';
const SHOP_CHECKOUT_PAGE = baseUrl + 'checkout/';
const SHOP_MY_ACCOUNT_PAGE = baseUrl + 'my-account/';

const MY_ACCOUNT_ORDERS = baseUrl + 'my-account/orders';
const MY_ACCOUNT_DOWNLOADS = baseUrl + 'my-account/downloads';
const MY_ACCOUNT_ADDRESSES = baseUrl + 'my-account/edit-address';
const MY_ACCOUNT_ACCOUNT_DETAILS = baseUrl + 'my-account/edit-account';
const MY_ACCOUNT_PAYMENT_METHODS = baseUrl + 'my-account/payment-methods';

const getProductColumnExpression = ( productTitle ) =>
	'td[@class="product-name" and ' +
	`a[contains(text(), "${ productTitle }")]` +
	']';

const getQtyInputExpression = ( args = {} ) => {
	let qtyValue = '';

	if ( args.checkQty ) {
		qtyValue = ` and @value="${ args.qty }"`;
	}

	return 'input[contains(@class, "input-text")' + qtyValue + ']';
};

const getQtyColumnExpression = ( args ) =>
	'td[@class="product-quantity" and ' +
	'.//' +
	getQtyInputExpression( args ) +
	']';

const getCartItemExpression = ( productTitle, args ) =>
	'//tr[contains(@class, "cart_item") and ' +
	getProductColumnExpression( productTitle ) +
	' and ' +
	getQtyColumnExpression( args ) +
	']';

const getRemoveExpression = () =>
	'td[@class="product-remove"]//a[@class="remove"]';

// The generic flows will be moved to their own package soon (more details in p7bje6-2gV-p2), so we're
// keeping our customizations grouped here so it's easier to extend the flows once the move happens.
const PaymentsCustomerFlow = {
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

const CustomerFlow = {
	addToCart: async () => {
		await Promise.all( [
			page.waitForNavigation( { waitUntil: 'networkidle0' } ),
			page.click( '.single_add_to_cart_button' ),
		] );
	},

	addToCartFromShopPage: async ( productTitle ) => {
		const addToCartXPath =
			`//li[contains(@class, "type-product") and a/h2[contains(text(), "${ productTitle }")]]` +
			'//a[contains(@class, "add_to_cart_button") and contains(@class, "ajax_add_to_cart")';

		const [ addToCartButton ] = await page.$x( addToCartXPath + ']' );
		addToCartButton.click();

		await page.waitFor(
			addToCartXPath + ' and contains(@class, "added")]'
		);
	},

	goToCheckout: async () => {
		await page.goto( SHOP_CHECKOUT_PAGE, {
			waitUntil: 'networkidle0',
		} );
	},

	goToOrders: async () => {
		await page.goto( MY_ACCOUNT_ORDERS, {
			waitUntil: 'networkidle0',
		} );
	},

	goToDownloads: async () => {
		await page.goto( MY_ACCOUNT_DOWNLOADS, {
			waitUntil: 'networkidle0',
		} );
	},

	goToAddresses: async () => {
		await page.goto( MY_ACCOUNT_ADDRESSES, {
			waitUntil: 'networkidle0',
		} );
	},

	goToAccountDetails: async () => {
		await page.goto( MY_ACCOUNT_ACCOUNT_DETAILS, {
			waitUntil: 'networkidle0',
		} );
	},

	goToProduct: async ( postID ) => {
		await page.goto( SHOP_PRODUCT_PAGE + postID, {
			waitUntil: 'networkidle0',
		} );
	},

	goToShop: async () => {
		await page.goto( SHOP_PAGE, {
			waitUntil: 'networkidle0',
		} );
	},

	placeOrder: async () => {
		await Promise.all( [
			expect( page ).toClick( '#place_order' ),
			page.waitForNavigation( { waitUntil: 'networkidle0' } ),
		] );
	},

	productIsInCheckout: async (
		productTitle,
		quantity,
		total,
		cartSubtotal
	) => {
		await expect( page ).toMatchElement( '.product-name', {
			text: productTitle,
		} );
		await expect( page ).toMatchElement( '.product-quantity', {
			text: quantity,
		} );
		await expect( page ).toMatchElement( '.product-total .amount', {
			text: total,
		} );
		await expect( page ).toMatchElement( '.cart-subtotal .amount', {
			text: cartSubtotal,
		} );
	},

	goToCart: async () => {
		await page.goto( SHOP_CART_PAGE, {
			waitUntil: 'networkidle0',
		} );
	},

	login: async () => {
		await page.goto( SHOP_MY_ACCOUNT_PAGE, {
			waitUntil: 'networkidle0',
		} );

		await expect( page.title() ).resolves.toMatch( 'My account' );

		await page.type( '#username', config.get( 'users.customer.username' ) );
		await page.type( '#password', config.get( 'users.customer.password' ) );

		await Promise.all( [
			page.waitForNavigation( { waitUntil: 'networkidle0' } ),
			page.click( 'button[name="login"]' ),
		] );
	},

	productIsInCart: async ( productTitle, quantity = null ) => {
		const cartItemArgs = quantity ? { qty: quantity } : {};
		const cartItemXPath = getCartItemExpression(
			productTitle,
			cartItemArgs
		);

		await expect( page.$x( cartItemXPath ) ).resolves.toHaveLength( 1 );
	},

	fillBillingDetails: async ( customerBillingDetails ) => {
		await expect( page ).toFill(
			'#billing_first_name',
			customerBillingDetails.firstname
		);
		await expect( page ).toFill(
			'#billing_last_name',
			customerBillingDetails.lastname
		);
		await expect( page ).toFill(
			'#billing_company',
			customerBillingDetails.company
		);
		await expect( page ).toSelect(
			'#billing_country',
			customerBillingDetails.country
		);
		await expect( page ).toFill(
			'#billing_address_1',
			customerBillingDetails.addressfirstline
		);
		await expect( page ).toFill(
			'#billing_address_2',
			customerBillingDetails.addresssecondline
		);
		await expect( page ).toFill(
			'#billing_city',
			customerBillingDetails.city
		);
		await expect( page ).toSelect(
			'#billing_state',
			customerBillingDetails.state
		);
		await expect( page ).toFill(
			'#billing_postcode',
			customerBillingDetails.postcode
		);
		await expect( page ).toFill(
			'#billing_phone',
			customerBillingDetails.phone
		);
		await expect( page ).toFill(
			'#billing_email',
			customerBillingDetails.email
		);
	},

	fillShippingDetails: async ( customerShippingDetails ) => {
		await expect( page ).toFill(
			'#shipping_first_name',
			customerShippingDetails.firstname
		);
		await expect( page ).toFill(
			'#shipping_last_name',
			customerShippingDetails.lastname
		);
		await expect( page ).toFill(
			'#shipping_company',
			customerShippingDetails.company
		);
		await expect( page ).toSelect(
			'#shipping_country',
			customerShippingDetails.country
		);
		await expect( page ).toFill(
			'#shipping_address_1',
			customerShippingDetails.addressfirstline
		);
		await expect( page ).toFill(
			'#shipping_address_2',
			customerShippingDetails.addresssecondline
		);
		await expect( page ).toFill(
			'#shipping_city',
			customerShippingDetails.city
		);
		await expect( page ).toSelect(
			'#shipping_state',
			customerShippingDetails.state
		);
		await expect( page ).toFill(
			'#shipping_postcode',
			customerShippingDetails.postcode
		);
	},

	removeFromCart: async ( productTitle ) => {
		const cartItemXPath = getCartItemExpression( productTitle );
		const removeItemXPath = cartItemXPath + '//' + getRemoveExpression();

		const [ removeButton ] = await page.$x( removeItemXPath );
		await removeButton.click();
	},

	setCartQuantity: async ( productTitle, quantityValue ) => {
		const cartItemXPath = getCartItemExpression( productTitle );
		const quantityInputXPath =
			cartItemXPath + '//' + getQtyInputExpression();

		const [ quantityInput ] = await page.$x( quantityInputXPath );
		await quantityInput.focus();
		await pressKeyWithModifier( 'primary', 'a' );
		await quantityInput.type( quantityValue.toString() );
	},

	...PaymentsCustomerFlow,
};

export { CustomerFlow };
