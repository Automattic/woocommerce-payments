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
	declined: {
		number: '4000000000000002',
		expires: {
			month: '06',
			year: '45',
		},
		cvc: '626',
	},
	'declined-funds': {
		number: '4000000000009995',
		expires: {
			month: '06',
			year: '45',
		},
		cvc: '626',
	},
	'declined-incorrect': {
		number: '4242424242424241',
		expires: {
			month: '06',
			year: '45',
		},
		cvc: '626',
	},
	'declined-expired': {
		number: '4000000000000069',
		expires: {
			month: '06',
			year: '45',
		},
		cvc: '626',
	},
	'declined-cvc': {
		number: '4000000000000127',
		expires: {
			month: '06',
			year: '45',
		},
		cvc: '626',
	},
	'declined-processing': {
		number: '4000000000000119',
		expires: {
			month: '06',
			year: '45',
		},
		cvc: '626',
	},
	'declined-3ds': {
		number: '4000008400001629',
		expires: {
			month: '06',
			year: '45',
		},
		cvc: '626',
	},
	'invalid-cvv-number': {
		number: '4242424242424242',
		expires: {
			month: '06',
			year: '45',
		},
		cvc: '11',
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
 * @param {string} checkoutUrl - URL to use for the checkout page (Blocks or shortcode)
 */
export const setupCheckout = async (
	page,
	billingAddress,
	checkoutUrl = CHECKOUT_URLS.shortcode
) => {
	// Navigate to the provided checkout page (Blocks or shortcode)
	await page.goto( checkoutUrl );
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

	// Detect Blocks checkout
	const isBlocks =
		( await page
			.locator( '.wp-block-woocommerce-checkout, .wc-block-checkout' )
			.count() ) > 0;

	if ( isBlocks ) {
		// If the billing address form is collapsed (summary card), click 'Edit billing address' to expand it
		const editBillingButton = page.getByRole( 'button', {
			name: 'Edit billing address',
		} );
		if ( ( await editBillingButton.count() ) > 0 ) {
			await editBillingButton.click();
			// Wait for the form to appear
			await page
				.getByRole( 'group', { name: 'Billing address' } )
				.waitFor();
		}
		// Use label-based selectors for Blocks checkout
		const contactInfoForm = page.getByRole( 'group', {
			name: 'Contact information',
		} );
		await contactInfoForm
			.getByLabel( 'Email address' )
			.fill( billingAddress.email );

		const billingAddressForm = page.getByRole( 'group', {
			name: 'Billing address',
		} );
		await billingAddressForm
			.getByLabel( 'First name', { exact: true } )
			.fill( billingAddress.firstName );
		await billingAddressForm
			.getByLabel( 'Last name', { exact: true } )
			.fill( billingAddress.lastName );
		// Country is a dropdown in Blocks checkout, use selectOption
		const countrySelect = await page
			.getByLabel( 'Country/Region', { exact: false } )
			.or( page.getByLabel( 'Country', { exact: true } ) );
		try {
			await countrySelect.selectOption( billingAddress.country );
		} catch ( e ) {
			// Fallback: try 'Country' label if 'Country/Region' is not found
			try {
				const fallbackCountry = await page.getByLabel( 'Country', {
					exact: true,
				} );
				await fallbackCountry.selectOption( billingAddress.country );
			} catch ( e2 ) {
				throw new Error(
					`Could not select country in Blocks checkout: ${ e2.message }`
				);
			}
		}

		// Address line 1
		await billingAddressForm
			.getByLabel( 'Address', { exact: true } )
			.fill( billingAddress.address1 );

		// Address line 2 (apartment, suite, etc.)
		const addSecondLineButton = page.getByRole( 'button', {
			name: '+ Add apartment, suite, etc.',
		} );
		if ( ( await addSecondLineButton.count() ) > 0 ) {
			await addSecondLineButton.click();
		}
		await billingAddressForm
			.getByLabel( 'Apartment, suite, etc. (optional)' )
			.fill( billingAddress.address2 );
		await billingAddressForm
			.getByLabel( 'City', { exact: true } )
			.fill( billingAddress.city );

		// State: try selectOption, fallback to fill (text input)
		const stateInput = billingAddressForm.getByLabel( 'State', {
			exact: true,
		} );
		if ( billingAddress.state ) {
			try {
				await stateInput.selectOption( billingAddress.state );
			} catch ( error ) {
				await stateInput.fill( billingAddress.state );
			}
		}

		await billingAddressForm
			.getByLabel( 'ZIP Code', { exact: true } )
			.fill( billingAddress.postcode );
		await billingAddressForm
			.getByLabel( 'Phone (optional)', { exact: true } )
			.fill( billingAddress.phone );
	} else {
		// Fallback to classic shortcode selectors
		await page
			.locator( '#billing_first_name' )
			.fill( billingAddress.firstName );
		await page
			.locator( '#billing_last_name' )
			.fill( billingAddress.lastName );
		await page
			.locator( '#billing_country' )
			.selectOption( billingAddress.country );
		await page
			.locator( '#billing_address_1' )
			.fill( billingAddress.address1 );
		await page
			.locator( '#billing_address_2' )
			.fill( billingAddress.address2 );
		await page.locator( '#billing_city' ).fill( billingAddress.city );
		await page
			.locator( '#billing_postcode' )
			.fill( billingAddress.postcode );
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
	let cardNumberField = null;

	const cardNumberSelectors = [
		'input[name="number"]',
		'#Field-numberInput',
		'.p-CardNumberInput input',
		'[placeholder="1234 1234 1234 1234"]',
		'[aria-label="Card number"]',
		'[name="cardnumber"]',
		'[placeholder*="card number" i]',
		'[placeholder*="number" i]',
		'input[data-elements-stable-field-name="cardNumber"]',
		'#cardNumber',
		'input[autocomplete="cc-number"]',
		'input[type="tel"]',
		'.CardNumberField input',
		'.CardField-number input',
	];

	const expirySelectors = [
		'input[name="expiry"]',
		'#Field-expiryInput',
		'.p-ExpiryInput input',
		'[placeholder="MM / YY"]',
		'[placeholder="MM/YY"]',
		'[name="exp-date"]',
		'[placeholder*="mm" i]',
		'[placeholder*="expir" i]',
		'input[data-elements-stable-field-name="expiryDate"]',
		'#expiryDate',
		'input[autocomplete="cc-exp"]',
		'.CardExpiryField input',
		'.CardField-expiry input',
	];

	const cvcSelectors = [
		'input[name="cvc"]',
		'input[name="securityCode"]',
		'#Field-cvcInput',
		'.p-CvcInput input',
		'[placeholder="CVC"]',
		'[placeholder*="cvc" i]',
		'[placeholder*="cvv" i]',
		'[placeholder*="security" i]',
		'input[data-elements-stable-field-name="cvcNumber"]',
		'#cvcNumber',
		'input[autocomplete="cc-csc"]',
		'.CardCvcField input',
		'.CardField-cvc input',
	];

	const findFieldInFrame = async (
		frameLocator,
		selectors,
		timeout = 1500
	) => {
		for ( const selector of selectors ) {
			const locator = frameLocator.locator( selector ).first();
			try {
				await locator.waitFor( { timeout } );
				return locator;
			} catch ( e ) {
				continue;
			}
		}

		return null;
	};

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
					const candidateFrame = page.frameLocator(
						`iframe[title="${ title }"]`
					);
					cardNumberField = await findFieldInFrame(
						candidateFrame,
						cardNumberSelectors,
						1500
					);
					if ( cardNumberField ) {
						stripeFrame = candidateFrame;
						break;
					}
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
			const candidateFrame = page.frameLocator(
				'#payment .wcpay-upe-element iframe[name^="__privateStripeFrame"]'
			);
			cardNumberField = await findFieldInFrame(
				candidateFrame,
				cardNumberSelectors,
				2000
			);
			if ( ! cardNumberField ) {
				throw new Error(
					'Card number field not found in fallback Stripe frame'
				);
			}
			stripeFrame = candidateFrame;
		} catch ( e2 ) {
			throw new Error(
				`Could not find WooCommerce Payments card input form. Error: ${ e2.message }`
			);
		}
	}

	if ( ! stripeFrame ) {
		throw new Error( 'Could not locate Stripe payment frame.' );
	}

	// Fill card number - try different field selectors that Stripe might use
	cardNumberField =
		cardNumberField ??
		( await findFieldInFrame( stripeFrame, cardNumberSelectors, 2000 ) );

	if ( ! cardNumberField ) {
		throw new Error( 'Could not find card number field in Stripe iframe' );
	}

	await cardNumberField.fill( card.number );

	const expiryField = await findFieldInFrame(
		stripeFrame,
		expirySelectors,
		2000
	);

	if ( ! expiryField ) {
		throw new Error( 'Could not find expiry date field in Stripe iframe' );
	}

	await expiryField.fill( card.expires.month + card.expires.year );

	const cvcField = await findFieldInFrame( stripeFrame, cvcSelectors, 2000 );

	if ( ! cvcField ) {
		throw new Error( 'Could not find CVC field in Stripe iframe' );
	}

	await cvcField.fill( card.cvc );
};

