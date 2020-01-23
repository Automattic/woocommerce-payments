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
		const paymentStatusChip = renderPaymentStatus();
		expect( paymentStatusChip ).toMatchSnapshot();
	} );

	test( 'renders a light chip with partially refunded message if there\'s a partial refund', () => {
		getChargeStatus.mockReturnValue( 'partially-refunded' );
		const paymentStatusChip = renderPaymentStatus();
		expect( paymentStatusChip ).toMatchSnapshot();
	} );

	test( 'renders a light chip with fully refunded message if there\'s a full refund', () => {
		getChargeStatus.mockReturnValue( 'fully-refunded' );
		const paymentStatusChip = renderPaymentStatus();
		expect( paymentStatusChip ).toMatchSnapshot();
	} );

	test( 'renders a light chip with paid message if it is paid', () => {
		getChargeStatus.mockReturnValue( 'paid' );
		const paymentStatusChip = renderPaymentStatus();
		expect( paymentStatusChip ).toMatchSnapshot();
	} );

	test( 'renders a primary chip with authorized message if payment was not captured', () => {
		getChargeStatus.mockReturnValue( 'authorized' );
		const paymentStatusChip = renderPaymentStatus();
		expect( paymentStatusChip ).toMatchSnapshot();
	} );

	test( 'renders an alert chip with failed message if payment status is failed', () => {
		getChargeStatus.mockReturnValue( 'failed' );
		const paymentStatusChip = renderPaymentStatus();
		expect( paymentStatusChip ).toMatchSnapshot();
	} );

	test( 'renders a primary chip with dispute message if there\'s a dispute needing response', () => {
		getChargeStatus.mockReturnValue( 'disputed-needs-response' );
		const paymentStatusChip = renderPaymentStatus();
		expect( paymentStatusChip ).toMatchSnapshot();
	} );

	test( 'renders a light chip with dispute message if there\'s a dispute in review', () => {
		getChargeStatus.mockReturnValue( 'disputed-under-review' );
		const paymentStatusChip = renderPaymentStatus();
		expect( paymentStatusChip ).toMatchSnapshot();
	} );

	test( 'renders a light chip with dispute message if there\'s a won dispute', () => {
		getChargeStatus.mockReturnValue( 'disputed-won' );
		const paymentStatusChip = renderPaymentStatus();
		expect( paymentStatusChip ).toMatchSnapshot();
	} );

	test( 'renders a light chip with dispute message if there\'s a lost dispute', () => {
		getChargeStatus.mockReturnValue( 'disputed-lost' );
		const paymentStatusChip = renderPaymentStatus();
		expect( paymentStatusChip ).toMatchSnapshot();
	} );

	function renderPaymentStatus() {
		return shallow( <PaymentStatusChip charge={ {} } /> );
	}
} );

