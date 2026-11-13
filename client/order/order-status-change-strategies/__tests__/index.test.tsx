/**
 * External dependencies
 */
import { act, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import getStatusChangeStrategy from '..';
import { getConfig } from 'wcpay/utils/order';

jest.mock( 'wcpay/utils/order', () => ( {
	getConfig: jest.fn(),
} ) );

const BankTransferGatewayId = 'bacs';
const WooPaymentsGatewayId = 'woocommerce_payments';
const OnHoldStatus = 'wc-on-hold';
const CompletedStatus = 'wc-completed';

function renderPaymentMethodSelect( selectedGatewayId: string ) {
	document.body.innerHTML = `
		<select id="_payment_method">
			<option value="${ WooPaymentsGatewayId }">WooPayments</option>
			<option value="${ BankTransferGatewayId }">Direct bank transfer</option>
		</select>
	`;
	(
		document.getElementById( '_payment_method' ) as HTMLSelectElement
	 ).value = selectedGatewayId;
}

describe( 'Completing an order with an open authorization', () => {
	beforeEach( () => {
		( getConfig as jest.Mock ).mockImplementation(
			( name: string ) => name === 'hasOpenAuthorization'
		);
	} );

	afterEach( () => {
		document.body.innerHTML = '';
	} );

	it( 'asks to capture while the order uses WooPayments', async () => {
		renderPaymentMethodSelect( WooPaymentsGatewayId );

		act( () => {
			getStatusChangeStrategy( CompletedStatus )(
				OnHoldStatus,
				CompletedStatus
			);
		} );

		expect(
			await screen.findByText( 'Complete order and capture payment' )
		).toBeInTheDocument();
	} );

	it( "doesn't ask to capture once the payment method changes to another gateway", () => {
		renderPaymentMethodSelect( BankTransferGatewayId );

		getStatusChangeStrategy( CompletedStatus )(
			OnHoldStatus,
			CompletedStatus
		);

		expect(
			document.getElementById( 'wcpay-orderstatus-confirm-container' )
		).toBeNull();
	} );
} );
