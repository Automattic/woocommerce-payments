/** @format */

/**
 * External dependencies
 */
import React, { act } from 'react';
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';

// Mock ResizeObserver for modal components - comprehensive setup
class MockResizeObserver {
	observe() {}
	unobserve() {}
	disconnect() {}
}

// Set up ResizeObserver mock in multiple places to ensure it's available
global.ResizeObserver = MockResizeObserver;
global.window = global.window || {};
global.window.ResizeObserver = MockResizeObserver;

// Ensure it's available on window in JSDOM environment
Object.defineProperty( window, 'ResizeObserver', {
	writable: true,
	configurable: true,
	value: MockResizeObserver,
} );

/**
 * Internal dependencies
 */
import ConfirmPaymentMethodDeleteModal from '../delete-modal';
import paymentMethodsMap from 'wcpay/payment-methods-map';

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
				icon={ paymentMethodsMap.card.icon }
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

	it( 'triggers the onClose event on close button click', async () => {
		renderDeleteModal();
		const closeButton = screen.queryByRole( 'button', { name: 'Cancel' } );
		expect( mockOnClose ).not.toBeCalled();
		await act( async () => {
			await user.click( closeButton );
		} );
		expect( mockOnClose ).toHaveBeenCalled();
	} );

	it( 'triggers the onConfirmClose event on confirm button click', async () => {
		renderDeleteModal();
		const confirmButton = screen.queryByRole( 'button', {
			name: 'Remove',
		} );
		expect( mockOnConfirm ).not.toHaveBeenCalled();
		await act( async () => {
			await user.click( confirmButton );
		} );
		expect( mockOnConfirm ).toHaveBeenCalled();
	} );
} );
