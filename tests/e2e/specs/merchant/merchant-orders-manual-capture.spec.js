/**
 * External dependencies
 */
const {
	merchant,
	shopper,
	setCheckbox,
	unsetCheckbox,
	selectOrderAction,
} = require( '@woocommerce/e2e-utils' );
import config from 'config';

/**
 * Internal dependencies
 */
import { merchantWCP } from '../../utils';
import { fillCardDetails, setupProductCheckout } from '../../utils/payments';

const chkboxCaptureLaterOption = '#inspector-checkbox-control-8';
let orderId;

describe( 'Order > Manual Capture', () => {
	beforeAll( async () => {
		// As the merchant, enable the "Issue an authorization on checkout, and capture later" option in the Payment Settings page
		await merchant.login();
		await merchantWCP.openWCPSettings();
		await setCheckbox( chkboxCaptureLaterOption );
		await expect( page ).toFill( '#inspector-text-control-0', 'E2E Store' );
		await merchantWCP.wcpSettingsSaveChanges();

		// As the shopper, place an order as usual.
		// Remember the order id
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
		const card = config.get( 'cards.basic' );
		await fillCardDetails( page, card );
		await shopper.placeOrder();
		await expect( page ).toMatch( 'Order received' );
		const orderIdField = await page.$(
			'.woocommerce-order-overview__order.order > strong'
		);
		orderId = await orderIdField.evaluate( ( el ) => el.innerText );
	}, 120000 );

	afterAll( async () => {
		// Disable the "Issue an authorization on checkout, and capture later" option
		await merchantWCP.openWCPSettings();
		await unsetCheckbox( chkboxCaptureLaterOption );
		await merchantWCP.wcpSettingsSaveChanges();
		await merchant.logout();
	} );

	it( 'should create an order with status "On Hold"', async () => {
		await merchant.goToOrder( orderId );

		await expect( page ).toMatchElement(
			'#select2-order_status-container',
			{ text: 'On hold' }
		);
	} );

	it( 'should create an order note saying that payment was authorized ', async () => {
		await expect( page ).toMatchElement( '.system-note', {
			text: /A payment of \$\d+\.\d{2}.* was authorized/,
		} );
	} );

	it( 'should successfully capture charge', async () => {
		// Capture the charge
		await selectOrderAction( 'capture_charge' );

		// Verify that the order status is now "Processing"
		await expect( page ).toMatchElement(
			'#select2-order_status-container',
			{ text: 'Processing' }
		);

		// Verify that a system note about the capture was generated
		await expect( page ).toMatchElement( '.system-note', {
			text: /A payment of \$\d+\.\d{2}.* was successfully captured/,
		} );
	} );
} );
