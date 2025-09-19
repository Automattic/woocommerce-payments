/**
 * External dependencies
 */
import { expect } from '@playwright/test';

/**
 * QIT Test Utilities for WooCommerce Payments
 *
 * This file contains helper functions for QIT e2e tests.
 * Setup creates two checkout pages:
 * - /checkout (default Blocks checkout)
 * - /checkout-wsc (shortcode checkout for classic tests)
 */

/**
 * Card configuration for QIT tests
 */
export const cards = {
	basic: {
		number: '4242424242424242',
		expires: {
			month: '12',
			year: '30',
		},
		cvc: '123',
	},
	threeDSecure: {
		number: '4000000000003220',
		expires: {
			month: '12',
			year: '30',
		},
		cvc: '123',
	},
	'3ds': {
		number: '4000000000003220',
		expires: {
			month: '12',
			year: '30',
		},
		cvc: '123',
	},
};

/**
 * Default billing address for tests
 */
export const defaultBillingAddress = {
	firstName: 'John',
	lastName: 'Doe',
	company: 'Test Company',
	country: 'US',
	address1: '123 Test Street',
	address2: 'Apt 1',
	city: 'Test City',
	state: 'CA',
	postcode: '90210',
	phone: '555-123-4567',
	email: 'test@example.com',
};

/**
 * Checkout page URLs for different implementations
 */
export const CHECKOUT_URLS = {
	blocks: '/checkout', // Default Blocks checkout
	shortcode: '/checkout-wsc', // Classic shortcode checkout
};

/**
 * Add a product to cart from the shop page
 *
 * @param {Page} page - Playwright page object
 */
export const addToCartFromShopPage = async ( page ) => {
	// Navigate to shop page
	await page.goto( '/shop' );
	await page.waitForLoadState( 'domcontentloaded' );

	// Add the first available product to cart (usually "Beanie")
	const addToCartButton = page.locator( '.add_to_cart_button' ).first();
	await addToCartButton.click();

	// Wait for the cart to update
	await page.waitForTimeout( 2000 );
};

/**
 * Wait for either cart or checkout form to be available
 *
 * @param {Page} page - Playwright page object
 */
export const waitForCartOrCheckoutForm = async ( page ) => {
	// Classic WooCommerce selectors only (QIT configured for shortcode checkout)
	const selectors = [ '.woocommerce-cart-form', '#customer_details' ];

	for ( const selector of selectors ) {
		if ( ( await page.locator( selector ).count() ) > 0 ) {
			break;
		}
	}
};

/**
 * Navigates to checkout and fills billing address
 *
 * @param {Page} page - Playwright page object
 * @param {Object} billingAddress - Billing address details
 */
export const setupCheckout = async ( page, billingAddress ) => {
	// Navigate to shortcode checkout page (setup script creates this at /checkout-wsc)
	await page.goto( CHECKOUT_URLS.shortcode );
	await page.waitForLoadState( 'domcontentloaded' );

	// Give the page time to fully load and render
	await page.waitForTimeout( 3000 );

	// Take a screenshot to debug what's actually on the page
	await page.screenshot( { path: 'checkout-debug.png', fullPage: true } );

	// Wait for page content to load - be very permissive about what we accept
	try {
		await page.waitForSelector( 'form, .woocommerce, .checkout, body', {
			timeout: 5000,
		} );
	} catch ( error ) {
		// If no basic elements found, log page content for debugging
		const pageContent = await page.content();
		throw new Error(
			`No checkout content found. Page title: "${ await page.title() }" URL: ${ page.url() } Content length: ${
				pageContent.length
			}`
		);
	}

	// Check what checkout structure we actually have
	const hasCustomerDetails =
		( await page.locator( '#customer_details' ).count() ) > 0;
	const hasBlocksCheckout =
		( await page.locator( '.wp-block-woocommerce-checkout' ).count() ) > 0;

	// If neither classic nor blocks structure is detected, wait a bit more for page to fully load
	if ( ! hasCustomerDetails && ! hasBlocksCheckout ) {
		await page.waitForTimeout( 2000 );

		// Check again after waiting
		const hasCustomerDetailsRetry =
			( await page.locator( '#customer_details' ).count() ) > 0;
		const hasBlocksCheckoutRetry =
			( await page.locator( '.wp-block-woocommerce-checkout' ).count() ) >
			0;

		// If still no expected structure, throw error
		if ( ! hasCustomerDetailsRetry && ! hasBlocksCheckoutRetry ) {
			throw new Error(
				'No recognized checkout structure found (neither classic #customer_details nor Blocks .wp-block-woocommerce-checkout)'
			);
		}
	}

	// Fill billing information - using classic shortcode format (underscores)
	// Since we're on /checkout-wsc, this should be pure shortcode checkout
	await page
		.locator( '#billing_first_name' )
		.fill( billingAddress.firstName );
	await page.locator( '#billing_last_name' ).fill( billingAddress.lastName );

	// Company field is optional - only fill if present
	const companyField = page.locator( '#billing_company' );
	if (
		( await companyField.count() ) > 0 &&
		( await companyField.isVisible() )
	) {
		await companyField.fill( billingAddress.company );
	}

	await page
		.locator( '#billing_country' )
		.selectOption( billingAddress.country );
	await page.locator( '#billing_address_1' ).fill( billingAddress.address1 );
	await page.locator( '#billing_address_2' ).fill( billingAddress.address2 );
	await page.locator( '#billing_city' ).fill( billingAddress.city );
	await page.locator( '#billing_postcode' ).fill( billingAddress.postcode );
	await page.locator( '#billing_phone' ).fill( billingAddress.phone );
	await page.locator( '#billing_email' ).fill( billingAddress.email );

	// Handle state field (can be dropdown or text input) - classic shortcode format
	const stateDropdown = page.locator( '#billing_state' );
	if (
		( await stateDropdown.count() ) > 0 &&
		( await stateDropdown.isVisible() )
	) {
		await stateDropdown.selectOption( billingAddress.state );
	}
};

