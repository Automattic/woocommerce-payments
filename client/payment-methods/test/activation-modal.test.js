/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import ConfirmPaymentMethodActivationModal from '../activation-modal';

const mockOnClose = jest.fn();
const mockOnConfirm = jest.fn();

describe( 'Activation Modal', () => {
	beforeAll( () => {
		global.wcpaySettings = {
			accountEmail: 'admin@example.com',
		};
	} );
	afterEach( () => {
		jest.resetAllMocks();
	} );

	const renderActivationModal = ( requirements ) => {
		return render(
			<ConfirmPaymentMethodActivationModal
				paymentMethod={ 'card' }
				onClose={ mockOnClose }
				onConfirmClose={ mockOnConfirm }
				requirements={ requirements }
			/>
		);
	};

	it( 'matches the snapshot', () => {
		const container = renderActivationModal( [ 'company.tax_id' ] )
			.baseElement;
		expect( container ).toMatchSnapshot();
	} );

	it( 'contains the requirement text', () => {
		renderActivationModal( [ 'company.tax_id' ] );
		expect( screen.queryByText( 'Business Number' ) ).toBeInTheDocument();
	} );

	it( 'contains the email address of the current merchant', () => {
		renderActivationModal( [ 'company.tax_id' ] );
		expect( screen.queryByText( 'admin@example.com' ) ).toBeInTheDocument();
	} );

	it( 'doesnt contain the requirement text when there isnt a translation', () => {
		renderActivationModal( [ 'person.tax_id' ] );
		expect( screen.queryByText( 'person.tax_id' ) ).not.toBeInTheDocument();
	} );

	it( 'triggers the onClose event on close button click', () => {
		renderActivationModal( [] );
		const closeButton = screen.queryByRole( 'button', { name: 'Cancel' } );
		expect( mockOnClose ).not.toBeCalled();
		user.click( closeButton );
		expect( mockOnClose ).toBeCalled();
	} );

	it( 'triggers the onConfirmClose event on confirm button click', () => {
		renderActivationModal( [] );
		const confirmButton = screen.queryByRole( 'button', {
			name: 'Continue',
		} );
		expect( mockOnConfirm ).not.toBeCalled();
		user.click( confirmButton );
		expect( mockOnConfirm ).toBeCalled();
	} );
} );
