/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PhoneNumberInput from '../phone-number-input';

describe( 'PhoneNumberInput', () => {
	const handlePhoneNumberChangeMock = jest.fn();

	it( 'should render phone number input', () => {
		render(
			<PhoneNumberInput
				handlePhoneNumberChange={ handlePhoneNumberChangeMock }
			/>
		);
		expect(
			screen.queryByText( 'Mobile phone number' )
		).toBeInTheDocument();
	} );

	it( 'should render the default selected country with code', () => {
		render(
			<PhoneNumberInput
				handlePhoneNumberChange={ handlePhoneNumberChangeMock }
			/>
		);
		expect(
			screen.queryByRole( 'combobox', { name: 'United States: +1' } )
		).toBeInTheDocument();
	} );

	it( 'should call the handlePhoneNumberChange with phone number including country code', () => {
		render(
			<PhoneNumberInput
				handlePhoneNumberChange={ handlePhoneNumberChangeMock }
			/>
		);

		expect( handlePhoneNumberChangeMock ).not.toHaveBeenCalled();

		const input = screen.queryByPlaceholderText( '201-555-0123' ); // placeholder number for us
		fireEvent.change( input, { target: { value: '201' } } );

		expect( handlePhoneNumberChangeMock ).toHaveBeenCalledWith( '+1201' );
	} );
} );
