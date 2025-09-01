/**
 * External dependencies
 */
import { Page, expect } from 'playwright/test';
/**
 * Internal dependencies
 */
import * as navigation from './shopper-navigation';
import { config, CustomerAddress, Product } from '../config/default';
import { isUIUnblocked } from './helpers';

/**
 * Waits for the UI to refresh after a user interaction.
 *
 * Woo core blocks and refreshes the UI after 1s after each key press
 * in a text field or immediately after a select field changes.
 * We need to wait to make sure that all key presses were processed by that mechanism.
 */
export const waitForUiRefresh = ( page: Page ) => page.waitForTimeout( 1000 );

/**
 * Takes off the focus out of the Stripe elements to let Stripe logic
 * wrap up and make sure the Place Order button is clickable.
 */
export const focusPlaceOrderButton = async ( page: Page ) => {
	await page.locator( '#place_order' ).focus();
	await waitForUiRefresh( page );
};

export const fillBillingAddress = async (
	page: Page,
	billingAddress: CustomerAddress
) => {
	await page
		.locator( '#billing_first_name' )
		.fill( billingAddress.firstname );
	await page.locator( '#billing_last_name' ).fill( billingAddress.lastname );
	await page.locator( '#billing_company' ).fill( billingAddress.company );
	await page
		.locator( '#billing_country' )
		.selectOption( billingAddress.country );
	await page
		.locator( '#billing_address_1' )
		.fill( billingAddress.addressfirstline );
	await page
		.locator( '#billing_address_2' )
		.fill( billingAddress.addresssecondline );
	await page.locator( '#billing_city' ).fill( billingAddress.city );
	if ( billingAddress.state ) {
		// Setting the state is optional, relative to the selected country. E.g Selecting Belgium hides the state input.
		await page
			.locator( '#billing_state' )
			.selectOption( billingAddress.state );
	}
	await page.locator( '#billing_postcode' ).fill( billingAddress.postcode );
	await page.locator( '#billing_phone' ).fill( billingAddress.phone );
	await page.locator( '#billing_email' ).fill( billingAddress.email );
};

export const fillBillingAddressWCB = async (
	page: Page,
	billingAddress: CustomerAddress
) => {
	const editBillingAddressButton = page.getByLabel( 'Edit billing address' );
	if ( await editBillingAddressButton.isVisible() ) {
		await editBillingAddressButton.click();
	}
	const billingAddressForm = page.getByRole( 'group', {
		name: 'Billing address',
	} );

	const countryField = billingAddressForm.getByLabel( 'Country/Region' );

	try {
		await countryField.selectOption( billingAddress.country );
	} catch ( error ) {
		// Fallback for WC 7.7.0.
		await countryField.focus();
		await countryField.fill( billingAddress.country );

		await page
			.locator( '.components-form-token-field__suggestion' )
			.first()
			.click();
	}

	await billingAddressForm
		.getByLabel( 'First Name' )
		.fill( billingAddress.firstname );
	await billingAddressForm
		.getByLabel( 'Last Name' )
		.fill( billingAddress.firstname );
	await billingAddressForm
		.getByLabel( 'Company (optional)' )
		.fill( billingAddress.company );
	await billingAddressForm
		.getByLabel( 'Address', { exact: true } )
		.fill( billingAddress.addressfirstline );
	const addSecondLineButton = page.getByRole( 'button', {
		name: '+ Add apartment, suite, etc.',
	} );
	if ( ( await addSecondLineButton.count() ) > 0 ) {
		await addSecondLineButton.click();
	}
	await billingAddressForm
		.getByLabel( 'Apartment, suite, etc. (optional)' )
		.fill( billingAddress.addresssecondline );
	await billingAddressForm.getByLabel( 'City' ).fill( billingAddress.city );

	const stateInput = billingAddressForm.getByLabel( 'State', {
		exact: true,
	} );
	if ( billingAddress.state ) {
		try {
			await stateInput.selectOption( billingAddress.state );
		} catch ( error ) {
			// Fallback for WC 7.7.0.
			await stateInput.fill( billingAddress.state );
		}
	}
	await billingAddressForm
		.getByLabel( 'ZIP Code' )
		.fill( billingAddress.postcode );
	await billingAddressForm
		.getByLabel( 'Phone (optional)' )
		.fill( billingAddress.phone );
};

