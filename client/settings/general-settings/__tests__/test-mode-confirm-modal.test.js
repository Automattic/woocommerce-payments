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
import TestModeConfirmationModal from '../test-mode-confirm-modal';

const mockOnClose = jest.fn();
const mockOnConfirm = jest.fn();

describe( 'Sandbox Mode Confirmation Modal', () => {
	const renderTestModeConfirmationModal = () => {
		return render(
			<TestModeConfirmationModal
				onClose={ mockOnClose }
				onConfirm={ mockOnConfirm }
			/>
		);
	};

	it( 'Sandbox mode confirmation modal asks confirmation', () => {
		renderTestModeConfirmationModal();
		expect(
			screen.queryByText( 'Are you sure you want to enable test mode?' )
		).toBeInTheDocument();
	} );

	it( 'triggers the onClose function on close button click', async () => {
		renderTestModeConfirmationModal();
		const closeButton = screen.queryByRole( 'button', { name: 'Cancel' } );
		expect( mockOnClose ).not.toBeCalled();
		await user.click( closeButton );
		expect( mockOnClose ).toBeCalled();
	} );

	it( 'triggers the onConfirm function on Enable button click', async () => {
		renderTestModeConfirmationModal();
		const enableButton = screen.queryByRole( 'button', { name: 'Enable' } );
		expect( mockOnConfirm ).not.toBeCalled();
		await user.click( enableButton );
		expect( mockOnConfirm ).toBeCalled();
	} );
} );
