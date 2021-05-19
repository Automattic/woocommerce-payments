/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import PaymentMethod from '../payment-method';

describe( 'PaymentMethod', () => {
	test( 'renders label and description', () => {
		render( <PaymentMethod label="Foo" description="Bar" /> );

		expect( screen.queryByText( 'Foo' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Bar' ) ).toBeInTheDocument();
	} );

	test( 'does not render "Delete" when the handler is not provided', () => {
		render( <PaymentMethod label="Foo" onDeleteClick={ undefined } /> );

		const deleteButton = screen.queryByRole( 'button', {
			name: 'Delete Foo from checkout',
		} );

		expect( deleteButton ).not.toBeInTheDocument();
	} );

	test( 'clicking the "Delete" button with confirmation calls onDeleteClick()', () => {
		const handleDeleteClickMock = jest.fn();
		render(
			<PaymentMethod
				label="Foo"
				id="foo"
				onDeleteClick={ handleDeleteClickMock }
			/>
		);

		user.click(
			screen.getByRole( 'button', {
				name: 'Delete Foo from checkout',
			} )
		);
		user.click(
			screen.getByRole( 'button', {
				name: 'Cancel',
			} )
		);

		expect( handleDeleteClickMock ).not.toHaveBeenCalled();

		user.click(
			screen.getByRole( 'button', {
				name: 'Delete Foo from checkout',
			} )
		);
		user.click(
			screen.getByRole( 'button', {
				name: 'Remove',
			} )
		);

		expect( handleDeleteClickMock ).toHaveBeenCalledTimes( 1 );
		expect( handleDeleteClickMock ).toHaveBeenCalledWith( 'foo' );
	} );
} );
