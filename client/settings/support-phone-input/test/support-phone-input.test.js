/**
 * External dependencies
 */
import { fireEvent, render, queryByAttribute } from '@testing-library/react';

/**
 * Internal dependencies
 */
import SupportPhoneInput from '..';
import {
	useGetSavingError,
	useAccountBusinessSupportPhone,
	useDevMode,
} from 'wcpay/data';

jest.mock( 'wcpay/data', () => ( {
	useAccountBusinessSupportPhone: jest.fn(),
	useGetSavingError: jest.fn(),
	useDevMode: jest.fn(),
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
		const { container } = render( <SupportPhoneInput /> );

		const expectedNewPhone = '+12377778888';
		const newPhone = expectedNewPhone.replace( '+1', '' );
		fireEvent.change(
			queryByAttribute(
				'id',
				container,
				'account-business-support-phone-input'
			),
			{
				target: { value: newPhone },
			}
		);

		expect( setSupportPhone ).toHaveBeenCalledWith( expectedNewPhone );
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

		const phoneInput = queryByAttribute(
			'id',
			container,
			'account-business-support-phone-input'
		);
		fireEvent.blur( phoneInput );
		fireEvent.change( phoneInput, {
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

	it( 'in sandbox mode, allow all 0s number', async () => {
		useAccountBusinessSupportPhone.mockReturnValue( [
			'+10000000000', // test phone number.
			jest.fn(),
		] );
		useDevMode.mockReturnValue( true );

		const { container } = render( <SupportPhoneInput /> );
		expect(
			container.querySelector( '.components-notice.is-error' )
		).toBeNull();
	} );
} );
