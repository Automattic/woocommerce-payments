/** @format */

/**
 * External dependencies
 */
import { render, screen, within } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentMethodSettings from '..';

jest.mock( '../../../data', () => ( {
	useSettings: jest.fn().mockReturnValue( {} ),
	usePaymentRequestButtonType: jest.fn().mockReturnValue( [ 'buy' ] ),
	usePaymentRequestButtonSize: jest.fn().mockReturnValue( [ 'default' ] ),
	usePaymentRequestButtonTheme: jest.fn().mockReturnValue( [ 'dark' ] ),
} ) );

describe( 'PaymentMethodSettings', () => {
	test( 'renders title and description', () => {
		render( <PaymentMethodSettings methodId="payment_request" /> );

		const heading = screen.queryByRole( 'heading', {
			name: 'Express checkouts',
		} );
		expect( heading ).toBeInTheDocument();
	} );

	test( 'renders settings', () => {
		render( <PaymentMethodSettings methodId="payment_request" /> );

		expect(
			screen.queryByRole( 'heading', { name: 'Call to action' } )
		).toBeInTheDocument();
	} );

	test( 'renders breadcrumbs', () => {
		render( <PaymentMethodSettings methodId="payment_request" /> );

		const linkToPayments = screen.getByRole( 'link', {
			name: 'WooCommerce Payments',
		} );
		const breadcrumbs = linkToPayments.closest( 'h2' );

		const methodName = within( breadcrumbs ).getByText(
			'Express checkouts'
		);
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
		render( <PaymentMethodSettings methodId="payment_request" /> );

		const heading = screen.queryByRole( 'heading', {
			name: 'Express checkouts',
		} );
		expect( heading ).toBeInTheDocument();
	} );

	test( 'renders banner at the top', () => {
		render( <PaymentMethodSettings methodId="payment_request" /> );

		const banner = screen.queryByTitle( 'WooCommerce Payments' );
		expect( banner ).toBeInTheDocument();
	} );
} );
