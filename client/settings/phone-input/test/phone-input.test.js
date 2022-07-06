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

	it( 'should render phone number input', () => {
		render(
			<PhoneNumberInput onValueChange={ handlePhoneNumberChangeMock } />
		);
		expect(
			screen.queryByLabelText( 'Mobile phone number' )
		).toBeInTheDocument();
	} );

	it( 'should render the default selected country with code', () => {
		render(
			<PhoneNumberInput onValueChange={ handlePhoneNumberChangeMock } />
		);
		expect(
			screen.queryByRole( 'combobox', { name: 'United States: +1' } )
		).toBeInTheDocument();
	} );

	it( 'should call the handlePhoneNumberChange with phone number including country code', () => {
		render(
			<PhoneNumberInput onValueChange={ handlePhoneNumberChangeMock } />
		);

		expect( handlePhoneNumberChangeMock ).not.toHaveBeenCalled();

		const input = screen.queryByLabelText( 'Mobile phone number' ); // The label text for our input.
		fireEvent.change( input, { target: { value: '201' } } );

		expect( handlePhoneNumberChangeMock ).toHaveBeenCalledWith( '+1201' );
	} );
} );
