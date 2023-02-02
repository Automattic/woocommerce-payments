/**
 * External dependencies
 */
import { fireEvent, render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import SupportEmailInput from '..';
import { useGetSavingError, useAccountBusinessSupportEmail } from 'wcpay/data';

jest.mock( 'wcpay/data', () => ( {
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
		).toEqual( 'Error: Invalid email address: invalid.email' );
	} );
} );
