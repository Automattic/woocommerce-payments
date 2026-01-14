/**
 * External dependencies
 */
import { Page, expect } from '@playwright/test';
import qit from '@qit/helpers';

import { config } from '../config/default';

type WidgetEntry = {
	id: string;
	id_base: string;
	instance?: unknown;
};

/**
 * Default theme used as fallback when active theme detection fails.
 */
const DEFAULT_THEME = 'twentytwentyfour';

const parseJson = < T >( value: string, fallback: T ): T => {
	try {
		return JSON.parse( value ) as T;
	} catch ( _error ) {
		return fallback;
	}
};

const chunkArray = < T >( items: T[], size: number ): T[][] => {
	if ( size <= 0 ) {
		return [ items ];
	}
	const chunks: T[][] = [];
	for ( let index = 0; index < items.length; index += size ) {
		chunks.push( items.slice( index, index + size ) );
	}
	return chunks;
};

const shouldRemoveWidget = ( widget: WidgetEntry ) => {
	if ( widget.id_base === 'currency_switcher_widget' ) {
		return true;
	}

	if ( widget.id_base === 'block' ) {
		const serialized = JSON.stringify( widget.instance ?? '' );
		return serialized.includes( 'currency-switcher-holder' );
	}

	return false;
};

export async function dataHasLoaded( page: Page ) {
	await expect( page.locator( '.is-loadable-placeholder' ) ).toHaveCount( 0 );
}

export const tableDataHasLoaded = async ( page: Page ) => {
	await page
		.locator( '.woocommerce-table__table.is-loading' )
		.waitFor( { state: 'hidden' } );
};

export const waitAndSkipTourComponent = async (
	page: Page,
	containerClass: string
) => {
	try {
		await page.waitForSelector( `${ containerClass }`, { timeout: 3000 } );
		if ( await page.isVisible( `${ containerClass }` ) ) {
			await page.click(
				`${ containerClass } button.woocommerce-tour-kit-step-controls__close-btn`
			);
		}
	} catch ( error ) {
		// Do nothing. The tour component being not present shouldn't cause the test to fail.
	}
};

export const ensureOrderIsProcessed = async ( page: Page ) => {
	// Sync the most recent order to WooCommerce Analytics tables.
	// We call the sync functions directly via PHP eval since the 'wc admin' CLI
	// command no longer exists in current WooCommerce versions.
	const syncCommand = `
		$order = wc_get_orders( array( 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC' ) )[0];
		if ( $order ) {
			$id = $order->get_id();
			Automattic\\WooCommerce\\Admin\\API\\Reports\\Orders\\Stats\\DataStore::sync_order( $id );
			Automattic\\WooCommerce\\Admin\\API\\Reports\\Products\\DataStore::sync_order_products( $id );
			Automattic\\WooCommerce\\Admin\\API\\Reports\\Customers\\DataStore::sync_order_customer( $id );
		}
	`;

	try {
		await qit.wp( `eval '${ syncCommand.replace( /'/g, `'"'"'` ) }'`, true );
	} catch ( error ) {
		// Sync may fail in some environments, continue anyway
	}

	// Brief wait for analytics to update
	await page.waitForTimeout( 2000 );
};

export const goToWooPaymentsSettings = async ( page: Page ) => {
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments',
		{ waitUntil: 'load' }
	);
	await dataHasLoaded( page );
};

export const goToTransactions = async ( page: Page ) => {
	await page.goto(
		'/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Ftransactions',
		{ waitUntil: 'load' }
	);
	await dataHasLoaded( page );
};

export const goToDisputes = async ( page: Page ) => {
	await page.goto(
		'/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Fdisputes',
		{ waitUntil: 'load' }
	);
	await dataHasLoaded( page );
};

export const goToOrderAnalytics = async ( page: Page ) => {
	await page.goto(
		'/wp-admin/admin.php?page=wc-admin&path=%2Fanalytics%2Forders',
		{ waitUntil: 'load' }
	);
	await dataHasLoaded( page );
};