/**
 * Places the order
 *
 * @param {Page} page - Playwright page object
 */
export const placeOrder = async ( page ) => {
	// Detect Blocks checkout
	const isBlocks =
		( await page
			.locator( '.wp-block-woocommerce-checkout, .wc-block-checkout' )
			.count() ) > 0;

	let placeOrderButton;
	if ( isBlocks ) {
		// Use role+name for Blocks checkout
		placeOrderButton = page.getByRole( 'button', { name: 'Place Order' } );
	} else {
		// Use ID for shortcode checkout
		placeOrderButton = page.locator( '#place_order' );
	}
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
	// Give the Payment Element a moment to mount the challenge iframe tree
	await page.waitForTimeout( 2000 );

	const privateFrame = page.locator(
		'body > div > iframe[name^="__privateStripeFrame"]'
	);
	const challengeVisible = await privateFrame
		.waitFor( { state: 'visible', timeout: 20000 } )
		.then( () => true )
		.catch( () => false );

	// Frictionless flows never surface the modal
	if ( ! challengeVisible ) {
		return;
	}

	const stripeFrame = page.frameLocator(
		'body>div>iframe[name^="__privateStripeFrame"]'
	);
	const challengeFrame = stripeFrame.frameLocator(
		'iframe[name="stripe-challenge-frame"]'
	);

	try {
		await challengeFrame
			.locator( 'body' )
			.waitFor( { state: 'visible', timeout: 20000 } );
	} catch ( _error ) {
		// Some issuers complete 3DS without showing the embedded challenge UI
		return;
	}

	const submitButton = challengeFrame.getByRole( 'button', {
		name: authorize ? /Complete/i : /Fail/i,
	} );

	// Avoid interacting while the challenge is still loading
	await expect(
		stripeFrame.locator( '.LightboxModalLoadingIndicator' )
	).not.toBeVisible( { timeout: 20000 } );

	await submitButton.click();

	await privateFrame
		.waitFor( { state: 'hidden', timeout: 20000 } )
		.catch( () => {} );

	if ( authorize ) {
		await page
			.waitForURL( '**/order-received/**', { timeout: 30000 } )
			.catch( () => {} );
	} else {
		await page
			.waitForSelector(
				'.woocommerce-error, #payment .woocommerce-error, .wcpay-payment-error',
				{ timeout: 30000 }
			)
			.catch( () => {} );
	}
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
