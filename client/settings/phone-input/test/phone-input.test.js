/**
 * External dependencies
 */
import React from 'react';
import {
	render,
	screen,
	fireEvent,
	queryByAttribute,
} from '@testing-library/react';

/**
 * Internal dependencies
 */
import PhoneInput from '../';

describe( 'PhoneInput', () => {
	const handlePhoneNumberChangeMock = jest.fn();
	const handlePhoneValidationChangeMock = jest.fn();
	const phoneInputId = 'phone-input-id';

	beforeEach( () => {
		window.wcpaySettings = {
			accountStatus: {
				country: 'US',
			},
		};
	} );

	it( 'should render phone number input', () => {
		const { container } = render(
			<PhoneInput
				onValueChange={ handlePhoneNumberChangeMock }
				onValidationChange={ handlePhoneValidationChangeMock }
				id={ phoneInputId }
				value="123"
			/>
		);
		expect(
			queryByAttribute( 'id', container, phoneInputId )
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
		expect( screen.queryByText( '+1' ) ).toBeInTheDocument();
	} );

	it( 'should call the onValueChange with phone number including country code', () => {
		const { container } = render(
			<PhoneInput
				onValueChange={ handlePhoneNumberChangeMock }
				onValidationChange={ handlePhoneValidationChangeMock }
				id={ phoneInputId }
				value="123"
			/>
		);

		expect( handlePhoneNumberChangeMock ).not.toHaveBeenCalled();

		const input = queryByAttribute( 'id', container, phoneInputId );
		fireEvent.change( input, { target: { value: '201' } } );

		expect( handlePhoneNumberChangeMock ).toHaveBeenCalledWith( '+1201' );
	} );

	it( 'should call the onValidationChange with true if value is valid', () => {
		const { container } = render(
			<PhoneInput
				onValueChange={ handlePhoneNumberChangeMock }
				onValidationChange={ handlePhoneValidationChangeMock }
				id={ phoneInputId }
				value="123"
			/>
		);

		const input = queryByAttribute( 'id', container, phoneInputId );

		expect( handlePhoneValidationChangeMock ).toHaveBeenLastCalledWith(
			false
		);

		fireEvent.change( input, { target: { value: '2345678901' } } );

		expect( handlePhoneValidationChangeMock ).toHaveBeenLastCalledWith(
			true
		);
	} );
} );
