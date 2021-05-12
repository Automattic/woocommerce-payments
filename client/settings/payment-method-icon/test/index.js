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

describe( 'PaymentMethodIcon', () => {
	test( 'renders GiroPay payment method icon', () => {
		const { container } = render( <PaymentMethodIcon name="giropay" /> );
		expect( container.querySelector( 'svg' ) ).toBeInTheDocument();
	} );

	test( 'renders Sepa payment method icon', () => {
		const { container } = render( <PaymentMethodIcon name="sepa" /> );
		expect( container.querySelector( 'svg' ) ).toBeInTheDocument();
	} );

	test( 'renders GiroPay payment method icon', () => {
		const { container } = render( <PaymentMethodIcon name="sofort" /> );
		expect( container.querySelector( 'svg' ) ).toBeInTheDocument();
	} );

	test( 'renders GiroPay payment method icon and label', () => {
		render( <PaymentMethodIcon name="giropay" showName /> );

		const label = screen.queryByText( 'GiroPay' );
		expect( label ).toBeInTheDocument();
	} );

	test( 'renders Sepa payment method icon and label', () => {
		render( <PaymentMethodIcon name="sepa" showName /> );

		const label = screen.queryByText( 'Direct Debit Payments' );
		expect( label ).toBeInTheDocument();
	} );

	test( 'renders GiroPay payment method icon and label', () => {
		render( <PaymentMethodIcon name="sofort" showName /> );

		const label = screen.queryByText( 'Sofort' );
		expect( label ).toBeInTheDocument();
	} );
	test( 'renders nothing when using an invalid icon name', () => {
		const { container } = render( <PaymentMethodIcon name="wrong" /> );

		expect( container.firstChild ).toBeNull();
	} );
} );
