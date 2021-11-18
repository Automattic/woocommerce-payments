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
import ConfirmPaymentMethodDeleteModal from '../delete-modal';
import paymentMethodsMap from '../../payment-methods-map';

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

	const renderDeleteModal = () => {
		return render(
			<ConfirmPaymentMethodDeleteModal
				id={ 'card' }
				label={ paymentMethodsMap.card.label }
				Icon={ paymentMethodsMap.card.Icon }
				onCancel={ mockOnClose }
				onConfirm={ mockOnConfirm }
			/>
		);
	};

	it( 'matches the snapshot', () => {
		const container = renderDeleteModal().baseElement;
		expect( container ).toMatchSnapshot();
	} );

	it( 'contains the label of the payment method', () => {
		renderDeleteModal();
		expect(
			screen.queryByText( paymentMethodsMap.card.label )
		).toBeInTheDocument();
	} );

	it( 'triggers the onClose event on close button click', () => {
		renderDeleteModal();
		const closeButton = screen.queryByRole( 'button', { name: 'Cancel' } );
		expect( mockOnClose ).not.toBeCalled();
		user.click( closeButton );
		expect( mockOnClose ).toBeCalled();
	} );

	it( 'triggers the onConfirmClose event on confirm button click', () => {
		renderDeleteModal();
		const confirmButton = screen.queryByRole( 'button', {
			name: 'Remove',
		} );
		expect( mockOnConfirm ).not.toBeCalled();
		user.click( confirmButton );
		expect( mockOnConfirm ).toBeCalled();
	} );
} );
