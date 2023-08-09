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
		window.wcpaySettings = {
			accountStatus: {
				country: 'US',
			},
		};
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

	it( 'displays error message for empty phone input when it has been set', async () => {
		useAccountBusinessSupportPhone.mockReturnValue( [
			'+12345678901',
			jest.fn(),
		] );
		const { container } = render( <SupportPhoneInput /> );

		// In the first render, the phone number has been set correctly, so the error message is not displayed.
		expect(
			container.querySelector( '.components-notice.is-error' )
		).toBeNull();

		// Mock that the phone number input is set to empty.
		useAccountBusinessSupportPhone.mockReturnValue( [ '', jest.fn() ] );

		fireEvent.change( screen.getByLabelText( 'Support phone number' ), {
			target: { value: '' },
		} );

		// The error message is displayed.
		expect(
			container.querySelector( '.components-notice.is-error' ).textContent
		).toEqual(
			'Support phone number cannot be empty once it has been set before, please specify.'
		);
	} );

	it( 'no error message for empty phone input when it has not been set', async () => {
		useAccountBusinessSupportPhone.mockReturnValue( [ '', jest.fn() ] );

		const { container } = render( <SupportPhoneInput /> );

		expect(
			container.querySelector( '.components-notice.is-error' )
		).toBeNull();
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
