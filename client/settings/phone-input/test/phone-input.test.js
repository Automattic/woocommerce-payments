/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PhoneInput from '../';

describe( 'PhoneInput', () => {
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
			<PhoneInput
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
			<PhoneInput
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
			<PhoneInput
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
			<PhoneInput
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
} );