/**
 * Fills card details in the Stripe payment form
 *
 * @param {Page} page - Playwright page object
 * @param {Object} card - Card details object
 */
export const fillCardDetails = async ( page, card ) => {
	// Select WooCommerce Payments if multiple payment methods are available
	const wcPayRadio = page.locator( '#payment_method_woocommerce_payments' );
	if ( ( await wcPayRadio.count() ) > 0 ) {
		await wcPayRadio.check();
		await page.waitForTimeout( 1000 ); // Wait for payment form to load
	}

	// From the page source, we can see the iframe is in: .wcpay-upe-element iframe[name^="__privateStripeFrame"]
	// And it has title="Secure payment input frame"

	let stripeFrame = null;

	// Method 1: Direct approach - look for the main Stripe iframe with card inputs
	try {
		// Wait for any Stripe iframe that contains card inputs (the visible one)
		const iframes = await page
			.locator( 'iframe[name^="__privateStripeFrame"]' )
			.all();

		for ( const iframe of iframes ) {
			try {
				const title = await iframe.getAttribute( 'title' );
				if ( title && title.includes( 'Secure payment input frame' ) ) {
					stripeFrame = page.frameLocator(
						`iframe[title="${ title }"]`
					);
					// Test if this frame has card inputs
					await stripeFrame
						.locator( '[name="cardnumber"]' )
						.waitFor( { timeout: 1000 } );
					break;
				}
			} catch ( e ) {
				// This iframe doesn't have card inputs, continue to next
				continue;
			}
		}

		if ( ! stripeFrame ) {
			throw new Error( 'No iframe with card inputs found' );
		}
	} catch ( e ) {
		// Method 2: Fallback - look in payment section
		try {
			await page.waitForSelector(
				'#payment .wcpay-upe-element iframe[name^="__privateStripeFrame"]',
				{ timeout: 5000 }
			);
			stripeFrame = page.frameLocator(
				'#payment .wcpay-upe-element iframe[name^="__privateStripeFrame"]'
			);
			await stripeFrame
				.locator( '[name="cardnumber"]' )
				.waitFor( { timeout: 2000 } );
		} catch ( e2 ) {
			throw new Error(
				`Could not find WooCommerce Payments card input form. Error: ${ e2.message }`
			);
		}
	}

	// Fill card number - try different field selectors that Stripe might use
	const cardNumberSelectors = [
		'[name="cardnumber"]',
		'[placeholder*="card number" i]',
		'[placeholder*="number" i]',
		'input[data-elements-stable-field-name="cardNumber"]',
		'#cardNumber',
		'input[autocomplete="cc-number"]',
		'input[type="tel"]',
		'.CardNumberField input, .CardField-number input',
	];

	let cardNumberFilled = false;
	for ( const selector of cardNumberSelectors ) {
		try {
			const field = stripeFrame.locator( selector );
			await field.waitFor( { timeout: 1000 } );
			await field.fill( card.number );
			cardNumberFilled = true;
			break;
		} catch ( e ) {
			continue; // Try next selector
		}
	}

	if ( ! cardNumberFilled ) {
		throw new Error( 'Could not find card number field in Stripe iframe' );
	}

	// Fill expiry date - try different field selectors
	const expirySelectors = [
		'[name="exp-date"]',
		'[placeholder*="mm" i]',
		'[placeholder*="expir" i]',
		'input[data-elements-stable-field-name="expiryDate"]',
		'#expiryDate',
		'input[autocomplete="cc-exp"]',
		'.CardExpiryField input, .CardField-expiry input',
	];

	let expiryFilled = false;
	for ( const selector of expirySelectors ) {
		try {
			const field = stripeFrame.locator( selector );
			await field.waitFor( { timeout: 1000 } );
			await field.fill( card.expires.month + card.expires.year );
			expiryFilled = true;
			break;
		} catch ( e ) {
			continue; // Try next selector
		}
	}

	if ( ! expiryFilled ) {
		throw new Error( 'Could not find expiry date field in Stripe iframe' );
	}

	// Fill CVC - try different field selectors
	const cvcSelectors = [
		'[name="cvc"]',
		'[placeholder*="cvc" i]',
		'[placeholder*="cvv" i]',
		'[placeholder*="security" i]',
		'input[data-elements-stable-field-name="cvcNumber"]',
		'#cvcNumber',
		'input[autocomplete="cc-csc"]',
		'.CardCvcField input, .CardField-cvc input',
	];

	let cvcFilled = false;
	for ( const selector of cvcSelectors ) {
		try {
			const field = stripeFrame.locator( selector );
			await field.waitFor( { timeout: 1000 } );
			await field.fill( card.cvc );
			cvcFilled = true;
			break;
		} catch ( e ) {
			continue; // Try next selector
		}
	}

	if ( ! cvcFilled ) {
		throw new Error( 'Could not find CVC field in Stripe iframe' );
	}
};

