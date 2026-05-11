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

	test( 'prefers the routed network for co-branded card-present payments', () => {
		const { container } = render(
			<PaymentMethodDetails
				payment={ {
					type: 'card_present',
					card_present: {
						brand: 'visa',
						network: 'eftpos_au',
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
		expect(
			container.querySelector( '.payment-method__brand--visa' )
		).toBeNull();
		expect( container.textContent ).toContain( '0978' );
	} );

	test( 'falls back to brand for unsupported card-present networks', () => {
		const { container } = render(
			<PaymentMethodDetails
				payment={ {
					type: 'card_present',
					card_present: {
						brand: 'visa',
						network: 'unsupported_network',
						last4: '0978',
					},
				} }
			/>
		);

		expect(
			container.querySelector( '.payment-method__brand--visa' )
		).not.toBeNull();
		expect(
			container.querySelector(
				'.payment-method__brand--unsupported_network'
			)
		).toBeNull();
		expect( container.textContent ).toContain( '0978' );
	} );

	test( 'falls back to network when brand is unavailable', () => {
		const { container } = render(
			<PaymentMethodDetails
				payment={ {
					type: 'card_present',
					card_present: {
						network: 'unsupported_network',
						last4: '0978',
					},
				} }
			/>
		);

		expect(
			container.querySelector(
				'.payment-method__brand--unsupported_network'
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

	test( 'renders Amazon Pay with funding card brand and last4', () => {
		const { container } = render(
			<PaymentMethodDetails
				payment={ {
					type: 'amazon_pay',
					amazon_pay: {
						funding: {
							type: 'card',
							card: {
								brand: 'visa',
								last4: '4242',
							},
						},
						transaction_id: null,
					},
				} }
			/>
		);
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders Amazon Pay without funding card', () => {
		const { container } = render(
			<PaymentMethodDetails
				payment={ {
					type: 'amazon_pay',
					amazon_pay: {
						transaction_id: null,
					},
				} }
			/>
		);
		expect( container ).toMatchSnapshot();
	} );

	function renderCard( card ) {
		return render(
			<PaymentMethodDetails payment={ { card: card, type: 'card' } } />
		);
	}
} );
