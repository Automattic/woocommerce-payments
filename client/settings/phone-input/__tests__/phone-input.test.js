/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PhoneNumberInput from '../';

describe( 'PhoneNumberInput', () => {
	const handlePhoneNumberChangeMock = jest.fn();
	const handlePhoneValidationChangeMock = jest.fn();

	beforeEach( () => {
		window.wcpaySettings = {
			accountStatus: {
				country: 'US',
			},
		};
	} );

	it( 'should render phone number input', () => {
		render(
			<PhoneNumberInput
				onValueChange={ handlePhoneNumberChangeMock }
				onValidationChange={ handlePhoneValidationChangeMock }
				value="123"
			/>
		);
		expect(
			screen.queryByLabelText( 'Mobile phone number' )
		).toBeInTheDocument();
	} );

	it( 'should render the default selected country with code', () => {
		render(
			<PhoneNumberInput
				onValueChange={ handlePhoneNumberChangeMock }
				onValidationChange={ handlePhoneValidationChangeMock }
				value="123"
			/>
		);
		expect(
			screen.queryByRole( 'combobox', { name: 'United States: +1' } )
		).toBeInTheDocument();
	} );

	it( 'should call the onValueChange with phone number including country code', () => {
		render(
			<PhoneNumberInput
				onValueChange={ handlePhoneNumberChangeMock }
				onValidationChange={ handlePhoneValidationChangeMock }
				value="123"
			/>
		);

		expect( handlePhoneNumberChangeMock ).not.toHaveBeenCalled();

		const input = screen.queryByLabelText( 'Mobile phone number' ); // The label text for our input.
		fireEvent.change( input, { target: { value: '201' } } );

		expect( handlePhoneNumberChangeMock ).toHaveBeenCalledWith( '+1201' );
	} );

	it( 'should call the onValidationChange with true if value is valid', () => {
		render(
			<PhoneNumberInput
				onValueChange={ handlePhoneNumberChangeMock }
				onValidationChange={ handlePhoneValidationChangeMock }
				value="123"
			/>
		);

		const input = screen.queryByLabelText( 'Mobile phone number' ); // The label text for our input.

		expect( handlePhoneValidationChangeMock ).toHaveBeenLastCalledWith(
			false
		);

		fireEvent.change( input, { target: { value: '2345678901' } } );

		expect( handlePhoneValidationChangeMock ).toHaveBeenLastCalledWith(
			true
		);
	} );

	it( 'should sync the dial code font size with the phone input', () => {
		const originalGetComputedStyle = window.getComputedStyle;
		const spy = jest
			.spyOn( window, 'getComputedStyle' )
			.mockImplementation( ( element, ...args ) => {
				if ( element instanceof HTMLInputElement ) {
					return { fontSize: '13px' };
				}
				return originalGetComputedStyle( element, ...args );
			} );

		const { container } = render(
			<PhoneNumberInput
				onValueChange={ handlePhoneNumberChangeMock }
				onValidationChange={ handlePhoneValidationChangeMock }
				value="123"
			/>
		);

		const dialCode = container.querySelector( '.iti__selected-dial-code' );
		expect( dialCode ).toBeInTheDocument();
		expect( dialCode.style.fontSize ).toBe( '13px' );

		spy.mockRestore();
	} );
} );
