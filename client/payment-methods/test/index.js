/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { fireEvent, render, screen, within } from '@testing-library/react';
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

	test( 'enabled methods are rendered with "Manage" and "Delete" buttons', () => {
		useEnabledPaymentMethodIds.mockReturnValue( {
			enabledPaymentMethodIds: [
				'woocommerce_payments',
				'woocommerce_payments_sepa',
			],
		} );

		render( <PaymentMethods /> );

		const cc = screen.getByText( 'Credit card / debit card' );
		const listItem = cc.closest( 'li' );

		expect(
			within( listItem ).queryByRole( 'link', { name: 'Manage' } )
		).toBeInTheDocument();
		expect(
			within( listItem ).queryByRole( 'button', { name: 'Delete' } )
		).toBeInTheDocument();
	} );

	test( 'when only one enabled method is rendered, the "Delete" button is not visible', () => {
		useEnabledPaymentMethodIds.mockReturnValue( {
			enabledPaymentMethodIds: [ 'woocommerce_payments' ],
		} );

		render( <PaymentMethods /> );

		const cc = screen.getByText( 'Credit card / debit card' );
		const listItem = cc.closest( 'li' );

		expect(
			within( listItem ).queryByRole( 'link', { name: 'Manage' } )
		).toBeInTheDocument();
		expect(
			within( listItem ).queryByRole( 'button', { name: 'Delete' } )
		).not.toBeInTheDocument();
	} );

	test( 'clicking delete updates enabled method IDs', () => {
		const enabledMethodIds = [
			'woocommerce_payments',
			'woocommerce_payments_sepa',
			'woocommerce_payments_giropay',
			'woocommerce_payments_sofort',
		];

		useEnabledPaymentMethodIds.mockReturnValue( {
			enabledPaymentMethodIds: enabledMethodIds,
			updateEnabledPaymentMethodIds: jest.fn( () => {} ),
		} );

		render( <PaymentMethods /> );

		const cc = screen.getByText( 'Credit card / debit card' );
		const ccListItem = cc.closest( 'li' );
		const ccDeleteButton = within( ccListItem ).getByRole( 'button', {
			name: 'Delete',
		} );
		user.click( ccDeleteButton );

		const expectedUpdatedMethodIds = enabledMethodIds.filter(
			( id ) => 'woocommerce_payments' !== id
		);
		expect(
			useEnabledPaymentMethodIds().updateEnabledPaymentMethodIds
		).toHaveBeenCalledWith( expectedUpdatedMethodIds );
	} );
} );
