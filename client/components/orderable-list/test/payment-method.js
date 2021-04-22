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

		expect( screen.getByText( 'Foo' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Bar' ) ).toBeInTheDocument();
	} );

	test( 'renders Manage and Delete buttons', () => {
		render( <PaymentMethod label="Foo" /> );

		const manageButton = screen.getByRole( 'button', {
			name: 'Manage',
		} );

		const deleteButton = screen.getByRole( 'button', {
			name: 'Delete',
		} );

		expect( manageButton ).toBeInTheDocument( manageButton );
		expect( deleteButton ).toBeInTheDocument( deleteButton );
	} );

	test( 'clicking Manage button calls onManageClick()', () => {
		const onManageClick = jest.fn();
		render( <PaymentMethod label="Foo" onManageClick={ onManageClick } /> );

		const manageButton = screen.getByRole( 'button', {
			name: 'Manage',
		} );
		user.click( manageButton );
		expect( onManageClick ).toHaveBeenCalled();
	} );

	test( 'clicking Delete button calls onDeleteClick()', () => {
		const onDeleteClick = jest.fn();
		render( <PaymentMethod label="Foo" onDeleteClick={ onDeleteClick } /> );

		const deleteButton = screen.getByRole( 'button', {
			name: 'Delete',
		} );
		user.click( deleteButton );
		expect( onDeleteClick ).toHaveBeenCalled();
	} );

	test( 'clicking label calls onManageClick()', () => {
		const onManageClick = jest.fn();
		render( <PaymentMethod label="Foo" onManageClick={ onManageClick } /> );

		const paymentMethodLabel = screen.getByRole( 'button', {
			name: 'Foo',
		} );
		user.click( paymentMethodLabel );
		expect( onManageClick ).toHaveBeenCalled();
	} );
} );