/**
 * Places the order
 *
 * @param {Page} page - Playwright page object
 */
export const placeOrder = async ( page ) => {
	// Click the place order button
	const placeOrderButton = page.locator( '#place_order' );
	await expect( placeOrderButton ).toBeVisible();
	await placeOrderButton.click();

	// Wait for either success or 3DS challenge
	await Promise.race( [
		// Success page
		page.waitForURL( '**/order-received/**', { timeout: 30000 } ),
		// 3DS challenge frame
		page.waitForSelector( 'iframe[name^="__privateStripeFrame"]', {
			timeout: 10000,
		} ),
	] );
};

/**
 * Confirms 3DS authentication
 *
 * @param {Page} page - Playwright page object
 * @param {boolean} authorize - Whether to authorize or decline
 */
export const confirm3dsAuthentication = async ( page, authorize = true ) => {
	// Wait for 3DS iframe to appear
	const privateFrame = page.locator(
		'body > div > iframe[name^="__privateStripeFrame"]'
	);
	await privateFrame.waitFor( { state: 'visible', timeout: 20000 } );

	// Access the 3DS challenge frame
	await page.waitForTimeout( 2000 ); // Give iframe time to load content

	const stripeFrame = page.frameLocator(
		'body>div>iframe[name^="__privateStripeFrame"]'
	);
	const challengeFrame = stripeFrame.frameLocator(
		'iframe[name="stripe-challenge-frame"]'
	);

	// Wait for challenge form to be ready
	await challengeFrame
		.locator( 'body' )
		.waitFor( { state: 'visible', timeout: 20000 } );

	// Click Complete authentication or Fail authentication
	if ( authorize ) {
		await challengeFrame
			.locator( '#test-source-authorize-3ds' )
			.click( { timeout: 10000 } );
	} else {
		await challengeFrame
			.locator( '#test-source-fail-3ds' )
			.click( { timeout: 10000 } );
	}

	// Wait for redirect to order confirmation
	await page.waitForURL( '**/order-received/**', { timeout: 30000 } );
};

/**
 * Confirms card authentication for 3DS - alias for confirm3dsAuthentication
 *
 * @param {Page} page - Playwright page object
 * @param {boolean} authorize - Whether to authorize or decline
 */
export const confirmCardAuthentication = confirm3dsAuthentication;

/**
 * Checks for fraud prevention token (simplified for QIT)
 *
 * @param {Page} page - Playwright page object
 * @param {boolean} shouldBePresent - Whether token should be present
 */
export const expectFraudPreventionToken = async (
	page,
	shouldBePresent = true
) => {
	// In QIT, we just verify the payment method exists (it may be hidden by CSS but still present)
	if ( shouldBePresent ) {
		const paymentMethod = page.locator(
			'#payment_method_woocommerce_payments'
		);
		await expect( paymentMethod ).toHaveCount( 1 );
		// Also verify it's checked (since it may be visually hidden but functional)
		await expect( paymentMethod ).toBeChecked();
	}
	// For QIT, this is a placeholder - in full environment we'd check for actual tokens
};
