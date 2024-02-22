/** @format */
/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom/extend-expect';

/**
 * Internal dependencies
 */
import PaymentMethodIcon from '..';
import paymentMethodsMap from 'wcpay/payment-methods-map';

describe( 'PaymentMethodIcon', () => {
	it( 'renders BECS payment method icon', () => {
		const { container } = render(
			<PaymentMethodIcon
				Icon={ paymentMethodsMap.au_becs_debit.icon }
				label={ paymentMethodsMap.au_becs_debit.label }
			/>
		);

		expect( container.querySelector( 'img' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'BECS Direct Debit' ) ).toBeInTheDocument();
	} );

	it( 'does not render the label, if not passed', () => {
		render(
			<PaymentMethodIcon Icon={ paymentMethodsMap.au_becs_debit.icon } />
		);

		expect(
			screen.queryByText( 'BECS Direct Debit' )
		).not.toBeInTheDocument();
	} );

	it( "doesn't render anything, if the icon isn' provided", () => {
		const { container } = render( <PaymentMethodIcon /> );

		expect( container.firstChild ).toBeNull();
	} );
} );
