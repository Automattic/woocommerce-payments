/**
 * External dependencies
 */
import { Page, expect } from '@playwright/test';
import qit from '/qitHelpers';

export async function dataHasLoaded( page: Page ) {
	await expect( page.locator( '.is-loadable-placeholder' ) ).toHaveCount( 0 );
}

const goToWooPaymentsSettings = async ( page: Page ) => {
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments',
		{ waitUntil: 'load' }
	);
	await dataHasLoaded( page );
};

const goToMultiCurrencySettings = async ( page: Page ) => {
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=wcpay_multi_currency',
		{ waitUntil: 'load' }
	);
	await dataHasLoaded( page );
};

const expectSnackbarWithText = async (
	page: Page,
	text: string,
	timeout = 10_000
) => {
	const snackbar = page.locator( '.components-snackbar__content', {
		hasText: text,
	} );
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

export const saveMultiCurrencySettings = async ( page: Page ) => {
	await page.getByRole( 'button', { name: 'Save changes' } ).click();
	await expectSnackbarWithText( page, 'Currency settings updated.' );
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

const disableAllEnabledCurrencies = async ( page: Page ) => {
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

export const activateTheme = async ( slug: string ) => {
	try {
		await qit.wp( `theme is-installed ${ slug }`, true );
	} catch ( error ) {
		await qit.wp( `theme install ${ slug } --force`, true );
	}

	await qit.wp( `theme activate ${ slug }`, true );
};
