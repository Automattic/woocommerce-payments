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
		const { container } = render(
			<PaymentMethodIcon name="woocommerce_payments_giropay" />
		);
		expect( container.querySelector( 'svg' ) ).toBeInTheDocument();
	} );

	test( 'renders Sepa payment method icon', () => {
		const { container } = render(
			<PaymentMethodIcon name="woocommerce_payments_sepa" />
		);
		expect( container.querySelector( 'svg' ) ).toBeInTheDocument();
	} );

	test( 'renders GiroPay payment method icon', () => {
		const { container } = render(
			<PaymentMethodIcon name="woocommerce_payments_sofort" />
		);
		expect( container.querySelector( 'svg' ) ).toBeInTheDocument();
	} );

	test( 'renders GiroPay payment method icon and label', () => {
		render(
			<PaymentMethodIcon name="woocommerce_payments_giropay" showName />
		);

		const label = screen.queryByText( 'GiroPay' );
		expect( label ).toBeInTheDocument();
	} );

	test( 'renders Sepa payment method icon and label', () => {
		render(
			<PaymentMethodIcon name="woocommerce_payments_sepa" showName />
		);

		const label = screen.queryByText( 'Direct Debit Payments' );
		expect( label ).toBeInTheDocument();
	} );

	test( 'renders GiroPay payment method icon and label', () => {
		render(
			<PaymentMethodIcon name="woocommerce_payments_sofort" showName />
		);

		const label = screen.queryByText( 'Sofort' );
		expect( label ).toBeInTheDocument();
	} );
	test( 'renders nothing when using an invalid icon name', () => {
		const { container } = render( <PaymentMethodIcon name="wrong" /> );

		expect( container.firstChild ).toBeNull();
	} );
} );
