/**
 * External dependencies
 */
import config from 'config';

/**
 * Internal dependencies
 */
import { merchantWCP, shopperWCP } from '../../../utils/flows';
import {
	confirmCardAuthentication,
	fillCardDetails,
	setupProductCheckout,
} from '../../../utils/payments';
import { uiUnblocked } from '@woocommerce/e2e-utils/build/page-utils';
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
		await merchantWCP.activateUPEWithDefferedIntentCreation();
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
		await merchantWCP.deactivateUPEWithDefferedIntentCreation();
		await merchant.logout();
	} );

	it( 'should successfully place order with the default card', async () => {
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
		await fillCardDetails( page, card );
		await shopper.placeOrder();
		await expect( page ).toMatch( 'Order received' );
	} );

	it( 'should process a payment with authentication for the 3DS card', async () => {
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
		await fillCardDetails( page, config.get( 'cards.3ds' ) );
		await shopper.placeOrder();
		await confirmCardAuthentication( page, '3DS' );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
		await expect( page ).toMatch( 'Order received' );
	} );

	it( 'should successfully save the card', async () => {
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
		await fillCardDetails( page, card );
		await shopperWCP.toggleSavePaymentMethod();
		await shopper.placeOrder();
		await expect( page ).toMatch( 'Order received' );

		// validate that the payment method has been added to the customer.
		await shopperWCP.goToPaymentMethods();
		await expect( page ).toMatch( card.label );
		await expect( page ).toMatch(
			`${ card.expires.month }/${ card.expires.year }`
		);
	} );

	it( 'should process a payment with the saved card', async () => {
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
		await shopper.goToCheckout();
		await uiUnblocked();
		await shopperWCP.selectSavedPaymentMethod(
			`${ card.label } (expires ${ card.expires.month }/${ card.expires.year })`
		);
		await shopper.placeOrder();
		await expect( page ).toMatch( 'Order received' );
	} );

	it( 'should delete the card', async () => {
		await shopperWCP.goToPaymentMethods();
		await shopperWCP.deleteSavedPaymentMethod( card.label );
		await expect( page ).toMatch( 'Payment method deleted' );
	} );
} );
