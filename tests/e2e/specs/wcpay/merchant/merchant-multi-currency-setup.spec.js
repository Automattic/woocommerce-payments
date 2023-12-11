/**
 * External dependencies
 */
const { merchant } = require( '@woocommerce/e2e-utils' );
/**
 * Internal dependencies
 */
import { merchantWCP, shopperWCP } from '../../../utils';

let wasMulticurrencyEnabled;

describe( 'Merchant Multi-Currency Settings', () => {
	beforeAll( async () => {
		await merchant.login();
		// Get initial multi-currency feature status.
		await merchantWCP.openWCPSettings();
		wasMulticurrencyEnabled = await page.evaluate( () => {
			const checkbox = document.querySelector(
				"[data-testid='multi-currency-toggle']"
			);
			return checkbox ? checkbox.checked : false;
		} );
	} );

	afterAll( async () => {
		// Disable multi-currency if it was not initially enabled.
		if ( ! wasMulticurrencyEnabled ) {
			await merchant.login();
			await merchantWCP.deactivateMulticurrency();
		}
		await merchant.logout();
	} );

	it( 'can enable multi-currency feature', async () => {
		// Assertions are in the merchantWCP.wcpSettingsSaveChanges() flow.
		await merchantWCP.activateMulticurrency();
	} );

	it( 'can disable multi-currency feature', async () => {
		// Assertions are in the merchantWCP.wcpSettingsSaveChanges() flow.
		await merchantWCP.deactivateMulticurrency();
	} );

	describe( 'Currency Management', () => {
		const testCurrency = 'CHF';

		beforeAll( async () => {
			await merchantWCP.activateMulticurrency();
		} );

		it( 'can add a new currency', async () => {
			await merchantWCP.addCurrency( testCurrency );
			// Add assertions to verify the currency is added
		} );

		it.skip( 'can remove a currency', async () => {
			await merchantWCP.removeCurrency( testCurrency );
			// Add assertions to verify the currency is removed
		} );
	} );
} );