// This is currently the source of some flaky tests since sometimes the form is not submitted
// after the first click, so we retry until the ui is blocked.
export const placeOrder = async ( page: Page ) => {
	let orderPlaced = false;
	while ( ! orderPlaced ) {
		await page.locator( '#place_order' ).click();

		if ( await page.$( '.blockUI' ) ) {
			orderPlaced = true;
		}
	}
};

export const placeOrderWCB = async (
	page: Page,
	confirmOrderReceived = true
) => {
	const placeOrderButton = page.getByRole( 'button', {
		name: 'Place Order',
	} );

	await placeOrderButton.focus();
	await waitForUiRefresh( page );

	await placeOrderButton.click();

	if ( confirmOrderReceived ) {
		await page.waitForURL( /\/order-received\// );
		await expect(
			page.getByRole( 'heading', { name: 'Order received' } )
		).toBeVisible();
	}
};

const ensureSavedCardNotSelected = async ( page: Page ) => {
	if (
		await page
			.locator( '#wc-woocommerce_payments-payment-token-new' )
			.isVisible()
	) {
		const newCardOption = await page.locator(
			'#wc-woocommerce_payments-payment-token-new'
		);
		if ( newCardOption ) {
			await newCardOption.click();
		}
	}
};

export const fillCardDetails = async (
	page: Page,
	card = config.cards.basic
) => {
	await ensureSavedCardNotSelected( page );
	if (
		await page.$(
			'#payment .payment_method_woocommerce_payments .wcpay-upe-element'
		)
	) {
		const frameHandle = await page.waitForSelector(
			'#payment .payment_method_woocommerce_payments .wcpay-upe-element iframe'
		);

		const stripeFrame = await frameHandle.contentFrame();

		if ( ! stripeFrame ) return;

		await stripeFrame.locator( '[name="number"]' ).fill( card.number );

		await stripeFrame
			.locator( '[name="expiry"]' )
			.fill( card.expires.month + card.expires.year );

		await stripeFrame.locator( '[name="cvc"]' ).fill( card.cvc );

		const zip = stripeFrame.locator( '[name="postalCode"]' );

		if ( await zip.isVisible() ) {
			await zip.fill( '90210' );
		}
	} else {
		const frameHandle = await page.waitForSelector(
			'#payment #wcpay-card-element iframe[name^="__privateStripeFrame"]'
		);
		const stripeFrame = await frameHandle.contentFrame();

		if ( ! stripeFrame ) return;

		await stripeFrame.locator( '[name="cardnumber"]' ).fill( card.number );

		await stripeFrame
			.locator( '[name="exp-date"]' )
			.fill( card.expires.month + card.expires.year );

		await stripeFrame.locator( '[name="cvc"]' ).fill( card.cvc );
	}
};

export const fillCardDetailsWCB = async (
	page: Page,
	card: typeof config.cards.basic
) => {
	const newPaymentMethodRadioButton = page.locator(
		'#radio-control-wc-payment-method-options-woocommerce_payments'
	);
	if ( await newPaymentMethodRadioButton.isVisible() ) {
		await newPaymentMethodRadioButton.click();
	}
	await page.waitForSelector( '.__PrivateStripeElement' );
	const frameHandle = await page.waitForSelector(
		'#payment-method .wcpay-payment-element iframe[name^="__privateStripeFrame"]'
	);
	const stripeFrame = await frameHandle.contentFrame();
	if ( ! stripeFrame ) return;
	await stripeFrame.getByPlaceholder( '1234 1234 1234' ).fill( card.number );
	await stripeFrame
		.getByPlaceholder( 'MM / YY' )
		.fill( card.expires.month + card.expires.year );

	await stripeFrame.getByPlaceholder( 'CVC' ).fill( card.cvc );
};

export const confirmCardAuthentication = async (
	page: Page,
	authorize = true
) => {
	// Wait for the Stripe modal to appear.
	await page.waitForTimeout( 5000 );

	// Stripe card input also uses __privateStripeFrame as a prefix, so need to make sure we wait for an iframe that
	// appears at the top of the DOM.
	await page.waitForSelector(
		'body > div > iframe[name^="__privateStripeFrame"]'
	);

	const stripeFrame = page.frameLocator(
		'body>div>iframe[name^="__privateStripeFrame"]'
	);
	if ( ! stripeFrame ) return;

	const challengeFrame = stripeFrame.frameLocator(
		'iframe[name="stripe-challenge-frame"]'
	);
	if ( ! challengeFrame ) return;

	const button = challengeFrame.getByRole( 'button', {
		name: authorize ? 'Complete' : 'Fail',
	} );

	await expect(
		stripeFrame.locator( '.LightboxModalLoadingIndicator' )
	).not.toBeVisible();

	await button.click();
};

/**
 * Retrieves the product price from the current product page.
 *
 * This function assumes that the page object has already navigated to a product page.
 */
export const getPriceFromProduct = async ( page: Page, slug: string ) => {
	await navigation.goToProductPageBySlug( page, slug );

	const priceText = await page
		.locator( 'ins .woocommerce-Price-amount.amount' )
		.first()
		.textContent();

	return priceText?.replace( /[^0-9.,]/g, '' ) ?? '';
};

/**
 * Adds a product to the cart from the shop page.
 *
 * @param {Page} page The Playwright page object.
 * @param {Product} product The product add to the cart.
 */
export const addToCartFromShopPage = async (
	page: Page,
	product: Product = config.products.simple,
	currency?: string
) => {
	await navigation.goToShop( page, {
		pageNumber: product.pageNumber,
		currency,
	} );

	// This generic regex will match the aria-label for the "Add to cart" button for any product.
	// It should work for WC 7.7.0 and later.
	// These unicode characters are the smart (or curly) quotes: “ ”.
	const addToCartRegex = new RegExp(
		`Add\\s+(?:to\\s+cart:\\s*)?\u201C${ product.name }\u201D(?:\\s+to\\s+your\\s+cart)?`
	);

	const addToCartButton = page.getByLabel( addToCartRegex );
	await addToCartButton.click();

	try {
		await expect( addToCartButton ).toHaveAttribute( 'class', /added/, {
			timeout: 5000,
		} );
	} catch ( error ) {
		// fallback for a different theme.
		await expect( addToCartButton ).toHaveText( /in cart/ );
	}
};

export const selectPaymentMethod = async (
	page: Page,
	paymentMethod = 'Card'
) => {
	// Wait for the page to be stable before attempting to select payment method
	// Use a more reliable approach than networkidle which can timeout
	await page.waitForLoadState( 'domcontentloaded' );

	// Ensure UI is not blocked
	await isUIUnblocked( page );

	// Wait for payment methods to be fully loaded and stable
	await page.waitForSelector( '.wc_payment_methods', { timeout: 10000 } );

	// Try to find and click the payment method with retry logic
	const maxRetries = 3;
	for ( let attempt = 1; attempt <= maxRetries; attempt++ ) {
		try {
			// Use a more robust locator that handles mixed content in labels
			// Look for the label containing the payment method text
			const paymentMethodElement = page
				.locator( `label:has-text("${ paymentMethod }")` )
				.first();

			// Wait for the element to be visible and stable
			await expect( paymentMethodElement ).toBeVisible( {
				timeout: 5000,
			} );

			// Ensure the element is in viewport
			await paymentMethodElement.scrollIntoViewIfNeeded();

			// Wait a bit more for any animations to complete
			await page.waitForTimeout( 200 );

			// Click the payment method
			await paymentMethodElement.click();

			// Wait a moment to ensure the click was processed
			await page.waitForTimeout( 100 );

			// If we get here, the click was successful
			break;
		} catch ( error ) {
			if ( attempt === maxRetries ) {
				throw error;
			}
			// Wait a bit before retrying
			await page.waitForTimeout( 1000 );
		}
	}
};

/**
 * The checkout page can sometimes be blank, so we need to reload it.
 *
 * @param page Page
 */
export const ensureCheckoutIsLoaded = async ( page: Page ) => {
	if ( ! ( await page.locator( '#billing_first_name' ).isVisible() ) ) {
		await page.reload();
	}
};

export const setupCheckout = async (
	page: Page,
	billingAddress: CustomerAddress = config.addresses.customer.billing
) => {
	await navigation.goToCheckout( page );
	await ensureCheckoutIsLoaded( page );
	await fillBillingAddress( page, billingAddress );
	await waitForUiRefresh( page );
	await isUIUnblocked( page );
};

/**
 * Sets up checkout with any number of products.
 *
 * @param {Array<[string, number]>} lineItems A 2D array of line items where each line item is an array
 * that contains the product title as the first element, and the quantity as the second.
 * For example, if you want to checkout x2 "Hoodie" and x3 "Belt" then set this parameter like this:
 *
 * `[ [ "Hoodie", 2 ], [ "Belt", 3 ] ]`.
 * @param {CustomerAddress} billingAddress The billing address to use for the checkout.
 */
export async function setupProductCheckout(
	page: Page,
	lineItems: Array< [ Product, number ] > = [ [ config.products.simple, 1 ] ],
	billingAddress: CustomerAddress = config.addresses.customer.billing,
	currency?: string
) {
	await navigation.goToShop( page );

	const cartSizeText = await page
		.locator( '.cart-contents .count' )
		.textContent();
	let cartSize = Number( cartSizeText?.replace( /\D/g, '' ) ?? '0' );

	for ( const line of lineItems ) {
		let [ product, qty ] = line;

		while ( qty-- ) {
			await addToCartFromShopPage( page, product, currency );

			// Make sure the number of items in the cart is incremented before adding another item.
			await expect( page.locator( '.cart-contents .count' ) ).toHaveText(
				new RegExp( `${ ++cartSize } items?` ),
				{
					timeout: 30000,
				}
			);

			// Wait for the cart to update before adding another item.
			await page.waitForTimeout( 500 );
		}
	}

	await setupCheckout( page, billingAddress );
}

export const expectFraudPreventionToken = async (
	page: Page,
	toBeDefined: boolean
) => {
	const token = await page.evaluate( () => {
		return ( window as any ).wcpayFraudPreventionToken;
	} );

	if ( toBeDefined ) {
		expect( token ).toBeDefined();
	} else {
		expect( token ).toBeUndefined();
	}
};

/**
 * Places an order with custom options.
 *
 * @param  page The Playwright page object.
 * @param  options The custom options to use for the order.
 * @return The order ID.
 */
export const placeOrderWithOptions = async (
	page: Page,
	options?: {
		product?: Product;
		billingAddress?: CustomerAddress;
		createAccount?: boolean;
	}
) => {
	await navigation.goToShop( page );
	await addToCartFromShopPage( page, options?.product );
	await setupCheckout( page, options?.billingAddress );
	if (
		options?.createAccount &&
		( await page.getByLabel( 'Create an account?' ).isVisible() )
	) {
		await page.getByLabel( 'Create an account?' ).check();
	}
	await selectPaymentMethod( page );
	await fillCardDetails( page, config.cards.basic );
	await focusPlaceOrderButton( page );
	await placeOrder( page );
	await page.waitForURL( /\/order-received\//, {
		waitUntil: 'load',
	} );
	await expect(
		page.getByRole( 'heading', { name: 'Order received' } )
	).toBeVisible();

	const url = await page.url();
	return url.match( /\/order-received\/(\d+)\// )?.[ 1 ] ?? '';
};

/**
 * Places an order with a specified currency.
 *
 * @param {Page} page The Playwright page object.
 * @param {string} currency The currency code to use for the order.
 * @return {Promise<string>} The order ID.
 */
export const placeOrderWithCurrency = async (
	page: Page,
	currency: string
) => {
	await navigation.goToShop( page, { currency } );
	return placeOrderWithOptions( page );
};

export const setSavePaymentMethod = async ( page: Page, save = true ) => {
	const checkbox = page.getByLabel(
		'Save payment information to my account for future purchases.'
	);

	const isChecked = await checkbox.isChecked();

	if ( save && ! isChecked ) {
		await checkbox.check();
	} else if ( ! save && isChecked ) {
		await checkbox.uncheck();
	}
};

export const emptyCart = async ( page: Page ) => {
	await navigation.goToCart( page );

	// Remove products if they exist.
	let products = await page.locator( '.remove' ).all();

	while ( products.length ) {
		await products[ 0 ].click();
		await isUIUnblocked( page );

		products = await page.locator( '.remove' ).all();
	}

	// Remove coupons if they exist.
	let coupons = await page.locator( '.woocommerce-remove-coupon' ).all();

	while ( coupons.length ) {
		await coupons[ 0 ].click();
		await isUIUnblocked( page );

		coupons = await page.locator( '.woocommerce-remove-coupon' ).all();
	}

	await expect(
		page.getByText( 'Your cart is currently empty.' )
	).toBeVisible();
};

export const changeAccountCurrency = async (
	page: Page,
	customerDetails: any,
	currency: string
) => {
	await navigation.goToMyAccount( page, 'edit-account' );
	await page.getByLabel( 'First name *' ).fill( customerDetails.firstname );
	await page.getByLabel( 'Last name *' ).fill( customerDetails.lastname );
	await page.getByLabel( 'Default currency' ).selectOption( currency );
	await page.getByRole( 'button', { name: 'Save changes' } ).click();
	await expect(
		page.getByText( 'Account details changed successfully.' )
	).toBeVisible();
};

export const addSavedCard = async (
	page: Page,
	card: typeof config.cards.basic,
	country: string,
	zipCode?: string
) => {
	await page.getByRole( 'link', { name: 'Add payment method' } ).click();

	// Wait for the page to be stable
	// Use a more reliable approach than networkidle which can timeout
	await page.waitForLoadState( 'domcontentloaded' );
	// Ensure UI is not blocked
	await isUIUnblocked( page );

	await page.getByText( 'Card', { exact: true } ).click();
	const frameHandle = page.getByTitle( 'Secure payment input frame' );
	const stripeFrame = frameHandle.contentFrame();

	if ( ! stripeFrame ) return;

	await stripeFrame
		.getByPlaceholder( '1234 1234 1234 1234' )
		.fill( card.number );

	await stripeFrame
		.getByPlaceholder( 'MM / YY' )
		.fill( card.expires.month + card.expires.year );

	await stripeFrame.getByPlaceholder( 'CVC' ).fill( card.cvc );
	await stripeFrame
		.getByRole( 'combobox', { name: 'country' } )
		.selectOption( country );
	const zip = stripeFrame.getByLabel( 'ZIP Code' );
	if ( zip ) await zip.fill( zipCode ?? '90210' );

	await page.getByRole( 'button', { name: 'Add payment method' } ).click();
};

export const deleteSavedCard = async (
	page: Page,
	card: typeof config.cards.basic
) => {
	const row = page.getByRole( 'row', { name: card.label } ).first();
	await expect( row ).toBeVisible( { timeout: 100 } );
	const button = row.getByRole( 'link', { name: 'Delete' } );
	await expect( button ).toBeVisible( { timeout: 100 } );
	await expect( button ).toBeEnabled( { timeout: 100 } );
	await button.click();
};

export const selectSavedCardOnCheckout = async (
	page: Page,
	card: typeof config.cards.basic
) => {
	const option = page
		.getByText(
			`${ card.label } (expires ${ card.expires.month }/${ card.expires.year })`
		)
		.first();
	await expect( option ).toBeVisible( { timeout: 100 } );
	await option.click();
};

export const setDefaultPaymentMethod = async (
	page: Page,
	card: typeof config.cards.basic
) => {
	const row = page.getByRole( 'row', { name: card.label } ).first();
	await expect( row ).toBeVisible( { timeout: 100 } );
	const button = row.getByRole( 'link', { name: 'Make default' } );
	await expect( button ).toBeVisible( { timeout: 100 } );
	await expect( button ).toBeEnabled( { timeout: 100 } );
	await button.click();
};

export const removeCoupon = async ( page: Page ) => {
	const couponRemovalLink = page.locator( '.woocommerce-remove-coupon' );

	if ( await couponRemovalLink.isVisible() ) {
		await couponRemovalLink.click();
		await expect(
			page.getByText( 'Coupon has been removed.' )
		).toBeVisible();
	}
};

/**
 * When using a 3DS card, call this function after clicking the 'Place order' button
 * to confirm the card authentication.
 *
 * @param  {Page}          page The Shopper page object.
 * @param  {boolean}       authorize Whether to authorize the transaction or not.
 * @return {Promise<void>}      Void.
 */
export const confirmCardAuthenticationWCB = async (
	page: Page,
	authorize = true
): Promise< void > => {
	const placeOrderButton = page.locator(
		'.wc-block-components-checkout-place-order-button'
	);
	await expect( placeOrderButton ).toBeDisabled();
	/**
	 * Starting around version 9.9.0 WooCommerce Blocks class names changed to
	 * be more specific. To cover both case, this check allows for additional
	 * sections in the "loading" class name.
	 */
	await expect( placeOrderButton ).toHaveClass(
		/\bwc-block-components-(?:[-\w]+-)?button--loading\b/
	);
	await confirmCardAuthentication( page, authorize );
};
