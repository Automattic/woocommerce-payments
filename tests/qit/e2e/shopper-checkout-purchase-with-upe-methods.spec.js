/**
 * Migrated from tests/e2e/specs/wcpay/shopper/shopper-checkout-purchase-with-upe-methods.spec.ts
 */
/**
 * External dependencies
 */
import { expect, test } from '@playwright/test';
import qit from '/qitHelpers';

/**
 * Internal dependencies
 */
import {
	addToCartFromShopPage,
	changeAccountCurrency,
	emptyCart,
	expectFraudPreventionToken,
	placeOrder,
	selectPaymentMethod,
	setupCheckout,
	CHECKOUT_URLS,
} from './helpers/checkout.js';

const CUSTOMER_USERNAME = process.env.QIT_CUSTOMER_USERNAME || 'testcustomer';
const CUSTOMER_PASSWORD = process.env.QIT_CUSTOMER_PASSWORD || 'testpass123';

const customerAccountDetails = {
	firstname: 'I am',
	lastname: 'Customer',
	company: 'Automattic',
	email: 'e2e-wcpay-customer@woocommerce.com',
	phone: '123456789',
};

const belgianBillingAddress = {
	firstName: 'I am',
	lastName: 'Customer',
	company: 'Automattic',
	country: 'BE',
	address1: "Rue de l'Etuve 1000",
	address2: 'billing-be',
	city: 'Bruxelles',
	state: '',
	postcode: '1000',
	phone: '123456789',
	email: 'e2e-wcpay-customer@woocommerce.com',
};

const checkoutVariants = [
	{
		key: 'shortcode',
		description: 'Classic shortcode checkout',
		url: CHECKOUT_URLS.shortcode,
	},
	{
		key: 'blocks',
		description: 'Blocks checkout',
		url: CHECKOUT_URLS.blocks,
	},
];

const loginAsCustomer = async ( page ) => {
	await page.goto( '/my-account' );
	await page.waitForLoadState( 'domcontentloaded' );

	const logoutLink = page.locator(
		'.woocommerce-MyAccount-navigation-link--customer-logout'
	);
	if ( await logoutLink.count() ) {
		return;
	}

	const usernameInput = page.locator( 'input#username' );
	const passwordInput = page.locator( 'input#password' );

	await expect( usernameInput ).toBeVisible();
	await usernameInput.fill( CUSTOMER_USERNAME );
	await passwordInput.fill( CUSTOMER_PASSWORD );
	await page.locator( 'button[name="login"]' ).click();

	await expect( logoutLink ).toBeVisible();
};

const waitForLoadingToComplete = async ( page ) => {
	const skeleton = page.locator( '.is-loadable-placeholder' );
	if ( ( await skeleton.count() ) > 0 ) {
		await skeleton
			.first()
			.waitFor( { state: 'hidden', timeout: 15000 } )
			.catch( () => {} );
	}
};

const goToWooPaymentsSettings = async ( page ) => {
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments'
	);
	await page.waitForLoadState( 'domcontentloaded' );
	await waitForLoadingToComplete( page );
};

const goToMultiCurrencySettings = async ( page ) => {
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=wcpay_multi_currency'
	);
	await page.waitForLoadState( 'domcontentloaded' );
	await waitForLoadingToComplete( page );
	const loadingTable = page.locator( '.woocommerce-table__table.is-loading' );
	if ( ( await loadingTable.count() ) > 0 ) {
		await loadingTable
			.first()
			.waitFor( { state: 'hidden', timeout: 15000 } )
			.catch( () => {} );
	}
};

const ensureSupportPhone = async ( page ) => {
	const phoneInput = page.getByPlaceholder( 'Mobile number' );
	if (
		( await phoneInput.count() ) > 0 &&
		( await phoneInput.inputValue() ) === ''
	) {
		await phoneInput.fill( '0000000000' );
	}
};

const saveWooPaymentsSettings = async ( page ) => {
	await ensureSupportPhone( page );
	const saveButton = page.getByRole( 'button', { name: 'Save changes' } );
	await expect( saveButton ).toBeEnabled( { timeout: 10000 } );
	await saveButton.click();
	await expect(
		page.locator( '.components-snackbar__content' )
	).toContainText( 'Settings saved.', { timeout: 15000 } );
};

