/**
 * External dependencies
 */
import { fireEvent, render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import SupportPhoneInput from '..';
import {
	useGetSavingError,
	useAccountBusinessSupportPhone,
	useTestModeOnboarding,
} from 'wcpay/data';

jest.mock( 'wcpay/data', () => ( {
	useAccountBusinessSupportPhone: jest.fn(),
	useGetSavingError: jest.fn(),
	useTestModeOnboarding: jest.fn(),
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

	it( 'error message for empty phone input when it has not been set', async () => {
		useAccountBusinessSupportPhone.mockReturnValue( [ '', jest.fn() ] );

		const { container } = render( <SupportPhoneInput /> );

		expect(
			container.querySelector( '.components-notice.is-error' ).textContent
		).toEqual( 'Support phone number cannot be empty.' );
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

	it( 'Singapore phone number validation special cases - starting with 800, 805, 806, 807, 808 or 809', async () => {
		useAccountBusinessSupportPhone.mockReturnValue( [
			'+6580600000', // test phone number.
			jest.fn(),
		] );
		useTestModeOnboarding.mockReturnValue( true );

		const { container } = render( <SupportPhoneInput /> );
		expect(
			container.querySelector( '.components-notice.is-error' )
		).toBeNull();

		fireEvent.change(
			screen.getByLabelText(
				'Support phone number (+1 0000000000 can be used in sandbox mode)'
			),
			{
				target: { value: '+6580000000' },
			}
		);
		expect(
			container.querySelector( '.components-notice.is-error' )
		).toBeNull();
		fireEvent.change(
			screen.getByLabelText(
				'Support phone number (+1 0000000000 can be used in sandbox mode)'
			),
			{
				target: { value: '+6580500000' },
			}
		);
		expect(
			container.querySelector( '.components-notice.is-error' )
		).toBeNull();
		fireEvent.change(
			screen.getByLabelText(
				'Support phone number (+1 0000000000 can be used in sandbox mode)'
			),
			{
				target: { value: '+6580700000' },
			}
		);
		expect(
			container.querySelector( '.components-notice.is-error' )
		).toBeNull();
		fireEvent.change(
			screen.getByLabelText(
				'Support phone number (+1 0000000000 can be used in sandbox mode)'
			),
			{
				target: { value: '+6580800000' },
			}
		);
		expect(
			container.querySelector( '.components-notice.is-error' )
		).toBeNull();
		fireEvent.change(
			screen.getByLabelText(
				'Support phone number (+1 0000000000 can be used in sandbox mode)'
			),
			{
				target: { value: '+6580900000' },
			}
		);
		expect(
			container.querySelector( '.components-notice.is-error' )
		).toBeNull();
	} );

	it( 'Hong Kong phone number validation special cases - starting with 4, 7, 8', async () => {
		useAccountBusinessSupportPhone.mockReturnValue( [
			'+85221234567', // test phone number.
			jest.fn(),
		] );
		useTestModeOnboarding.mockReturnValue( true );

		const { container } = render( <SupportPhoneInput /> );
		expect(
			container.querySelector( '.components-notice.is-error' )
		).toBeNull();

		fireEvent.change(
			screen.getByLabelText(
				'Support phone number (+1 0000000000 can be used in sandbox mode)'
			),
			{
				target: { value: '+85241234567' },
			}
		);
		expect(
			container.querySelector( '.components-notice.is-error' )
		).toBeNull();

		fireEvent.change(
			screen.getByLabelText(
				'Support phone number (+1 0000000000 can be used in sandbox mode)'
			),
			{
				target: { value: '+85271234567' },
			}
		);
		expect(
			container.querySelector( '.components-notice.is-error' )
		).toBeNull();

		fireEvent.change(
			screen.getByLabelText(
				'Support phone number (+1 0000000000 can be used in sandbox mode)'
			),
			{
				target: { value: '+85281234567' },
			}
		);
		expect(
			container.querySelector( '.components-notice.is-error' )
		).toBeNull();
	} );

	it( 'in sandbox mode, allow all 0s number', async () => {
		useAccountBusinessSupportPhone.mockReturnValue( [
			'+10000000000', // test phone number.
			jest.fn(),
		] );
		useTestModeOnboarding.mockReturnValue( true );

		const { container } = render( <SupportPhoneInput /> );
		expect(
			container.querySelector( '.components-notice.is-error' )
		).toBeNull();
	} );
} );
