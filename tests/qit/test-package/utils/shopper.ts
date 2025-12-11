/**
 * External dependencies
 */
import { Locator, Page, expect } from 'playwright/test';
/**
 * Internal dependencies
 */
import * as navigation from './shopper-navigation';
import { config, CustomerAddress, Product } from '../config/default';
import { isUIUnblocked } from './helpers';

/**
 * Generic condition-based waiting helper.
 * Polls for a condition to become true instead of guessing at timing.
 *
 * @param condition Function that returns truthy value when condition is met
 * @param description Human-readable description for error messages
 * @param timeoutMs Maximum time to wait in milliseconds
 * @param pollIntervalMs How often to check the condition
 * @return The truthy value returned by condition
 */
async function waitFor< T >(
	condition: () => Promise< T > | T,
	description: string,
	timeoutMs = 30000,
	pollIntervalMs = 100
): Promise< T > {
	const startTime = Date.now();

	while ( true ) {
		const result = await condition();
		if ( result ) {
			return result;
		}

		if ( Date.now() - startTime > timeoutMs ) {
			throw new Error(
				`Timeout waiting for ${ description } after ${ timeoutMs }ms`
			);
		}

		await new Promise( ( r ) => setTimeout( r, pollIntervalMs ) );
	}
}

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

const orderConfirmationTimeout = 30_000;

const isLocatorVisible = async ( locator: Locator ) => {
	try {
		return await locator.isVisible();
	} catch ( _error ) {
		return false;
	}
};

