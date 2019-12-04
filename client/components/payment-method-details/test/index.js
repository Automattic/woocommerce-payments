/** @format */
/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import PaymentMethodDetails from '..';

describe( 'PaymentMethodDetails', () => {
	test( 'renders a valid card brand and last 4 digits', () => {
		const paymentMethodDetails = renderCard( { brand: 'visa', last4: '4242' } );
		expect( paymentMethodDetails ).toMatchSnapshot();
	} );

	test( 'renders a dash if no card was provided', () => {
		const paymentMethodDetails = renderCard( null );
		expect( paymentMethodDetails ).toMatchSnapshot();
	} );

	function renderCard( card ) {
		return shallow( <PaymentMethodDetails payment={ { card: card, type: 'card' } } /> );
	}
} );