export const goToPaymentsOverview = async ( page: Page ) => {
	await page.goto(
		'/wp-admin/admin.php?page=wc-admin&path=/payments/overview',
		{ waitUntil: 'load' }
	);
	await dataHasLoaded( page );
};

export const goToMultiCurrencyOnboarding = async ( page: Page ) => {
	await page.goto(
		'/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Fmulti-currency-setup',
		{ waitUntil: 'load' }
	);
	await dataHasLoaded( page );
};

export const goToMultiCurrencySettings = async ( page: Page ) => {
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=wcpay_multi_currency',
		{ waitUntil: 'load' }
	);
	await dataHasLoaded( page );
};

export const goToNewPost = async ( page: Page ) => {
	await page.goto( '/wp-admin/post-new.php', {
		waitUntil: 'load',
	} );
	await dataHasLoaded( page );
};

export const goToOrder = async ( page: Page, orderId: string ) => {
	await page.goto(
		`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }`,
		{
			waitUntil: 'load',
		}
	);
	await dataHasLoaded( page );
};

export const goToPaymentDetails = async (
	page: Page,
	paymentIntentId: string
) => {
	await page.goto(
		`/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Ftransactions%2Fdetails&id=${ paymentIntentId }`
	);
	await dataHasLoaded( page );
};

/**
 * Navigate to payment details for a specific order.
 * Extracts the payment intent ID from the order page and navigates to the payment details.
 *
 * @param page - The page object to use for navigation
 * @param orderId - The WooCommerce order ID
 * @return The URL of the payment details page
 */
export const goToPaymentDetailsForOrder = async (
	page: Page,
	orderId: string
): Promise< string > => {
	// Navigate to the order page
	await goToOrder( page, orderId );

	// Extract payment intent ID from order page
	const paymentIntentId = await page
		.locator( '#order_data' )
		.getByRole( 'link', {
			name: /pi_/,
		} )
		.innerText();

	// Navigate to payment details
	await goToPaymentDetails( page, paymentIntentId );
	await dataHasLoaded( page );

	// Return current URL for later use
	return page.url();
};

const goToWooCommerceGeneralSettings = async ( page: Page ) => {
	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=general', {
		waitUntil: 'load',
	} );
	await expect( page.locator( '#woocommerce_currency' ) ).toBeVisible();
};

const expectSnackbarWithText = async (
	page: Page,
	text: string,
	timeout = 10_000
) => {
	const snackbar = page
		.locator( '.components-snackbar__content', {
			hasText: text,
		} )
		.first();
	await expect( snackbar ).toBeVisible( { timeout } );
	await page.waitForTimeout( 2_000 );
};

const ensureSupportPhoneIsFilled = async ( page: Page ) => {
	if ( ! page.url().includes( '&section=woocommerce_payments' ) ) {
		return;
	}
	const supportPhoneInput = page.getByPlaceholder( 'Mobile number' );
	if (
		( await supportPhoneInput.count() ) &&
		( await supportPhoneInput.inputValue() ) === ''
	) {
		await supportPhoneInput.fill( '0000000000' );
	}
};

export const saveWooPaymentsSettings = async ( page: Page ) => {
	await ensureSupportPhoneIsFilled( page );
	await page.getByRole( 'button', { name: 'Save changes' } ).click();
	await expectSnackbarWithText( page, 'Settings saved.' );
};

export const isCaptureLaterEnabled = async ( page: Page ) => {
	await goToWooPaymentsSettings( page );

	const checkboxTestId = 'capture-later-checkbox';
	const isEnabled = await page.getByTestId( checkboxTestId ).isChecked();

	return isEnabled;
};

export const activateCaptureLater = async ( page: Page ) => {
	await goToWooPaymentsSettings( page );

	const checkboxTestId = 'capture-later-checkbox';
	const wasInitiallyEnabled = await page
		.getByTestId( checkboxTestId )
		.isChecked();

	if ( ! wasInitiallyEnabled ) {
		await page.getByTestId( checkboxTestId ).click();
		await page
			.getByRole( 'button', { name: 'Enable manual capture' } )
			.click();
		await page.getByRole( 'button', { name: 'Save changes' } ).click();
		await page.waitForTimeout( 1000 );
	}
	return wasInitiallyEnabled;
};

