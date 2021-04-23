/** @format */

/**
 * External dependencies
 */
import { render, screen, within } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentMethodSettings from '../';

describe( 'PaymentMethodSettings', () => {
	test( 'renders title and description', () => {
		render(
			<PaymentMethodSettings methodId="woocommerce_payments_giropay" />
		);

		const heading = screen.getByRole( 'heading', { name: 'giropay' } );
		expect( heading ).toBeInTheDocument();
	} );

	test( 'renders settings', () => {
		render(
			<PaymentMethodSettings methodId="woocommerce_payments_giropay" />
		);

		expect(
			screen.getByText( 'Giropay settings placeholder.' )
		).toBeInTheDocument();
	} );

	test( 'renders breadcrumbs', () => {
		render(
			<PaymentMethodSettings methodId="woocommerce_payments_giropay" />
		);

		const linkToPayments = screen.getByRole( 'link', {
			name: 'WooCommerce Payments',
		} );
		const breadcrumbs = linkToPayments.closest( 'h2' );

		const methodName = within( breadcrumbs ).getByText( 'giropay' );
		expect( breadcrumbs ).toContainElement( methodName );
	} );

	test( 'renders error message for invalid method IDs', () => {
		render( <PaymentMethodSettings methodId="foo" /> );

		const errorMessage = screen.getByText(
			'Invalid payment method ID specified.'
		);
		expect( errorMessage ).toBeInTheDocument();
	} );
} );