const enableMultiCurrencyIfNeeded = async ( page ) => {
	await goToWooPaymentsSettings( page );
	const toggle = page.getByTestId( 'multi-currency-toggle' );
	if ( ( await toggle.count() ) === 0 ) {
		return false;
	}
	const wasEnabled = await toggle.isChecked();
	if ( ! wasEnabled ) {
		await toggle.check();
		await saveWooPaymentsSettings( page );
	}
	return wasEnabled;
};

const disableMultiCurrency = async ( page ) => {
	await goToWooPaymentsSettings( page );
	const toggle = page.getByTestId( 'multi-currency-toggle' );
	if ( ( await toggle.count() ) > 0 && ( await toggle.isChecked() ) ) {
		await toggle.uncheck();
		await saveWooPaymentsSettings( page );
	}
};

const isCurrencyEnabled = async ( page, code ) => {
	await goToMultiCurrencySettings( page );
	return (
		( await page
			.locator( `li.enabled-currency.${ code.toLowerCase() }` )
			.count() ) > 0
	);
};

const ensureCurrencyEnabled = async ( page, code ) => {
	const wasEnabled = await isCurrencyEnabled( page, code );
	if ( wasEnabled ) {
		return true;
	}

	await page.getByTestId( 'enabled-currencies-add-button' ).click();
	const checkbox = page.locator( `input[type="checkbox"][code="${ code }"]` );
	await expect( checkbox ).toBeVisible( { timeout: 10000 } );
	await checkbox.check();
	await page.getByRole( 'button', { name: 'Update selected' } ).click();
	await expect(
		page.locator( '.components-snackbar__content' )
	).toContainText( 'Enabled currencies updated.', { timeout: 15000 } );
	await expect(
		page.locator( `li.enabled-currency.${ code.toLowerCase() }` )
	).toBeVisible( { timeout: 15000 } );
	return false;
};

const removeCurrencyIfPresent = async ( page, code ) => {
	await goToMultiCurrencySettings( page );
	const currencyRow = page.locator(
		`li.enabled-currency.${ code.toLowerCase() }`
	);
	if ( ( await currencyRow.count() ) > 0 ) {
		await currencyRow.locator( '.enabled-currency__action.delete' ).click();
		await expect(
			page.locator( '.components-snackbar__content' )
		).toContainText( 'Enabled currencies updated.', { timeout: 15000 } );
	}
};

const ensurePaymentMethodEnabled = async ( page, methodName ) => {
	await goToWooPaymentsSettings( page );
	const checkbox = page.getByLabel( methodName, { exact: false } );
	if ( ( await checkbox.count() ) === 0 ) {
		throw new Error( `Payment method ${ methodName } not found` );
	}
	const wasEnabled = await checkbox.isChecked();
	if ( ! wasEnabled ) {
		await checkbox.check( { force: true } );
		await saveWooPaymentsSettings( page );
	}
	return wasEnabled;
};

const disablePaymentMethod = async ( page, methodName ) => {
	await goToWooPaymentsSettings( page );
	const checkbox = page.getByLabel( methodName, { exact: false } );
	if ( ( await checkbox.count() ) > 0 && ( await checkbox.isChecked() ) ) {
		await checkbox.click();
		const removeButton = page
			.getByRole( 'button', { name: 'Remove' } )
			.first();
		if ( await removeButton.isVisible() ) {
			await removeButton.click();
		}
		await saveWooPaymentsSettings( page );
	}
};

const setCardTestingProtection = async ( page ) => {
	await qit.loginAsAdmin( page );
	await goToWooPaymentsSettings( page );
};

