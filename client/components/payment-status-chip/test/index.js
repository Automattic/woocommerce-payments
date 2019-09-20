/** @format */
/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import PaymentStatusChip from '../';

describe( 'PaymentStatusChip', () => {
    test( 'renders a warning chip with dispute message if there\'s a dispute', () => {
        const paymentStatusChip = renderPaymentStatus( {
			dispute: {
				amount: 1500,
			},
		} );
        expect( paymentStatusChip ).toMatchSnapshot();
    } );

    test( 'renders a primary chip with paid message if there\'s no dispute', () => {
        const paymentStatusChip = renderPaymentStatus( {} );
        expect( paymentStatusChip ).toMatchSnapshot();
    } );

    function renderPaymentStatus( transaction ) {
        return shallow( <PaymentStatusChip transaction={ transaction } /> );
    }
} );

