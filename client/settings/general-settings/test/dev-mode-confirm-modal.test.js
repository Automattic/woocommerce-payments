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
import DevModeConfirmationModal from '../dev-mode-confirm-modal';

const mockOnClose = jest.fn();
const mockOnConfirm = jest.fn();

describe( 'Dev Mode Confirmation Modal', () => {
	const renderDevModeConfirmationModal = () => {
		return render(
			<DevModeConfirmationModal
				onClose={ mockOnClose }
				onConfirm={ mockOnConfirm }
			/>
		);
	};

	it( 'Dev mode confirmation modal asks confirmation', () => {
		renderDevModeConfirmationModal();
		expect(
			screen.queryByText( 'Are you sure you want to enable test mode?' )
		).toBeInTheDocument();
	} );

	it( 'triggers the onClose function on close button click', () => {
		renderDevModeConfirmationModal();
		const closeButton = screen.queryByRole( 'button', { name: 'Cancel' } );
		expect( mockOnClose ).not.toBeCalled();
		user.click( closeButton );
		expect( mockOnClose ).toBeCalled();
	} );

	it( 'triggers the onConfirm function on Enable button click', () => {
		renderDevModeConfirmationModal();
		const enableButton = screen.queryByRole( 'button', { name: 'Enable' } );
		expect( mockOnConfirm ).not.toBeCalled();
		user.click( enableButton );
		expect( mockOnConfirm ).toBeCalled();
	} );
} );
