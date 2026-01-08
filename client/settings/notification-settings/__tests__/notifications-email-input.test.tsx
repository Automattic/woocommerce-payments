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
import { useGetSavingError, useAccountCommunicationsEmail } from 'wcpay/data';

jest.mock( 'wcpay/data', () => ( {
	useAccountCommunicationsEmail: jest.fn(),
	useGetSavingError: jest.fn(),
} ) );

const mockUseAccountCommunicationsEmail = useAccountCommunicationsEmail as jest.MockedFunction<
	typeof useAccountCommunicationsEmail
>;
const mockUseGetSavingError = useGetSavingError as jest.MockedFunction<
	typeof useGetSavingError
>;

describe( 'NotificationsEmailInput', () => {
	beforeEach( () => {
		mockUseAccountCommunicationsEmail.mockReturnValue( [
			'communications@test.com',
			jest.fn(),
		] );
		mockUseGetSavingError.mockReturnValue( null );
	} );

	it( 'displays and updates email address', () => {
		const oldEmail = 'old.communications@test.com';
		const setAccountCommunicationsEmail = jest.fn();
		mockUseAccountCommunicationsEmail.mockReturnValue( [
			oldEmail,
			setAccountCommunicationsEmail,
		] );

		render( <NotificationsEmailInput /> );

		expect( screen.getByDisplayValue( oldEmail ) ).toBeInTheDocument();

		const newEmail = 'new.communications@test.com';
		fireEvent.change( screen.getByLabelText( 'Email address' ), {
			target: { value: newEmail },
		} );

		expect( setAccountCommunicationsEmail ).toHaveBeenCalledWith(
			newEmail
		);
	} );

	it( 'displays error message for empty email', () => {
		mockUseAccountCommunicationsEmail.mockReturnValue( [ '', jest.fn() ] );
		mockUseGetSavingError.mockReturnValue( {
			code: 'rest_invalid_param',
			message: 'Invalid parameter(s): account_communications_email',
			data: {
				status: 400,
				params: {
					account_communications_email:
						'Error: Communications email is required.',
				},
				details: {
					account_communications_email: {
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
		mockUseAccountCommunicationsEmail.mockReturnValue( [
			'invalid.email',
			jest.fn(),
		] );
		mockUseGetSavingError.mockReturnValue( {
			code: 'rest_invalid_param',
			message: 'Invalid parameter(s): account_communications_email',
			data: {
				status: 400,
				params: {
					account_communications_email:
						'Error: Invalid email address: invalid.email',
				},
				details: {
					account_communications_email: {
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
		mockUseAccountCommunicationsEmail.mockReturnValue( [
			'valid@test.com',
			jest.fn(),
		] );
		mockUseGetSavingError.mockReturnValue( null );

		const { container } = render( <NotificationsEmailInput /> );
		expect(
			container.querySelector( '.components-notice.is-error' )
		).toBeNull();
	} );

	it( 'renders section header and description', () => {
		render( <NotificationsEmailInput /> );

		expect( screen.getByText( 'Notifications email' ) ).toBeInTheDocument();
		expect(
			screen.getByText(
				'Provide an email address where you would like to receive communications about your WooPayments account.'
			)
		).toBeInTheDocument();
	} );

	it( 'displays client-side validation error for invalid email after blur', () => {
		mockUseAccountCommunicationsEmail.mockReturnValue( [
			'invalid-email',
			jest.fn(),
		] );
		mockUseGetSavingError.mockReturnValue( null );

		const { container } = render( <NotificationsEmailInput /> );

		// Error should not be shown before blur
		expect(
			container.querySelector( '.components-notice.is-error' )
		).toBeNull();

		// Trigger blur event
		fireEvent.blur( screen.getByLabelText( 'Email address' ) );

		// Error should be shown after blur
		expect(
			container.querySelector( '.components-notice.is-error' )
				?.textContent
		).toMatch( /Please enter a valid email address./ );
	} );

	it( 'does not display client-side validation error for valid email after blur', () => {
		mockUseAccountCommunicationsEmail.mockReturnValue( [
			'valid@test.com',
			jest.fn(),
		] );
		mockUseGetSavingError.mockReturnValue( null );

		const { container } = render( <NotificationsEmailInput /> );

		// Trigger blur event
		fireEvent.blur( screen.getByLabelText( 'Email address' ) );

		// No error should be shown for valid email
		expect(
			container.querySelector( '.components-notice.is-error' )
		).toBeNull();
	} );

	it( 'server error takes precedence over client-side validation error', () => {
		mockUseAccountCommunicationsEmail.mockReturnValue( [
			'invalid-email',
			jest.fn(),
		] );
		mockUseGetSavingError.mockReturnValue( {
			code: 'rest_invalid_param',
			message: 'Invalid parameter(s): account_communications_email',
			data: {
				status: 400,
				params: {
					account_communications_email:
						'Error: Invalid email address: invalid-email',
				},
				details: {
					account_communications_email: {
						code: 'rest_invalid_pattern',
						message: 'Error: Invalid email address: invalid-email',
						data: null,
					},
				},
			},
		} );

		const { container } = render( <NotificationsEmailInput /> );

		// Trigger blur to enable client-side validation
		fireEvent.blur( screen.getByLabelText( 'Email address' ) );

		// Server error should be shown instead of client-side error
		expect(
			container.querySelector( '.components-notice.is-error' )
				?.textContent
		).toMatch( /Error: Invalid email address: invalid-email/ );
	} );
} );
