/**
 * External dependencies
 */
import { fireEvent, render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import SupportEmailInput from '..';
import {
	useGetSavingError,
	useAccountBusinessSupportEmail,
} from 'wcpay/data/settings';

jest.mock( 'wcpay/data/settings', () => ( {
	useAccountBusinessSupportEmail: jest.fn(),
	useGetSavingError: jest.fn(),
} ) );

describe( 'SupportEmailInput', () => {
	beforeEach( () => {
		useAccountBusinessSupportEmail.mockReturnValue( [
			'test@test.com',
			jest.fn(),
		] );
		useGetSavingError.mockReturnValue( null );
	} );

	it( 'displays and updates email address', async () => {
		const oldEmail = 'old.email@test.com';
		const setSupportEmail = jest.fn();
		useAccountBusinessSupportEmail.mockReturnValue( [
			oldEmail,
			setSupportEmail,
		] );

		render( <SupportEmailInput /> );

		expect( screen.getByDisplayValue( oldEmail ) ).toBeInTheDocument();

		const newEmail = 'new.email@test.com';
		fireEvent.change( screen.getByLabelText( 'Support email' ), {
			target: { value: newEmail },
		} );

		expect( setSupportEmail ).toHaveBeenCalledWith( newEmail );
	} );

	it( 'no error message for empty email input when it has not been set', async () => {
		useAccountBusinessSupportEmail.mockReturnValue( [ '', jest.fn() ] );

		const { container } = render( <SupportEmailInput /> );
		expect(
			container.querySelector( '.components-notice.is-error' )
		).toBeNull();
	} );

	it( 'calls setInputValid with false when email format is invalid', () => {
		const setInputValid = jest.fn();
		useAccountBusinessSupportEmail.mockReturnValue( [
			'test@test',
			jest.fn(),
		] );

		render( <SupportEmailInput setInputValid={ setInputValid } /> );

		expect( setInputValid ).toHaveBeenLastCalledWith( false );
	} );

	it( 'displays client-side validation error for invalid email after blur', () => {
		useAccountBusinessSupportEmail.mockReturnValue( [
			'test@test',
			jest.fn(),
		] );

		render( <SupportEmailInput /> );

		const errorRegion = screen.getByTestId( 'support-email-error' );

		// Error Notice should not be shown before blur
		expect(
			errorRegion.querySelector( '.components-notice.is-error' )
		).toBeNull();

		// Trigger blur event
		fireEvent.blur( screen.getByLabelText( 'Support email' ) );

		// Error Notice should be shown after blur
		expect(
			errorRegion.querySelector( '.components-notice.is-error' )
		).not.toBeNull();
	} );

	it( 'does not display client-side validation error for valid email after blur', () => {
		useAccountBusinessSupportEmail.mockReturnValue( [
			'valid@test.com',
			jest.fn(),
		] );

		const { container } = render( <SupportEmailInput /> );

		fireEvent.blur( screen.getByLabelText( 'Support email' ) );

		expect(
			container.querySelector( '.components-notice.is-error' )
		).toBeNull();
	} );

	it( 'calls setInputValid with true when email format is valid', () => {
		const setInputValid = jest.fn();
		useAccountBusinessSupportEmail.mockReturnValue( [
			'test@test.com',
			jest.fn(),
		] );

		render( <SupportEmailInput setInputValid={ setInputValid } /> );

		expect( setInputValid ).toHaveBeenLastCalledWith( true );
	} );

	it( 'displays the error message for invalid email', async () => {
		useAccountBusinessSupportEmail.mockReturnValue( [
			'invalid.email',
			jest.fn(),
		] );
		useGetSavingError.mockReturnValue( {
			code: 'rest_invalid_param',
			message: 'Invalid parameter(s): account_business_support_email',
			data: {
				status: 400,
				params: {
					account_business_support_email:
						'Error: Invalid email address: invalid.email',
				},
				details: {
					account_business_support_email: {
						code: 'rest_invalid_pattern',
						message: 'Error: Invalid email address: invalid.email',
						data: null,
					},
				},
			},
		} );

		const { container } = render( <SupportEmailInput /> );
		expect(
			container.querySelector( '.components-notice.is-error' ).textContent
		).toMatch( /Error: Invalid email address: / );
	} );
} );