export const waitForOrderConfirmationWCB = async ( page: Page ) => {
	const orderReceivedHeading = page
		.getByRole( 'heading', { name: 'Order received' } )
		.first();
	const orderConfirmationHeading = page
		.getByRole( 'heading', { name: 'Order confirmation' } )
		.first();
	const thankYouNotice = page
		.locator( '.woocommerce-notice.woocommerce-notice--success' )
		.first();

	await new Promise< void >( ( resolve, reject ) => {
		let settled = false;
		const timer = setTimeout( () => {
			if ( settled ) {
				return;
			}
			settled = true;
			reject(
				new Error(
					'Timed out waiting for the Blocks checkout confirmation view.'
				)
			);
		}, orderConfirmationTimeout );

		const handleSuccess = () => {
			if ( settled ) {
				return;
			}
			settled = true;
			clearTimeout( timer );
			resolve();
		};

		page.waitForURL( /\/order-received\//, {
			timeout: orderConfirmationTimeout,
		} )
			.then( handleSuccess )
			.catch( () => undefined );
		orderReceivedHeading
			.waitFor( {
				state: 'visible',
				timeout: orderConfirmationTimeout,
			} )
			.then( handleSuccess )
			.catch( () => undefined );
		orderConfirmationHeading
			.waitFor( {
				state: 'visible',
				timeout: orderConfirmationTimeout,
			} )
			.then( handleSuccess )
			.catch( () => undefined );
		thankYouNotice
			.waitFor( {
				state: 'visible',
				timeout: orderConfirmationTimeout,
			} )
			.then( handleSuccess )
			.catch( () => undefined );
	} );

	if ( await isLocatorVisible( orderReceivedHeading ) ) {
		await expect( orderReceivedHeading ).toBeVisible();
		return;
	}

	if ( await isLocatorVisible( orderConfirmationHeading ) ) {
		await expect( orderConfirmationHeading ).toBeVisible();
		return;
	}

	await expect( thankYouNotice ).toBeVisible();
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
		await waitForOrderConfirmationWCB( page );
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

	// Wait for payment methods to be loaded and UI to be stable
	await isUIUnblocked( page );

	// Check which payment element type is being used (UPE vs Classic)
	const isUPE = await page.$(
		'#payment .payment_method_woocommerce_payments .wcpay-upe-element'
	);

	if ( isUPE ) {
		// UPE flow - wait for UPE iframe to be created by Stripe
		const frameHandle = await waitFor(
			async () =>
				page.$(
					'#payment .payment_method_woocommerce_payments .wcpay-upe-element iframe'
				),
			'UPE Stripe iframe to be created',
			30000
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
		// Classic flow - wait for classic Stripe iframe to be created
		// This polls for the iframe instead of guessing at timeout
		const frameHandle = await waitFor(
			async () =>
				page.$(
					'#payment #wcpay-card-element iframe[name^="__privateStripeFrame"]'
				),
			'Classic Stripe iframe to be created (waiting for Stripe.js load + account data + Elements init)',
			30000
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

const stripeChallengeAppearTimeout = 8_000;
const stripeChallengeBodyTimeout = 8_000;

export const confirmCardAuthentication = async (
	page: Page,
	authorize = true
) => {
	// Allow the Stripe modal to mount if it is going to show up.
	await page.waitForTimeout( 1_000 );

	// Stripe card input also uses __privateStripeFrame as a prefix, so need to make sure we wait for an iframe that
	// appears at the top of the DOM. If it never appears, skip gracefully.
	const privateFrame = page.locator(
		'body > div > iframe[name^="__privateStripeFrame"]'
	);
	const appeared = await privateFrame
		.waitFor( {
			state: 'visible',
			timeout: stripeChallengeAppearTimeout,
		} )
		.then( () => true )
		.catch( () => false );
	if ( ! appeared ) return;

	const stripeFrame = page.frameLocator(
		'body>div>iframe[name^="__privateStripeFrame"]'
	);
	if ( ! stripeFrame ) return;

	const challengeFrame = stripeFrame.frameLocator(
		'iframe[name="stripe-challenge-frame"]'
	);
	// If challenge frame never appears, assume frictionless and return.
	try {
		await challengeFrame.locator( 'body' ).waitFor( {
			state: 'visible',
			timeout: stripeChallengeBodyTimeout,
		} );
	} catch ( _e ) {
		return;
	}

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
	// If on WC Blocks checkout, wait for it to finish loading before interacting.
	// The blocks checkout shows "Loading..." text while updating.
	const blocksOrderSummary = page.locator(
		'.wc-block-components-order-summary'
	);
	if ( ( await blocksOrderSummary.count() ) > 0 ) {
		await expect( blocksOrderSummary ).not.toContainText( 'Loading', {
			timeout: 15000,
		} );
	}

	const checkbox = page.getByLabel(
		'Save payment information to my account for future purchases.'
	);

	// Wait for checkbox to be visible and stable before interacting.
	await expect( checkbox ).toBeVisible( { timeout: 10000 } );

	// Check current state first - if already in desired state, skip.
	const isChecked = await checkbox.isChecked();
	if ( isChecked === save ) {
		return; // Already in desired state.
	}

	// Use click() instead of setChecked() for better reliability with React components.
	// setChecked() fails if React re-renders and resets the checkbox state during the click.
	await checkbox.click();

	// Wait a moment for React to process the state change.
	await page.waitForTimeout( 500 );

	// Verify the checkbox is now in the expected state.
	const newState = await checkbox.isChecked();
	if ( newState !== save ) {
		// Retry once if state didn't change.
		await checkbox.click( { force: true } );
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
	// Wait for the page to be stable and the payment method list to render
	await page.waitForLoadState( 'domcontentloaded' );
	await isUIUnblocked( page );
	await expect(
		page.locator( 'input[name="payment_method"]' ).first()
	).toBeVisible( { timeout: 10000 } );

	await page.getByText( 'Card', { exact: true } ).click();

	// Wait for Stripe iframe to be ready
	const frameHandle = page.locator(
		'iframe[name^="__privateStripeFrame"][title="Secure payment input frame"]'
	);
	await expect( frameHandle ).toBeVisible( { timeout: 15000 } );
	const stripeFrame = frameHandle.contentFrame();

	if ( ! stripeFrame ) {
		throw new Error( 'Stripe iframe not found' );
	}

	// Fill card details with waits between fields.
	const cardNumberInput = stripeFrame.getByPlaceholder(
		'1234 1234 1234 1234'
	);
	await expect( cardNumberInput ).toBeVisible( { timeout: 10000 } );
	await cardNumberInput.fill( card.number );

	const expiryInput = stripeFrame.getByPlaceholder( 'MM / YY' );
	await expiryInput.fill( card.expires.month + card.expires.year );

	const cvcInput = stripeFrame.getByPlaceholder( 'CVC' );
	await cvcInput.fill( card.cvc );

	// Select country.
	const countrySelect = stripeFrame.getByRole( 'combobox', {
		name: /country/i,
	} );
	await countrySelect.selectOption( country );

	// Fill ZIP code after country is selected (ZIP field may appear after country selection).
	const zip = stripeFrame.getByLabel( /ZIP/i );
	if ( ( await zip.count() ) > 0 ) {
		await zip.fill( zipCode ?? '90210' );
	}

	// Click outside the iframe to ensure Stripe finishes processing.
	await page.locator( 'h1' ).first().click();
	await page.waitForTimeout( 500 );

	// Click the submit button.
	const submitButton = page.getByRole( 'button', {
		name: 'Add payment method',
	} );
	await expect( submitButton ).toBeEnabled( { timeout: 5000 } );
	await submitButton.click();

	// Wait for one of the expected outcomes:
	//  - 3DS modal appears (Stripe iframe)
	//  - Success notice
	//  - Error notice (any WooCommerce alert including declines)
	//  - Redirect back to Payment methods page
	const threeDSFrame = page.locator(
		'body > div > iframe[name^="__privateStripeFrame"]'
	);
	const successNotice = page.getByText(
		'Payment method successfully added.'
	);
	const methodsHeading = page.getByRole( 'heading', {
		name: 'Payment methods',
	} );
	// Wait for any WooCommerce error notice (role="alert")
	const errorAlert = page.getByRole( 'alert' );

	// Wait for navigation or any expected outcome.
	await Promise.race( [
		threeDSFrame.waitFor( { state: 'visible', timeout: 30000 } ),
		successNotice.waitFor( { state: 'visible', timeout: 30000 } ),
		errorAlert.waitFor( { state: 'visible', timeout: 30000 } ),
		methodsHeading.waitFor( { state: 'visible', timeout: 30000 } ),
	] ).catch( () => {
		/* ignore and let the caller continue; downstream assertions will catch real issues */
	} );
};

export const deleteSavedCard = async (
	page: Page,
	card: typeof config.cards.basic
) => {
	// Ensure UI is ready and table rendered
	await isUIUnblocked( page );
	await expect(
		page.getByRole( 'heading', { name: 'Payment methods' } )
	).toBeVisible( { timeout: 10000 } );

	// Saved methods are listed in a table in most themes; prefer the role=row
	// but fall back to a simpler text-based locator if table semantics differ.
	let row = page.getByRole( 'row', { name: card.label } ).first();
	const rowVisible = await row.isVisible().catch( () => false );
	if ( ! rowVisible ) {
		row = page
			.locator( 'tr, li, div' )
			.filter( { hasText: card.label } )
			.first();
	}
	await expect( row ).toBeVisible( { timeout: 20000 } );
	const button = row.getByRole( 'link', { name: 'Delete' } );
	await expect( button ).toBeVisible( { timeout: 10000 } );
	await expect( button ).toBeEnabled( { timeout: 10000 } );
	await button.click();

	// After clicking delete, wait for one of these to confirm deletion:
	// - success notice
	// - the row to be removed
	const successNotice = page.getByText( 'Payment method deleted.' );
	try {
		await Promise.race( [
			successNotice.waitFor( { state: 'visible', timeout: 20000 } ),
			row.waitFor( { state: 'detached', timeout: 20000 } ),
		] );
	} catch ( _e ) {
		// ignore; callers will assert expected state
	}
};

export const selectSavedCardOnCheckout = async (
	page: Page,
	card: typeof config.cards.basic
) => {
	// Prefer the full "label (expires mm/yy)" text, but fall back to the label-only
	// in environments where the expiry text may not be present in the option label.
	let option = page
		.getByText(
			`${ card.label } (expires ${ card.expires.month }/${ card.expires.year })`
		)
		.first();
	const found = await option.isVisible().catch( () => false );
	if ( ! found ) {
		option = page.getByText( card.label ).first();
	}
	await expect( option ).toBeVisible( { timeout: 15000 } );
	await option.click();
};

export const setDefaultPaymentMethod = async (
	page: Page,
	card: typeof config.cards.basic
) => {
	const row = page.getByRole( 'row', { name: card.label } ).first();
	await expect( row ).toBeVisible( { timeout: 10000 } );

	// Some themes/plugins render this as a link or a button; support both.
	const makeDefault = row
		.getByRole( 'link', { name: 'Make default' } )
		.or( row.getByRole( 'button', { name: 'Make default' } ) );

	// If the card is already default, the control might be missing; bail gracefully.
	if ( ! ( await makeDefault.count() ) ) {
		return;
	}

	await expect( makeDefault ).toBeVisible( { timeout: 10000 } );
	await expect( makeDefault ).toBeEnabled( { timeout: 10000 } );
	await makeDefault.click();
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
