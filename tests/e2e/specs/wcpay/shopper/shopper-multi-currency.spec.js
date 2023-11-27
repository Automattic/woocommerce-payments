/**
 * External dependencies
 */
const { merchant, shopper } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { merchantWCP } from '../../../utils';

describe( 'Shopper Multi-Currency checkout', () => {
	beforeAll( async () => {
		await merchant.login();
	} );

	it( "should display currency switcher if it's enabled", async () => {
		await merchantWCP.openWCPSettings();
		await merchantWCP.activateMulticurrency();
		await shopper.goToShop();
		await page.waitForSelector( '[name=currency]', {
			visible: true,
		} );
	} );

	it( "should not display currency switcher if it's disabled", async () => {
		await merchantWCP.openWCPSettings();
		await merchantWCP.deactivateMulticurrency();
		await shopper.goToShop();

		const currencySwitcher = await page.$( '[name=currency]' );
		expect( currencySwitcher ).toBeNull();

		// Activate it again for the other tests
		await merchantWCP.activateMulticurrency();
	} );
} );
