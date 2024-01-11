/**
 * External dependencies
 */
import config from 'config';

/**
 * Internal dependencies
 */
import { merchantWCP, shopperWCP } from '../../../utils/flows';
import {
	setupProductCheckout,
	selectOnCheckout,
	completeRedirectedPayment,
} from '../../../utils/payments';
const { shopper, merchant } = require( '@woocommerce/e2e-utils' );

const UPE_METHOD_CHECKBOXES = [
	"//label[contains(text(), 'Bancontact')]/preceding-sibling::span/input[@type='checkbox']",
	"//label[contains(text(), 'giropay')]/preceding-sibling::span/input[@type='checkbox']",
];

describe( 'Enabled UPE with deferred intent creation', () => {
	beforeAll( async () => {
		await merchant.login();
		await merchantWCP.enablePaymentMethod( UPE_METHOD_CHECKBOXES );
		await merchant.logout();
		await shopper.login();
		await shopperWCP.changeAccountCurrencyTo( 'EUR' );
	} );

	afterAll( async () => {
		await shopperWCP.logout();
		await merchant.login();
		await merchantWCP.disablePaymentMethod( UPE_METHOD_CHECKBOXES );
		await merchant.logout();
	} );

	describe( 'Enabled UPE with deferred intent creation', () => {
		it( 'should successfully place order with Giropay', async () => {
			await setupProductCheckout(
				config.get( 'addresses.customer.billing' )
			);
			await selectOnCheckout( 'giropay', page );
			await shopper.placeOrder();
			await completeRedirectedPayment( page, 'success' );
			await page.waitForNavigation( {
				waitUntil: 'networkidle0',
			} );
			await expect( page ).toMatch( 'Order received' );
		} );

		it( 'should successfully place order with Bancontact', async () => {
			await setupProductCheckout(
				config.get( 'addresses.customer.billing' )
			);
			await selectOnCheckout( 'bancontact', page );
			await shopper.placeOrder();
			await completeRedirectedPayment( page, 'success' );
			await page.waitForNavigation( {
				waitUntil: 'networkidle0',
			} );
			await expect( page ).toMatch( 'Order received' );
		} );
	} );
} );
