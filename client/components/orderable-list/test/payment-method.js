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
import { getPaymentMethodSettingsUrl } from '../../../utils';

describe( 'PaymentMethod', () => {
	test( 'renders label and description', () => {
		render( <PaymentMethod label="Foo" description="Bar" /> );

		expect( screen.getByText( 'Foo' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Bar' ) ).toBeInTheDocument();
	} );

	test( 'renders "Manage" and "Delete"', () => {
		render( <PaymentMethod label="Foo" /> );

		const manageLink = screen.getByRole( 'link', {
			name: 'Manage',
		} );

		const deleteButton = screen.getByRole( 'button', {
			name: 'Delete',
		} );

		expect( manageLink ).toBeInTheDocument();
		expect( deleteButton ).toBeInTheDocument();
	} );

	test( '"Manage" is a link to expected URL', () => {
		render( <PaymentMethod id="foo" label="Bar" /> );

		const manageLink = screen.getByRole( 'link', {
			name: 'Manage',
		} );
		expect( manageLink.getAttribute( 'href' ) ).toEqual(
			getPaymentMethodSettingsUrl( 'foo' )
		);
	} );

	test( 'clicking "Delete" button calls onDeleteClick()', () => {
		const onDeleteClick = jest.fn();
		render( <PaymentMethod label="Foo" onDeleteClick={ onDeleteClick } /> );

		const deleteButton = screen.getByRole( 'button', {
			name: 'Delete',
		} );
		user.click( deleteButton );
		expect( onDeleteClick ).toHaveBeenCalled();
	} );

	test( 'label is a link to expected URL', () => {
		render( <PaymentMethod id="foo" label="Bar" /> );

		const paymentMethodLabel = screen.getByRole( 'link', {
			name: 'Bar',
		} );
		expect( paymentMethodLabel.getAttribute( 'href' ) ).toEqual(
			getPaymentMethodSettingsUrl( 'foo' )
		);
	} );
} );
