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

	it( 'should render phone number input', async () => {
		render(
			<PhoneNumberInput
				onValueChange={ handlePhoneNumberChangeMock }
				onValidationChange={ handlePhoneValidationChangeMock }
				value="123"
			/>
		);

		// The `intl-tel-input` utils are loaded on demand, so the country
		// dropdown is enhanced asynchronously after the input mounts.
		expect(
			await screen.findByRole( 'combobox', {
				name: 'United States: +1',
			} )
		).toBeInTheDocument();
		expect(
			screen.queryByLabelText( 'Mobile phone number' )
		).toBeInTheDocument();
	} );

	it( 'should render the default selected country with code', async () => {
		render(
			<PhoneNumberInput
				onValueChange={ handlePhoneNumberChangeMock }
				onValidationChange={ handlePhoneValidationChangeMock }
				value="123"
			/>
		);
		expect(
			await screen.findByRole( 'combobox', {
				name: 'United States: +1',
			} )
		).toBeInTheDocument();
	} );

	it( 'should call the onValueChange with phone number including country code', async () => {
		render(
			<PhoneNumberInput
				onValueChange={ handlePhoneNumberChangeMock }
				onValidationChange={ handlePhoneValidationChangeMock }
				value="123"
			/>
		);

		await screen.findByRole( 'combobox', { name: 'United States: +1' } );

		expect( handlePhoneNumberChangeMock ).not.toHaveBeenCalled();

		const input = screen.queryByLabelText( 'Mobile phone number' ); // The label text for our input.
		fireEvent.change( input, { target: { value: '201' } } );

		expect( handlePhoneNumberChangeMock ).toHaveBeenCalledWith( '+1201' );
	} );

	it( 'should call the onValidationChange with true if value is valid', async () => {
		render(
			<PhoneNumberInput
				onValueChange={ handlePhoneNumberChangeMock }
				onValidationChange={ handlePhoneValidationChangeMock }
				value="123"
			/>
		);

		await screen.findByRole( 'combobox', { name: 'United States: +1' } );

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
