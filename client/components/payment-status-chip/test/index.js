/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { getChargeStatus } from '../../../utils/charge';
import PaymentStatusChip from '../';

jest.mock( '../../../utils/charge', () => ( { getChargeStatus: jest.fn() } ) );

describe( 'PaymentStatusChip', () => {
	test( 'renders a default light chip with no message if status does not match', () => {
		getChargeStatus.mockReturnValue( 'teststatus' );
		expect( renderPaymentStatus() ).toMatchSnapshot();
	} );

	test( "renders a light chip with partially refunded message if there's a partial refund", () => {
		getChargeStatus.mockReturnValue( 'refunded_partial' );
		expect( renderPaymentStatus() ).toMatchSnapshot();
	} );

	test( "renders a light chip with fully refunded message if there's a full refund", () => {
		getChargeStatus.mockReturnValue( 'refunded_full' );
		expect( renderPaymentStatus() ).toMatchSnapshot();
	} );

	test( 'renders a light chip with paid message if it is paid', () => {
		getChargeStatus.mockReturnValue( 'paid' );
		expect( renderPaymentStatus() ).toMatchSnapshot();
	} );

	test( 'renders a primary chip with authorized message if payment was not captured', () => {
		getChargeStatus.mockReturnValue( 'authorized' );
		const paymentStatusChip = renderPaymentStatus();
		expect( paymentStatusChip ).toMatchSnapshot();
	} );

	test( 'renders an alert chip with failed message if payment status is failed', () => {
		getChargeStatus.mockReturnValue( 'failed' );
		expect( renderPaymentStatus() ).toMatchSnapshot();
	} );

	test( 'renders an alert chip with failed message if payment status is blocked', () => {
		getChargeStatus.mockReturnValue( 'blocked' );
		expect( renderPaymentStatus() ).toMatchSnapshot();
	} );

	test( "renders a primary chip with dispute message if there's a dispute needing response", () => {
		getChargeStatus.mockReturnValue( 'disputed_needs_response' );
		expect( renderPaymentStatus() ).toMatchSnapshot();
	} );

	test( "renders a light chip with dispute message if there's a dispute in review", () => {
		getChargeStatus.mockReturnValue( 'disputed_under_review' );
		expect( renderPaymentStatus() ).toMatchSnapshot();
	} );

	test( "renders a light chip with dispute message if there's a won dispute", () => {
		getChargeStatus.mockReturnValue( 'disputed_won' );
		expect( renderPaymentStatus() ).toMatchSnapshot();
	} );

	test( "renders a light chip with dispute message if there's a lost dispute", () => {
		getChargeStatus.mockReturnValue( 'disputed_lost' );
		expect( renderPaymentStatus() ).toMatchSnapshot();
	} );

	function renderPaymentStatus() {
		return render( <PaymentStatusChip charge={ {} } /> ).container;
	}
} );
