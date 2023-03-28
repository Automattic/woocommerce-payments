/**
 * External dependencies
 */
import config from 'config';

/**
 * Internal dependencies
 */
import { merchantWCP, shopperWCP } from '../../../utils/flows';
import { fillCardDetails, setupProductCheckout } from '../../../utils/payments';
const { shopper, merchant } = require( '@woocommerce/e2e-utils' );

const upeMethodCheckboxes = [
	'#inspector-checkbox-control-3', // bancontact
	'#inspector-checkbox-control-4', // eps
	'#inspector-checkbox-control-5', // giropay
	'#inspector-checkbox-control-6', // ideal
	'#inspector-checkbox-control-7', // sofort
];
const card = config.get( 'cards.basic' );

describe( 'Enabled enhanced UPE', () => {
	beforeAll( async () => {
		await merchant.login();
		await merchantWCP.activateEnhancedUPE();
		await merchantWCP.enablePaymentMethod( upeMethodCheckboxes );
		await merchant.logout();
		await shopper.login();
		await shopperWCP.changeAccountCurrencyTo( 'EUR' );
	} );

	afterAll( async () => {
		await shopperWCP.changeAccountCurrencyTo( 'USD' );
		await shopperWCP.logout();
		await merchant.login();
		await merchantWCP.disablePaymentMethod( upeMethodCheckboxes );
		await merchantWCP.deactivateEnhancedUPE();
		await merchant.logout();
	} );

	it( 'should save the card', async () => {
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
		await fillCardDetails( page, card );
		await shopper.placeOrder();
		await expect( page ).toMatch( 'Order received' );
	} );
} );