export const deactivateCaptureLater = async ( page: Page ) => {
	await goToWooPaymentsSettings( page );
	await page.getByTestId( 'capture-later-checkbox' ).uncheck();
	await page.getByRole( 'button', { name: 'Save changes' } ).click();
	await page.waitForTimeout( 1000 );
};

export const isWooPayEnabled = async ( page: Page ) => {
	await goToWooPaymentsSettings( page );

	const checkboxTestId = 'woopay-toggle';
	const isEnabled = await page.getByTestId( checkboxTestId ).isChecked();

	return isEnabled;
};

export const activateWooPay = async ( page: Page ) => {
	await goToWooPaymentsSettings( page );

	const checkboxTestId = 'woopay-toggle';
	const wasInitiallyEnabled = await isWooPayEnabled( page );

	if ( ! wasInitiallyEnabled ) {
		await page.getByTestId( checkboxTestId ).check();
		await saveWooPaymentsSettings( page );
	}
	return wasInitiallyEnabled;
};

export const deactivateWooPay = async ( page: Page ) => {
	await goToWooPaymentsSettings( page );
	await page.getByTestId( 'woopay-toggle' ).uncheck();
	await saveWooPaymentsSettings( page );
};

export const saveMultiCurrencySettings = async ( page: Page ) => {
	await page.getByRole( 'button', { name: 'Save changes' } ).click();
	await expectSnackbarWithText( page, 'Currency settings updated.' );
};

export const getDefaultCurrency = async ( page: Page ) => {
	await goToWooCommerceGeneralSettings( page );
	return await page.locator( '#woocommerce_currency' ).inputValue();
};

export const setDefaultCurrency = async (
	page: Page,
	currencyCode: string
) => {
	await goToWooCommerceGeneralSettings( page );
	const currencySelect = page.locator( '#woocommerce_currency' );
	const currentCurrency = await currencySelect.inputValue();

	if ( currentCurrency === currencyCode ) {
		return;
	}

	await currencySelect.selectOption( currencyCode );
	await page.getByRole( 'button', { name: 'Save changes' } ).click();
	const successNotice = page
		.locator( '.notice-success, .updated' )
		.filter( { hasText: 'Your settings have been saved.' } );
	await expect( successNotice ).toBeVisible();
};

export const isMulticurrencyEnabled = async ( page: Page ) => {
	await goToWooPaymentsSettings( page );
	return await page.getByTestId( 'multi-currency-toggle' ).isChecked();
};

export const activateMulticurrency = async ( page: Page ) => {
	await goToWooPaymentsSettings( page );
	const toggle = page.getByTestId( 'multi-currency-toggle' );
	const wasEnabled = await toggle.isChecked();

	if ( ! wasEnabled ) {
		await toggle.check();
		await saveWooPaymentsSettings( page );
	}

	return wasEnabled;
};

export const deactivateMulticurrency = async ( page: Page ) => {
	await goToWooPaymentsSettings( page );
	const toggle = page.getByTestId( 'multi-currency-toggle' );
	if ( await toggle.isChecked() ) {
		await toggle.uncheck();
		await saveWooPaymentsSettings( page );
	}
};

