/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import PaymentMethods from '../';
import { useEnabledPaymentMethodIds } from 'data';

jest.mock( '../../data', () => ( {
	useEnabledPaymentMethodIds: jest.fn(),
} ) );

describe( 'PaymentMethods', () => {
	beforeEach( () => {
		useEnabledPaymentMethodIds.mockReturnValue( {
			enabledPaymentMethodIds: [],
			updateEnabledPaymentMethodIds: jest.fn(),
		} );
	} );

	test( 'renders the "Add payment method" button', () => {
		render( <PaymentMethods /> );

		const addPaymentMethodButton = screen.queryByRole( 'button', {
			name: 'Add payment method',
		} );

		expect( addPaymentMethodButton ).toBeInTheDocument();
	} );

	test( '"Add payment method" button opens the payment methods selector modal', () => {
		render( <PaymentMethods /> );

		const addPaymentMethodButton = screen.getByRole( 'button', {
			name: 'Add payment method',
		} );

		fireEvent.click( addPaymentMethodButton );
		expect(
			screen.queryByText( 'Add payment methods' )
		).toBeInTheDocument();
	} );

	test( 'payment methods are rendered in expected lists', () => {
		useEnabledPaymentMethodIds.mockReturnValue( {
			enabledPaymentMethodIds: [
				'woocommerce_payments',
				'woocommerce_payments_sepa',
			],
		} );

		render( <PaymentMethods /> );

		const cc = screen.getByText( 'Credit card / debit card' );
		const sepa = screen.getByText( 'Direct debit payment' );
		[ cc, sepa ].forEach( ( method ) => {
			expect( method.closest( 'ul' ) ).toHaveClass(
				'payment-methods__enabled-methods'
			);
		} );

		const giropay = screen.getByLabelText( 'giropay' );
		const sofort = screen.getByLabelText( 'Sofort' );
		[ giropay, sofort ].forEach( ( method ) => {
			expect( method.closest( 'ul' ) ).toHaveClass(
				'payment-methods__available-methods'
			);
		} );
	} );

	test( 'enabled methods are rendered with "Delete" buttons', () => {
		useEnabledPaymentMethodIds.mockReturnValue( {
			enabledPaymentMethodIds: [
				'woocommerce_payments',
				'woocommerce_payments_sepa',
			],
		} );

		render( <PaymentMethods /> );

		expect(
			screen.queryByRole( 'button', {
				name: 'Delete Credit card / debit card from checkout',
			} )
		).toBeInTheDocument();
	} );

	test( 'when only one enabled method is rendered, the "Delete" button is not visible', () => {
		useEnabledPaymentMethodIds.mockReturnValue( {
			enabledPaymentMethodIds: [ 'woocommerce_payments' ],
		} );

		render( <PaymentMethods /> );

		expect(
			screen.queryByRole( 'button', {
				name: 'Delete Credit card / debit card from checkout',
			} )
		).not.toBeInTheDocument();
	} );

	test( 'clicking delete updates enabled method IDs', () => {
		useEnabledPaymentMethodIds.mockReturnValue( {
			enabledPaymentMethodIds: [
				'woocommerce_payments',
				'woocommerce_payments_sepa',
				'woocommerce_payments_giropay',
				'woocommerce_payments_sofort',
			],
			updateEnabledPaymentMethodIds: jest.fn( () => {} ),
		} );

		render( <PaymentMethods /> );

		const ccDeleteButton = screen.getByRole( 'button', {
			name: 'Delete Credit card / debit card from checkout',
		} );
		user.click( ccDeleteButton );
		user.click(
			screen.getByRole( 'button', {
				name: 'Remove',
			} )
		);

		expect(
			useEnabledPaymentMethodIds().updateEnabledPaymentMethodIds
		).toHaveBeenCalledWith( [
			'woocommerce_payments_sepa',
			'woocommerce_payments_giropay',
			'woocommerce_payments_sofort',
		] );
	} );
} );
