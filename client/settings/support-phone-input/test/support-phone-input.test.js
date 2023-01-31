/**
 * External dependencies
 */
import { fireEvent, render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import SupportPhoneInput from '..';
import { useGetSavingError, useAccountBusinessSupportPhone } from 'wcpay/data';

jest.mock( 'wcpay/data', () => ( {
	useAccountBusinessSupportPhone: jest.fn(),
	useGetSavingError: jest.fn(),
} ) );

describe( 'SupportPhoneInput', () => {
	beforeEach( () => {
		useAccountBusinessSupportPhone.mockReturnValue( [
			'+12345678901',
			jest.fn(),
		] );
		useGetSavingError.mockReturnValue( null );
	} );

	it( 'updates phone input', async () => {
		const setSupportPhone = jest.fn();
		useAccountBusinessSupportPhone.mockReturnValue( [
			'+12345678901',
			setSupportPhone,
		] );
		render( <SupportPhoneInput /> );

		const newPhone = '+12377778888';
		fireEvent.change( screen.getByLabelText( 'Support phone number' ), {
			target: { value: newPhone },
		} );

		expect( setSupportPhone ).toHaveBeenCalledWith( newPhone );
	} );

	it( 'displays the error message for empty phone input', async () => {
		useAccountBusinessSupportPhone.mockReturnValue( [ '', jest.fn() ] );

		const { container } = render( <SupportPhoneInput /> );
		expect(
			container.querySelector( '.components-notice.is-error' )
		).not.toBeNull();
	} );

	it( 'displays the error message for invalid phone', async () => {
		useAccountBusinessSupportPhone.mockReturnValue( [
			'+12345', // invalid phone number.
			jest.fn(),
		] );

		const { container } = render( <SupportPhoneInput /> );
		expect(
			container.querySelector( '.components-notice.is-error' ).textContent
		).toEqual( 'Please enter a valid phone number.' );
	} );
} );
