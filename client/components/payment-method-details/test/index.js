/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentMethodDetails from '..';

describe( 'PaymentMethodDetails', () => {
	test( 'renders a valid card brand and last 4 digits', () => {
		const { container: paymentMethodDetails } = renderCard( {
			brand: 'visa',
			last4: '4242',
		} );
		expect( paymentMethodDetails ).toMatchSnapshot();
	} );

	test( 'renders a dash if no card was provided', () => {
		const { container: paymentMethodDetails } = renderCard( null );
		expect( paymentMethodDetails ).toMatchSnapshot();
	} );

	function renderCard( card ) {
		return render(
			<PaymentMethodDetails payment={ { card: card, type: 'card' } } />
		);
	}
} );
