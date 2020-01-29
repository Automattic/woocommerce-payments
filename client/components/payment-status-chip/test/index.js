/** @format */
/**
 * External dependencies
 */
import { shallow } from 'enzyme';

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

	test( 'renders a light chip with partially refunded message if there\'s a partial refund', () => {
		getChargeStatus.mockReturnValue( 'partially-refunded' );
		expect( renderPaymentStatus() ).toMatchSnapshot();
	} );

	test( 'renders a light chip with fully refunded message if there\'s a full refund', () => {
		getChargeStatus.mockReturnValue( 'fully-refunded' );
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

	test( 'renders a primary chip with dispute message if there\'s a dispute needing response', () => {
		getChargeStatus.mockReturnValue( 'disputed-needs-response' );
		expect( renderPaymentStatus() ).toMatchSnapshot();
	} );

	test( 'renders a light chip with dispute message if there\'s a dispute in review', () => {
		getChargeStatus.mockReturnValue( 'disputed-under-review' );
		expect( renderPaymentStatus() ).toMatchSnapshot();
	} );

	test( 'renders a light chip with dispute message if there\'s a won dispute', () => {
		getChargeStatus.mockReturnValue( 'disputed-won' );
		expect( renderPaymentStatus() ).toMatchSnapshot();
	} );

	test( 'renders a light chip with dispute message if there\'s a lost dispute', () => {
		getChargeStatus.mockReturnValue( 'disputed-lost' );
		expect( renderPaymentStatus() ).toMatchSnapshot();
	} );

	function renderPaymentStatus() {
		return shallow( <PaymentStatusChip charge={ {} } /> );
	}
} );

