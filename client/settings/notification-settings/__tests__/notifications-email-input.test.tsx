/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import NotificationsEmailInput from '../notifications-email-input';
import { useGetSavingError, useCommunicationsEmail } from 'wcpay/data';

jest.mock( 'wcpay/data', () => ( {
	useCommunicationsEmail: jest.fn(),
	useGetSavingError: jest.fn(),
} ) );

const mockUseCommunicationsEmail = useCommunicationsEmail as jest.MockedFunction<
	typeof useCommunicationsEmail
>;
const mockUseGetSavingError = useGetSavingError as jest.MockedFunction<
	typeof useGetSavingError
>;

describe( 'NotificationsEmailInput', () => {
	beforeEach( () => {
		mockUseCommunicationsEmail.mockReturnValue( [
			'communications@test.com',
			jest.fn(),
		] );
		mockUseGetSavingError.mockReturnValue( null );
	} );

	it( 'displays and updates email address', () => {
		const oldEmail = 'old.communications@test.com';
		const setCommunicationsEmail = jest.fn();
		mockUseCommunicationsEmail.mockReturnValue( [
			oldEmail,
			setCommunicationsEmail,
		] );

		render( <NotificationsEmailInput /> );

		expect( screen.getByDisplayValue( oldEmail ) ).toBeInTheDocument();

		const newEmail = 'new.communications@test.com';
		fireEvent.change( screen.getByLabelText( 'Communications email' ), {
			target: { value: newEmail },
		} );

		expect( setCommunicationsEmail ).toHaveBeenCalledWith( newEmail );
	} );

	it( 'displays error message for empty email', () => {
		mockUseCommunicationsEmail.mockReturnValue( [ '', jest.fn() ] );
		mockUseGetSavingError.mockReturnValue( {
			code: 'rest_invalid_param',
			message: 'Invalid parameter(s): communications_email',
			data: {
				status: 400,
				params: {
					communications_email:
						'Error: Communications email is required.',
				},
				details: {
					communications_email: {
						code: 'rest_invalid_pattern',
						message: 'Error: Communications email is required.',
						data: null,
					},
				},
			},
		} );

		const { container } = render( <NotificationsEmailInput /> );
		expect(
			container.querySelector( '.components-notice.is-error' )
				?.textContent
		).toMatch( /Error: Communications email is required./ );
	} );

	it( 'displays the error message for invalid email', () => {
		mockUseCommunicationsEmail.mockReturnValue( [
			'invalid.email',
			jest.fn(),
		] );
		mockUseGetSavingError.mockReturnValue( {
			code: 'rest_invalid_param',
			message: 'Invalid parameter(s): communications_email',
			data: {
				status: 400,
				params: {
					communications_email:
						'Error: Invalid email address: invalid.email',
				},
				details: {
					communications_email: {
						code: 'rest_invalid_pattern',
						message: 'Error: Invalid email address: invalid.email',
						data: null,
					},
				},
			},
		} );

		const { container } = render( <NotificationsEmailInput /> );
		expect(
			container.querySelector( '.components-notice.is-error' )
				?.textContent
		).toMatch( /Error: Invalid email address: / );
	} );

	it( 'does not display error when saving error is null', () => {
		mockUseCommunicationsEmail.mockReturnValue( [
			'valid@test.com',
			jest.fn(),
		] );
		mockUseGetSavingError.mockReturnValue( null );

		const { container } = render( <NotificationsEmailInput /> );
		expect(
			container.querySelector( '.components-notice.is-error' )
		).toBeNull();
	} );

	it( 'renders help text', () => {
		render( <NotificationsEmailInput /> );

		expect(
			screen.getByText(
				'Email address used for WooPayments communications.'
			)
		).toBeInTheDocument();
	} );
} );
