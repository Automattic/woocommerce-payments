/** @format */
/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import { getTransactionStatus } from '../../../utils/transaction';
import PaymentStatusChip from '../';

jest.mock( '../../../utils/transaction', () => ( { getTransactionStatus: jest.fn() } ) );

describe( 'PaymentStatusChip', () => {
	test( 'renders a default light chip with no message if status does not match', () => {
		getTransactionStatus.mockReturnValue( 'teststatus' );
        const paymentStatusChip = renderPaymentStatus();
        expect( paymentStatusChip ).toMatchSnapshot();
	} );

	test( 'renders a light chip with partially refunded message if there\'s a partial refund', () => {
		getTransactionStatus.mockReturnValue( 'partially-refunded' );
        const paymentStatusChip = renderPaymentStatus();
        expect( paymentStatusChip ).toMatchSnapshot();
	} );

	test( 'renders a light chip with fully refunded message if there\'s a full refund', () => {
		getTransactionStatus.mockReturnValue( 'fully-refunded' );
        const paymentStatusChip = renderPaymentStatus();
        expect( paymentStatusChip ).toMatchSnapshot();
    } );

	test( 'renders a light chip with paid message if it is paid', () => {
		getTransactionStatus.mockReturnValue( 'paid' );
		const paymentStatusChip = renderPaymentStatus();
		expect( paymentStatusChip ).toMatchSnapshot();
	} );

    test( 'renders a primary chip with authorized message if payment was not captured', () => {
		getTransactionStatus.mockReturnValue( 'authorized' );
        const paymentStatusChip = renderPaymentStatus();
        expect( paymentStatusChip ).toMatchSnapshot();
	} );

	test( 'renders an alert chip with failed message if payment status is failed', () => {
		getTransactionStatus.mockReturnValue( 'failed' );
        const paymentStatusChip = renderPaymentStatus();
        expect( paymentStatusChip ).toMatchSnapshot();
	} );

	test( 'renders a primary chip with dispute message if there\'s a dispute', () => {
		getTransactionStatus.mockReturnValue( 'disputed' );
        const paymentStatusChip = renderPaymentStatus();
        expect( paymentStatusChip ).toMatchSnapshot();
    } );

    function renderPaymentStatus() {
        return shallow( <PaymentStatusChip transaction={ {} } /> );
	}
} );

