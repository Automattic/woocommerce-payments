/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentMethodDetails from '..';

global.wcpaySettings = {
	accountStatus: {
		country: 'US',
	},
};

describe( 'PaymentMethodDetails', () => {
	test( 'renders a valid card brand and last 4 digits', () => {
		const { container: paymentMethodDetails } = renderCard( {
			brand: 'visa',
			last4: '4242',
		} );
		expect( paymentMethodDetails ).toMatchSnapshot();
	} );

	test( 'renders the eftpos_au card-present brand and last 4 digits', () => {
		const { container } = render(
			<PaymentMethodDetails
				payment={ {
					type: 'card_present',
					card_present: {
						brand: 'eftpos_au',
						last4: '0978',
					},
				} }
			/>
		);

		expect(
			container.querySelector(
				'.payment-method__brand--eftpos_au[aria-label="eftpos"]'
			)
		).not.toBeNull();
		expect( container.textContent ).toContain( '0978' );
	} );

	test( 'renders a dash if no card was provided', () => {
		const { container: paymentMethodDetails } = renderCard( null );
		expect( paymentMethodDetails ).toMatchSnapshot();
	} );

	test( 'renders without error when payment type object is undefined (e.g. Link)', () => {
		const { container } = render(
			<PaymentMethodDetails payment={ { type: 'link' } } />
		);
		expect( container ).toMatchSnapshot();
	} );

	function renderCard( card ) {
		return render(
			<PaymentMethodDetails payment={ { card: card, type: 'card' } } />
		);
	}
} );
