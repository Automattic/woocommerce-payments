/**
 * External dependencies
 */
const { merchant } = require( '@woocommerce/e2e-utils' );
/**
 * Internal dependencies
 */
import { merchantWCP, shopperWCP } from '../../../utils';

let wasMulticurrencyEnabled;

describe( 'Merchant On-boarding', () => {
	beforeAll( async () => {
		await merchant.login();
		// Get initial multi-currency feature status.
		await merchantWCP.openWCPSettings();
		await page.waitForSelector( "[data-testid='multi-currency-toggle']" );
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

	describe( 'Currency Selection and Management', () => {
		it( 'Should disable the submit button when no currencies are selected', async () => {
			// Implement test
		} );

		it( 'Should allow multiple currencies to be selectable', async () => {
			// Implement test
		} );

		it( 'Should exclude already enabled currencies from the currency screen', async () => {
			// Implement test
		} );

		it( 'Should display some suggested currencies at the beginning of the list', async () => {
			// Implement test
		} );

		it( 'Should ensure selected currencies are enabled after submitting the form', async () => {
			// Implement test
		} );
	} );

	describe.skip( 'Geolocation Features', () => {
		it( 'Should offer currency switch by geolocation', async () => {
			// Implement test
		} );

		it( 'Should preview currency switch by geolocation correctly with USD and GBP', async () => {
			// Implement test
		} );
	} );

	describe.skip( 'Currency Switcher Widget', () => {
		it( 'Should offer the currency switcher widget while Storefront theme is active', async () => {
			// Implement test
		} );

		it( 'Should not offer the currency switcher widget when an unsupported theme is active', async () => {
			// Implement test
		} );
	} );
} );
