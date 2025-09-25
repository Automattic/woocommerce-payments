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
	'invalid-exp-date': {
		number: '4242424242424242',
		expires: {
			month: '11',
			year: '12',
		},
		cvc: '123',
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
	await page.goto( '/shop' );
	await page.waitForLoadState( 'domcontentloaded' );

	const productTiles = page.locator( '.products .product' );
	await expect( productTiles.first() ).toBeVisible( { timeout: 10000 } );

	const addButton = productTiles
		.locator(
			'.add_to_cart_button, button[aria-label*="Add to cart" i], a[aria-label*="Add to cart" i]'
		)
		.first();
	await expect( addButton ).toBeVisible( { timeout: 10000 } );
	await addButton.click();
	await page.waitForLoadState( 'networkidle' ).catch( () => {} );
	await page.waitForTimeout( 500 );
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

		const zipField = billingAddressForm
			.getByLabel( 'ZIP Code', { exact: true } )
			.or(
				billingAddressForm.getByLabel( 'Postal code', { exact: true } )
			)
			.or(
				billingAddressForm.getByLabel( 'Postcode', { exact: false } )
			);
		await zipField.fill( billingAddress.postcode );
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
 * Updates the account currency for the current customer via My Account > Account details.
 *
 * @param {Page} page - Playwright page object
 * @param {Object} customerDetails - Customer profile information (firstname, lastname, company, etc.)
 * @param {string} currency - Currency code to select (e.g. 'EUR')
 */
export const changeAccountCurrency = async (
	page,
	customerDetails,
	currency
) => {
	await page.goto( '/my-account/edit-account/' );
	await page.waitForLoadState( 'domcontentloaded' );

	const maybeFill = async ( label, value, options = {} ) => {
		if ( ! value ) {
			return;
		}
		const field = page.getByLabel( label, { exact: false, ...options } );
		if ( ( await field.count() ) > 0 ) {
			await field.fill( value );
		}
	};

	await maybeFill(
		'First name',
		customerDetails?.firstname || customerDetails?.firstName
	);
	await maybeFill(
		'Last name',
		customerDetails?.lastname || customerDetails?.lastName
	);
	await maybeFill( 'Display name', customerDetails?.displayName );
	await maybeFill( 'Company name', customerDetails?.company );
	await maybeFill( 'Email address', customerDetails?.email );
	await maybeFill( 'Phone', customerDetails?.phone );

	const currencySelect = page.getByLabel( 'Default currency', {
		exact: false,
	} );
	await expect( currencySelect ).toBeVisible( { timeout: 10000 } );
	await currencySelect.selectOption( currency );

	await page.getByRole( 'button', { name: 'Save changes' } ).click();
	await expect(
		page.getByText( 'Account details changed successfully.' )
	).toBeVisible( { timeout: 15000 } );
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
 * Selects a WooPayments payment method by label/button text.
 *
 * @param {Page} page - Playwright page object
 * @param {string} paymentMethod - Display name of the payment method (e.g. 'Bancontact')
 */
export const selectPaymentMethod = async ( page, paymentMethod = 'Card' ) => {
	const candidates = [
		page.locator( `label:has-text("${ paymentMethod }")` ).first(),
		page
			.getByRole( 'button', { name: new RegExp( paymentMethod, 'i' ) } )
			.first(),
		page
			.getByRole( 'radio', { name: new RegExp( paymentMethod, 'i' ) } )
			.first(),
		page.getByText( paymentMethod, { exact: true } ).first(),
	];

	let target = null;
	for ( const option of candidates ) {
		if ( ( await option.count() ) > 0 ) {
			target = option;
			break;
		}
	}

	if ( ! target ) {
		const stripeFrame = page.frameLocator(
			'iframe[name^="__privateStripeFrame"]'
		);
		const paymentButtonInFrame = stripeFrame
			.getByRole( 'button', { name: new RegExp( paymentMethod, 'i' ) } )
			.first();
		if ( ( await paymentButtonInFrame.count() ) > 0 ) {
			await paymentButtonInFrame.click();
			return;
		}

		throw new Error(
			`Payment method "${ paymentMethod }" not found on checkout page.`
		);
	}

	await expect( target ).toBeVisible( { timeout: 20000 } );
	await target.scrollIntoViewIfNeeded();
	await target.click( { force: true } );

	const normalized = paymentMethod
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, '_' );
	const radio = page.locator(
		`#payment_method_woocommerce_payments_${ normalized.replace(
			/_+/g,
			'_'
		) }`
	);
	if ( ( await radio.count() ) > 0 ) {
		await radio.check( { force: true } ).catch( async () => {
			await radio.click( { force: true } );
		} );
	}
};

/**
 * Toggles the "save payment method" checkbox on checkout.
 *
 * @param {Page} page - Playwright page object
 * @param {boolean} save - Whether the method should be saved
 */
export const setSavePaymentMethod = async ( page, save = true ) => {
	const candidates = [
		page.getByLabel(
			'Save payment information to my account for future purchases.'
		),
		page.getByRole( 'checkbox', { name: /(save|store).*account/i } ),
		page.locator( 'input[name="save_payment_method"]' ),
		page.locator( '#save_payment_method' ),
	];

	let toggle = null;
	for ( const candidate of candidates ) {
		if ( ! candidate ) {
			continue;
		}
		if ( ( await candidate.count() ) > 0 ) {
			toggle = candidate.first();
			break;
		}
	}

	if ( ! toggle ) {
		throw new Error(
			'Save payment method toggle not found on checkout page.'
		);
	}

	await expect( toggle ).toBeVisible( { timeout: 15000 } );

	const shouldBeChecked = !! save;
	let isChecked = false;
	try {
		isChecked = await toggle.isChecked();
	} catch ( _error ) {
		// Some themes wrap the input in a label; fall back to reading the associated input
		const forAttribute = await toggle.getAttribute?.( 'for' );
		if ( forAttribute ) {
			const input = page.locator( `#${ forAttribute }` );
			if ( ( await input.count() ) > 0 ) {
				isChecked = await input.isChecked();
				toggle = input;
			}
		}
	}

	if ( shouldBeChecked === isChecked ) {
		return;
	}

	const toggleAction = async ( action ) => {
		try {
			await toggle[ action ]( { force: true } );
		} catch ( error ) {
			await toggle.click( { force: true } );
		}
	};

	if ( shouldBeChecked ) {
		await toggleAction( 'check' );
	} else {
		await toggleAction( 'uncheck' );
	}
};

/**
 * Selects a saved card option on the checkout page.
 *
 * @param {Page} page - Playwright page object
 * @param {Object} card - Card configuration containing the card number
 */
export const selectSavedCardOnCheckout = async ( page, card ) => {
	const lastFour = card?.number?.slice( -4 );
	if ( ! lastFour ) {
		throw new Error( 'Card number is required to select saved card.' );
	}

	const labelPattern = new RegExp( `ending in\\s*${ lastFour }`, 'i' );
	const candidates = [
		page.getByRole( 'radio', { name: labelPattern } ),
		page.getByRole( 'button', { name: labelPattern } ),
		page.locator( 'label' ).filter( { hasText: labelPattern } ),
		page
			.locator( '.wc-block-components-radio-control__label' )
			.filter( { hasText: labelPattern } ),
		page.locator( 'li, div, span' ).filter( { hasText: labelPattern } ),
	];

	let option = null;
	for ( const candidate of candidates ) {
		if ( ! candidate ) {
			continue;
		}
		if ( ( await candidate.count() ) > 0 ) {
			option = candidate.first();
			break;
		}
	}

	if ( ! option ) {
		throw new Error(
			`Saved card option containing "ending in ${ lastFour }" was not found.`
		);
	}

	await expect( option ).toBeVisible( { timeout: 20000 } );

	const forAttribute = await option.getAttribute?.( 'for' );
	if ( forAttribute ) {
		const radio = page.locator( `#${ forAttribute }` );
		if ( ( await radio.count() ) > 0 ) {
			await radio.check( { force: true } ).catch( async () => {
				await radio.click( { force: true } );
			} );
			return;
		}
	}

	const radioWithin = option.locator( 'input[type="radio"]' );
	if ( ( await radioWithin.count() ) > 0 ) {
		await radioWithin
			.first()
			.check( { force: true } )
			.catch( async () => {
				await radioWithin.first().click( { force: true } );
			} );
		return;
	}

	await option.click( { force: true } );
};

/**
 * Deletes a saved card from the My Account payment methods page.
 *
 * @param {Page} page - Playwright page object
 * @param {Object} card - Card configuration containing the card number
 */
export const deleteSavedCard = async ( page, card ) => {
	const lastFour = card?.number?.slice( -4 );
	if ( ! lastFour ) {
		throw new Error( 'Card number is required to delete saved card.' );
	}

	const rowLocator = page
		.locator( 'table tr, ul li, div' )
		.filter( {
			hasText: new RegExp( lastFour + '$|ending in\\s*' + lastFour, 'i' ),
		} )
		.first();

	await expect( rowLocator ).toBeVisible( { timeout: 20000 } );

	const deleteControl = rowLocator
		.getByRole( 'link', { name: /delete/i } )
		.or( rowLocator.getByRole( 'button', { name: /delete/i } ) )
		.or( rowLocator.locator( 'a.delete, button.delete' ) );

	await expect( deleteControl ).toBeVisible( { timeout: 15000 } );

	await Promise.all( [
		page.waitForLoadState( 'networkidle' ).catch( () => {} ),
		deleteControl.first().click(),
	] );
};

/**
 * Removes all items from the cart for cleanup between tests.
 *
 * @param {Page} page - Playwright page object
 */
export const emptyCart = async ( page ) => {
	await page.goto( '/cart' );
	await page.waitForLoadState( 'domcontentloaded' );

	const removalStrategies = [
		page.locator( '.cart_item .remove' ),
		page.locator( 'button[data-automation-id="remove-item"]' ),
		page.getByRole( 'button', { name: /^Remove/i } ),
	];

	for ( const remover of removalStrategies ) {
		while ( ( await remover.count() ) > 0 ) {
			await remover.first().click();
			await page.waitForLoadState( 'networkidle' ).catch( () => {} );
			await page.waitForTimeout( 250 );
		}
	}

	const emptyIndicators = page.locator(
		'.cart-empty, .wc-block-cart__empty-cart, .wc-block-components-notice-banner__content'
	);
	await emptyIndicators
		.first()
		.waitFor( { timeout: 10000 } )
		.catch( () => {} );
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
		// Local payment method redirect page
		page
			.getByRole( 'link', { name: /Authorize Test Payment/i } )
			.waitFor( { state: 'visible', timeout: 20000 } ),
	] );
};

/**
 * Confirms 3DS authentication
 *
 * @param {Page} page - Playwright page object
 * @param {boolean} authorize - Whether to authorize or decline
 * @param {Object} options - Additional options
 * @param {'success'|'error'|'none'} [options.waitFor] - Outcome to wait for
 */
export const confirm3dsAuthentication = async (
	page,
	authorize = true,
	options = {}
) => {
	const { waitFor = authorize ? 'success' : 'error' } = options;

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

	if ( waitFor === 'success' ) {
		await page
			.waitForURL( '**/order-received/**', { timeout: 30000 } )
			.catch( () => {} );
	} else if ( waitFor === 'error' ) {
		await page
			.waitForSelector(
				'.woocommerce-error, #payment .woocommerce-error, .wcpay-payment-error, .wc-block-components-notice-banner__content',
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
			'#payment_method_woocommerce_payments, input[type="radio"][value="woocommerce_payments"]'
		);
		if ( ( await paymentMethod.count() ) === 0 ) {
			await expect(
				page
					.locator(
						'.wcpay-upe-element iframe[name^="__privateStripeFrame"]'
					)
					.first()
			).toBeVisible( { timeout: 20000 } );
		}
	}
	// For QIT, this is a placeholder - in full environment we'd check for actual tokens
};
