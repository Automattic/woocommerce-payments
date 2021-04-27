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

		const addPaymentMethodButton = screen.getByRole( 'button', {
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
		expect( screen.getByText( 'Add payment methods' ) ).toBeInTheDocument();
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
		const enabledMethodIds = [ 'cc' ];

		render(
			<PaymentMethods
				enabledMethodIds={ enabledMethodIds }
				onEnabledMethodIdsChange={ () => {} }
			/>
		);

		const cc = screen.getByText( 'Credit card / debit card' );
		const listItem = cc.closest( 'li' );

		expect( listItem ).toContainElement(
			within( listItem ).getByRole( 'link', { name: 'Manage' } )
		);
		expect( listItem ).toContainElement(
			within( listItem ).getByRole( 'button', { name: 'Delete' } )
		);
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

		const expectedUpdatedMethodIds = enabledMethodIds.filter(
			( id ) => 'cc' !== id
		);
		expect( onEnabledMethodIdsChange ).toHaveBeenCalledWith(
			expectedUpdatedMethodIds
		);
	} );

	test( 'all methods are rendered with their IDs as classes', () => {
		const enabledMethods = {
			'Credit card / debit card': 'cc',
			giropay: 'giropay',
		};
		const availableMethods = {
			Sofort: 'sofort',
			'Direct debit payment': 'sepa',
		};

		render(
			<PaymentMethods
				enabledMethodIds={ Object.values( enabledMethods ) }
				onEnabledMethodIdsChange={ () => {} }
			/>
		);

		Object.entries( enabledMethods ).forEach(
			( [ label, expectedClass ] ) => {
				expect( screen.getByText( label ).closest( 'li' ) ).toHaveClass(
					expectedClass
				);
			}
		);

		Object.entries( availableMethods ).forEach(
			( [ label, expectedClass ] ) => {
				expect( screen.getByLabelText( label ) ).toHaveClass(
					expectedClass
				);
			}
		);
	} );
} );