export const removeMultiCurrencyWidgets = async () => {
	// Avoid relying on `wp widget list --fields=...` because some WP-CLI
	// versions/environment may not expose `id_base` or `instance` as fields.
	// Instead run a small PHP eval that examines the widget options and
	// returns the widget ids to delete as a JSON array.
	const php = `
$ids = array();
$widgets = get_option( 'widget_currency_switcher_widget', array() );
foreach ( $widgets as $num => $inst ) {
	if ( $num === '_multiwidget' ) {
		continue;
	}
	$ids[] = 'currency_switcher_widget-' . $num;
}
$blocks = get_option( 'widget_block', array() );
foreach ( $blocks as $num => $inst ) {
	if ( $num === '_multiwidget' ) {
		continue;
	}
	$rendered = is_array( $inst ) ? wp_json_encode( $inst ) : strval( $inst );
	if ( strpos( $rendered, 'currency-switcher-holder' ) !== false ) {
		$ids[] = 'block-' . $num;
	}
}
echo wp_json_encode( array_values( array_unique( $ids ) ) );
`;

	const { stdout } = await qit.wp(
		`eval '${ php.replace( /'/g, "'\"'\"'" ) }'`,
		true
	);
	const widgetIds = parseJson< string[] >(
		stdout.trim().length ? stdout : '[]',
		[]
	);

	if ( ! widgetIds.length ) {
		return;
	}

	for ( const batch of chunkArray( widgetIds, 5 ) ) {
		await qit.wp( `widget delete ${ batch.join( ' ' ) }`, true );
	}
};

export const addMulticurrencyWidget = async (
	page: Page,
	blocksVersion = false
) => {
	await removeMultiCurrencyWidgets();

	await page.goto( '/wp-admin/widgets.php', {
		waitUntil: 'load',
	} );

	try {
		await page
			.locator( '.components-spinner' )
			.first()
			.waitFor( { timeout: 2_000 } );
		await expect( page.locator( '.components-spinner' ) ).toHaveCount( 0 );
	} catch {
		// The spinner is not always present when the widget area is empty.
	}

	const closeModalButton = page.getByRole( 'button', { name: 'Close' } );
	if ( await closeModalButton.isVisible() ) {
		await closeModalButton.click();
	}

	await expect( page.locator( '.components-spinner' ) ).toHaveCount( 0 );

	const widgetName = blocksVersion
		? 'Currency Switcher Block'
		: 'Currency Switcher Widget';
	const isWidgetAdded = blocksVersion
		? ( await page.locator( `[data-title="${ widgetName }"]` ).count() ) > 0
		: ( await page.getByRole( 'heading', { name: widgetName } ).count() ) >
		  0;

	if ( isWidgetAdded ) {
		return;
	}

	await page.getByRole( 'button', { name: 'Add block' } ).click();
	const searchInput = page.locator( 'input[placeholder="Search"]' );
	await searchInput.pressSequentially( widgetName, { delay: 20 } );
	await expect(
		page.locator( 'button.components-button[role="option"]' ).first()
	).toBeVisible( { timeout: 5_000 } );
	await page
		.locator( 'button.components-button[role="option"]' )
		.first()
		.click();
	await page.waitForTimeout( 2_000 );
	await expect(
		page.getByRole( 'button', { name: 'Update' } )
	).toBeEnabled();
	await page.getByRole( 'button', { name: 'Update' } ).click();
	await expectSnackbarWithText( page, 'Widgets saved.' );
};

