/** @format */

/**
 * External dependencies
 */
import { fireEvent, render, screen, within } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import PaymentMethods from '../';

jest.mock( 'data', () => ( { addSelectedPaymentMethods: jest.fn() } ) );

describe( 'PaymentMethods', () => {
	test( 'renders the "Add payment method" button', () => {
		render(
			<PaymentMethods
				enabledMethodIds={ [] }
				onEnabledMethodIdsChange={ () => {} }
			/>
		);

		const addPaymentMethodButton = screen.queryByRole( 'button', {
			name: 'Add payment method',
		} );

		expect( addPaymentMethodButton ).toBeInTheDocument();
	} );

	test( '"Add payment method" button opens the payment methods selector modal', () => {
		render(
			<PaymentMethods
				enabledMethodIds={ [] }
				onEnabledMethodIdsChange={ () => {} }
			/>
		);

		const addPaymentMethodButton = screen.getByRole( 'button', {
			name: 'Add payment method',
		} );

		fireEvent.click( addPaymentMethodButton );
		expect(
			screen.queryByText( 'Add payment methods' )
		).toBeInTheDocument();
	} );

	test( 'payment methods are rendered in expected lists', () => {
		const enabledMethodIds = [ 'cc', 'sepa' ];

		render(
			<PaymentMethods
				enabledMethodIds={ enabledMethodIds }
				onEnabledMethodIdsChange={ () => {} }
			/>
		);

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
		const enabledMethodIds = [ 'cc', 'sepa' ];

		render(
			<PaymentMethods
				enabledMethodIds={ enabledMethodIds }
				onEnabledMethodIdsChange={ () => {} }
			/>
		);

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
		const enabledMethodIds = [ 'cc' ];

		render(
			<PaymentMethods
				enabledMethodIds={ enabledMethodIds }
				onEnabledMethodIdsChange={ () => {} }
			/>
		);

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
		const enabledMethodIds = [ 'cc', 'sepa', 'giropay', 'sofort' ];
		const onEnabledMethodIdsChange = jest.fn();

		render(
			<PaymentMethods
				enabledMethodIds={ enabledMethodIds }
				onEnabledMethodIdsChange={ onEnabledMethodIdsChange }
			/>
		);

		const cc = screen.getByText( 'Credit card / debit card' );
		const ccListItem = cc.closest( 'li' );
		const ccDeleteButton = within( ccListItem ).getByRole( 'button', {
			name: 'Delete',
		} );
		user.click( ccDeleteButton );
		user.click(
			screen.getByRole( 'button', {
				name: 'Remove',
			} )
		);

		const expectedUpdatedMethodIds = enabledMethodIds.filter(
			( id ) => 'cc' !== id
		);
		expect( onEnabledMethodIdsChange ).toHaveBeenCalledWith(
			expectedUpdatedMethodIds
		);
	} );
} );
