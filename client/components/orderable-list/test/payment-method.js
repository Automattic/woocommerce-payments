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

	test( 'renders "Manage" and "Delete"', () => {
		render( <PaymentMethod label="Foo" onDeleteClick={ jest.fn() } /> );

		const manageLink = screen.queryByRole( 'link', {
			name: 'Manage',
		} );

		const deleteButton = screen.queryByRole( 'button', {
			name: 'Delete',
		} );

		expect( manageLink ).toBeInTheDocument();
		expect( deleteButton ).toBeInTheDocument();
	} );

	test( 'does not render "Delete" when the handler is not provided', () => {
		render( <PaymentMethod label="Foo" onDeleteClick={ undefined } /> );

		const manageLink = screen.queryByRole( 'link', {
			name: 'Manage',
		} );

		const deleteButton = screen.queryByRole( 'button', {
			name: 'Delete',
		} );

		expect( manageLink ).toBeInTheDocument();
		expect( deleteButton ).not.toBeInTheDocument();
	} );

	test( '"Manage" is a link to expected URL', () => {
		render( <PaymentMethod id="foo" label="Bar" /> );

		const manageLink = screen.getByRole( 'link', {
			name: 'Manage',
		} );
		expect( manageLink.getAttribute( 'href' ) ).toEqual(
			'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments_foo'
		);
	} );

	test( 'clicking "Delete" button calls onDeleteClick()', () => {
		const handleDeleteClickMock = jest.fn();
		render(
			<PaymentMethod
				label="Foo"
				onDeleteClick={ handleDeleteClickMock }
			/>
		);

		const deleteButton = screen.getByRole( 'button', {
			name: 'Delete',
		} );
		user.click( deleteButton );
		expect( handleDeleteClickMock ).toHaveBeenCalled();
	} );

	test( 'label is a link to expected URL', () => {
		render( <PaymentMethod id="foo" label="Bar" /> );

		const paymentMethodLabel = screen.getByRole( 'link', {
			name: 'Bar',
		} );
		expect( paymentMethodLabel.getAttribute( 'href' ) ).toEqual(
			'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments_foo'
		);
	} );
} );
