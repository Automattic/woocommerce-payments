/** @format */

/**
 * External dependencies
 */
import { render, screen, within } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentMethodSettings from '..';

describe( 'PaymentMethodSettings', () => {
	test( 'renders title and description', () => {
		render(
			<PaymentMethodSettings methodId="woocommerce_payments_giropay" />
		);

		const heading = screen.queryByRole( 'heading', { name: 'giropay' } );
		expect( heading ).toBeInTheDocument();
	} );

	test( 'renders settings', () => {
		render(
			<PaymentMethodSettings methodId="woocommerce_payments_giropay" />
		);

		expect(
			screen.queryByText( 'Giropay settings placeholder.' )
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

		const errorMessage = screen.queryByText(
			'Invalid payment method ID specified.'
		);
		expect( errorMessage ).toBeInTheDocument();
	} );

	test( 'renders digital wallets settings and confirm its h2 copy', () => {
		render(
			<PaymentMethodSettings methodId="woocommerce_payments_digital_wallets" />
		);

		const heading = screen.queryByRole( 'heading', {
			name: 'Digital wallets & saved cards',
		} );
		expect( heading ).toBeInTheDocument();
	} );

	test( 'renders banner at the top', () => {
		render(
			<PaymentMethodSettings methodId="woocommerce_payments_digital_wallets" />
		);

		const banner = screen.queryByTitle( 'WooCommerce Payments' );
		expect( banner ).toBeInTheDocument();
	} );
} );