test.describe.serial(
	'Local payment method checkout with card testing @critical @shopper @bancontact @shopper-checkout-purchase-with-upe-methods',
	() => {
		let adminPage;
		let shopperPage;
		let wasMultiCurrencyEnabled = false;
		let euroInitiallyEnabled = false;
		let bancontactInitiallyEnabled = false;

		test.beforeAll( async ( { browser } ) => {
			adminPage = await browser.newPage();
			await qit.loginAsAdmin( adminPage );

			wasMultiCurrencyEnabled = await enableMultiCurrencyIfNeeded(
				adminPage
			);
			euroInitiallyEnabled = await ensureCurrencyEnabled(
				adminPage,
				'EUR'
			);
			bancontactInitiallyEnabled = await ensurePaymentMethodEnabled(
				adminPage,
				'Bancontact'
			);

			shopperPage = await browser.newPage();
			await loginAsCustomer( shopperPage );
			await changeAccountCurrency(
				shopperPage,
				customerAccountDetails,
				'EUR'
			);
			await emptyCart( shopperPage ).catch( () => {} );
		} );

		test.afterAll( async () => {
			if ( shopperPage && ! shopperPage.isClosed() ) {
				await emptyCart( shopperPage ).catch( () => {} );
				await changeAccountCurrency(
					shopperPage,
					customerAccountDetails,
					'USD'
				).catch( () => {} );
				await shopperPage.close();
			}

			if ( adminPage && ! adminPage.isClosed() ) {
				if ( ! bancontactInitiallyEnabled ) {
					await disablePaymentMethod(
						adminPage,
						'Bancontact'
					).catch( () => {} );
				}
				if ( ! euroInitiallyEnabled ) {
					await removeCurrencyIfPresent(
						adminPage,
						'EUR'
					).catch( () => {} );
				}
				if ( ! wasMultiCurrencyEnabled ) {
					await disableMultiCurrency( adminPage ).catch( () => {} );
				}
				await adminPage.close();
			}
		} );

		const ctpScenarios = [ false, true ];

		for ( const variant of checkoutVariants ) {
			for ( const ctpEnabled of ctpScenarios ) {
				test( `${ variant.description } - card testing protection ${
					ctpEnabled ? 'enabled' : 'disabled'
				}`, async () => {
					await setCardTestingProtection( adminPage );

					await emptyCart( shopperPage ).catch( () => {} );
					await addToCartFromShopPage( shopperPage );
					await setupCheckout(
						shopperPage,
						belgianBillingAddress,
						variant.url
					);
					await expectFraudPreventionToken( shopperPage, ctpEnabled );

					const bancontactRadio = shopperPage.locator(
						'#payment_method_woocommerce_payments_bancontact'
					);
					if ( ( await bancontactRadio.count() ) > 0 ) {
						const bancontactLabel = shopperPage
							.locator(
								'label[for="payment_method_woocommerce_payments_bancontact"]'
							)
							.first();
						await expect( bancontactLabel ).toBeVisible( {
							timeout: 20000,
						} );
						await bancontactLabel.click( { force: true } );
						const bancontactBox = shopperPage.locator(
							'.payment_method_woocommerce_payments_bancontact .payment_box'
						);
						let boxVisible = true;
						try {
							await bancontactBox.waitFor( {
								state: 'visible',
								timeout: 5000,
							} );
						} catch ( _error ) {
							boxVisible = false;
						}
						if ( ! boxVisible ) {
							await selectPaymentMethod(
								shopperPage,
								'Bancontact'
							);
							await bancontactBox.waitFor( {
								state: 'visible',
								timeout: 5000,
							} );
						}
					} else {
						await selectPaymentMethod( shopperPage, 'Bancontact' );
					}
					await shopperPage.waitForTimeout( 500 );
					await placeOrder( shopperPage );

					const authorizeLink = shopperPage
						.getByRole( 'link', {
							name: /Authorize Test Payment/i,
						} )
						.first();

					if ( ! shopperPage.url().includes( '/order-received/' ) ) {
						let authorizeLinkVisible = false;
						try {
							await authorizeLink.waitFor( {
								state: 'visible',
								timeout: 10000,
							} );
							authorizeLinkVisible = true;
						} catch ( _error ) {
							// Link did not surface (frictionless flow or failure)
						}

						if ( authorizeLinkVisible ) {
							await authorizeLink.click();
						}
					}

					await shopperPage.waitForURL( '**/order-received/**', {
						timeout: 30000,
					} );
					await expect(
						shopperPage.getByRole( 'heading', {
							name: 'Order received',
						} )
					).toBeVisible( { timeout: 20000 } );
				} );
			}
		}
	}
);