export const createPendingOrder = async (): Promise< string > => {
	const billing = config.addresses.customer.billing;
	const billingPayload = JSON.stringify( {
		first_name: billing.firstname,
		last_name: billing.lastname,
		company: billing.company,
		address_1: billing.addressfirstline,
		address_2: billing.addresssecondline,
		city: billing.city,
		state: billing.state,
		postcode: billing.postcode,
		country: billing.country_code,
		email: billing.email,
		phone: billing.phone,
	} );
	const escapedBilling = billingPayload.replace( /'/g, `'"'"'` );
	const customerUsername = config.users.customer.username;
	const script = `
$products = wc_get_products( array(
	'limit' => 1,
	'orderby' => 'date',
	'order' => 'DESC',
	'return' => 'objects',
	'paginated' => false,
	'status' => array( 'publish' ),
) );
if ( empty( $products ) ) {
	throw new Exception( 'No products available for order creation.' );
}
$order = wc_create_order( array( 'status' => 'pending' ) );
$order->add_product( $products[0], 1 );
$billing = json_decode( '${ escapedBilling }', true );
if ( is_array( $billing ) ) {
	$order->set_address( $billing, 'billing' );
	$order->set_address( $billing, 'shipping' );
}
$customer = get_user_by( 'login', '${ customerUsername }' );
if ( $customer && ! is_wp_error( $customer ) ) {
	$order->set_customer_id( (int) $customer->ID );
}
$order->calculate_totals();
echo $order->get_id();
`;
	const escapedScript = script.replace( /'/g, `'"'"'` );
	const { stdout } = await qit.wp( `eval '${ escapedScript }'`, true );
	const orderId = stdout.trim().split( /\s+/ ).pop() ?? '';
	if ( ! orderId ) {
		throw new Error( 'Failed to create order for pay-for-order flow.' );
	}
	return orderId;
};

export const disableAllEnabledCurrencies = async ( page: Page ) => {
	await goToMultiCurrencySettings( page );

	const deleteButtons = () =>
		page.locator( '.enabled-currency .enabled-currency__action.delete' );

	while ( await deleteButtons().count() ) {
		await deleteButtons().first().click();
		await expectSnackbarWithText( page, 'Enabled currencies updated.' );
	}
};

const setEnabledCurrencies = async ( page: Page, currencies: string[] ) => {
	await disableAllEnabledCurrencies( page );

	const currenciesToEnable = Array.from(
		new Set(
			currencies
				.map( ( currency ) => currency.toUpperCase() )
				.filter( ( currency ) => currency !== 'USD' )
		)
	);

	if ( ! currenciesToEnable.length ) {
		return;
	}

	await page.getByTestId( 'enabled-currencies-add-button' ).click();

	for ( const currency of currenciesToEnable ) {
		await page
			.locator( `input[type="checkbox"][code="${ currency }"]` )
			.check();
	}

	await page.getByRole( 'button', { name: 'Update selected' } ).click();
	await expectSnackbarWithText( page, 'Enabled currencies updated.' );

	for ( const currency of currenciesToEnable ) {
		await expect(
			page.locator( `li.enabled-currency.${ currency.toLowerCase() }` )
		).toBeVisible();
	}
};

export const getEnabledCurrenciesSnapshot = async ( page: Page ) => {
	await goToMultiCurrencySettings( page );

	const currencies = await page
		.locator( '.enabled-currencies-list li.enabled-currency' )
		.evaluateAll( ( elements ) =>
			elements
				.map( ( element ) => {
					const className = element.getAttribute( 'class' ) ?? '';
					const match = className.match(
						/enabled-currency\s+([a-z]{3})/
					);
					return match ? match[ 1 ].toUpperCase() : '';
				} )
				.filter( Boolean )
		);

	return currencies;
};

export const restoreCurrencies = async (
	page: Page,
	currencies: string[] = [ 'EUR', 'GBP' ]
) => {
	await setEnabledCurrencies( page, currencies );
};

export const addCurrency = async ( page: Page, currencyCode: string ) => {
	if ( currencyCode === 'USD' ) {
		return;
	}

	await goToMultiCurrencySettings( page );
	await page.getByTestId( 'enabled-currencies-add-button' ).click();

	const checkbox = page.locator(
		`input[type="checkbox"][code="${ currencyCode }"]`
	);

	if ( ! ( await checkbox.isChecked() ) ) {
		await checkbox.check();
	}

	await page.getByRole( 'button', { name: 'Update selected' } ).click();
	await expectSnackbarWithText( page, 'Enabled currencies updated.' );
	await expect(
		page.locator( `li.enabled-currency.${ currencyCode.toLowerCase() }` )
	).toBeVisible();
};

export const removeCurrency = async ( page: Page, currencyCode: string ) => {
	await goToMultiCurrencySettings( page );
	const removeButton = page.locator(
		`li.enabled-currency.${ currencyCode.toLowerCase() } .enabled-currency__action.delete`
	);
	await removeButton.click();
	await expectSnackbarWithText( page, 'Enabled currencies updated.' );
	await expect(
		page.locator( `li.enabled-currency.${ currencyCode.toLowerCase() }` )
	).toBeHidden();
};

export const editCurrency = async ( page: Page, currencyCode: string ) => {
	await goToMultiCurrencySettings( page );
	const editButton = page.locator(
		`.enabled-currency.${ currencyCode.toLowerCase() } .enabled-currency__action.edit`
	);
	await editButton.click();
	await dataHasLoaded( page );
};

export const setCurrencyRate = async (
	page: Page,
	currencyCode: string,
	rate: string
) => {
	await editCurrency( page, currencyCode );
	await page.getByLabel( 'Manual' ).check();
	await page.getByTestId( 'manual_rate_input' ).fill( rate );
	await saveMultiCurrencySettings( page );
};

export const setCurrencyCharmPricing = async (
	page: Page,
	currencyCode: string,
	charm: string
) => {
	await editCurrency( page, currencyCode );
	await page.getByTestId( 'price_charm' ).selectOption( charm );
	await saveMultiCurrencySettings( page );
};

export const setCurrencyPriceRounding = async (
	page: Page,
	currencyCode: string,
	rounding: string
) => {
	await editCurrency( page, currencyCode );
	await page.getByTestId( 'price_rounding' ).selectOption( rounding );
	await saveMultiCurrencySettings( page );
};

export const enablePaymentMethods = async (
	page: Page,
	paymentMethods: string[]
) => {
	await goToWooPaymentsSettings( page );
	let atLeastOnePaymentMethodEnabled = false;

	for ( const paymentMethodName of paymentMethods ) {
		const checkbox = page.getByLabel( paymentMethodName );
		if ( ! ( await checkbox.isChecked() ) ) {
			await checkbox.check();
			atLeastOnePaymentMethodEnabled = true;
		}
	}

	if ( atLeastOnePaymentMethodEnabled ) {
		await saveWooPaymentsSettings( page );
	}
};

export const disablePaymentMethods = async (
	page: Page,
	paymentMethods: string[]
) => {
	await goToWooPaymentsSettings( page );
	let atLeastOnePaymentMethodDisabled = false;

	for ( const paymentMethodName of paymentMethods ) {
		const checkbox = page.getByLabel( paymentMethodName );

		if ( await checkbox.isChecked() ) {
			await checkbox.click();
			atLeastOnePaymentMethodDisabled = true;
			const removeButton = page.getByRole( 'button', { name: 'Remove' } );
			if ( await removeButton.isVisible() ) {
				await removeButton.click();
			}
		}
	}

	if ( atLeastOnePaymentMethodDisabled ) {
		await saveWooPaymentsSettings( page );
	}
};

export const getActiveThemeSlug = async (): Promise< string > => {
	try {
		const result = await qit.wp(
			'theme list --status=active --field=name',
			true
		);
		// Handle case where result might be undefined or not a string
		if ( typeof result === 'string' && result.trim() ) {
			return result.trim();
		}
		// Fallback to getting active theme via option
		const activeTheme = await qit.wp( 'option get stylesheet', true );
		return typeof activeTheme === 'string'
			? activeTheme.trim()
			: DEFAULT_THEME;
	} catch ( error ) {
		// Default fallback theme
		return DEFAULT_THEME;
	}
};

export const activateTheme = async ( slug: string ) => {
	// Skip if no slug provided or if it's already the fallback
	if ( ! slug || slug === 'undefined' ) {
		return;
	}

	try {
		// Check if theme is already installed
		await qit.wp( `theme is-installed ${ slug }`, true );
	} catch ( error ) {
		// Try to install the theme if not found
		try {
			await qit.wp( `theme install ${ slug } --force`, true );
		} catch ( installError ) {
			// If installation fails, just return - we can't activate what we can't install
			return;
		}
	}

	try {
		await qit.wp( `theme activate ${ slug }`, true );
	} catch ( activationError ) {
		// Theme activation failed, but we don't want to crash the test
	}
};
