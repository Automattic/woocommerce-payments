/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import PhoneNumberControl from '../';

describe( 'Phone Number Control', () => {
	const onChange = jest.fn();

	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'renders correctly', () => {
		render(
			<PhoneNumberControl
				value="123"
				onChange={ onChange }
				label="Phone number"
			/>
		);

		const labelElement = screen.getByText( 'Phone number' );
		const selectElement = screen.getByRole( 'combobox', {
			name: 'phone number country code',
		} );
		const spanElement = screen.getByText( '+1' );
		const inputElement = screen.getByRole( 'textbox', {
			name: 'Phone number',
		} );

		expect( labelElement ).toBeInTheDocument();
		expect( selectElement ).toHaveDisplayValue( 'US' );
		expect( spanElement ).toBeInTheDocument();
		expect( inputElement ).toHaveDisplayValue( '123' );
	} );

	it( 'defaults to provided country', () => {
		render(
			<PhoneNumberControl value="" country="ES" onChange={ onChange } />
		);

		const selectElement = screen.getByRole( 'combobox' );
		expect( selectElement ).toHaveDisplayValue( 'ES' );
	} );

	it( 'defaults to US country code when country is not set', () => {
		render( <PhoneNumberControl value="" onChange={ onChange } /> );

		const selectElement = screen.getByRole( 'combobox' );
		expect( selectElement ).toHaveDisplayValue( 'US' );
	} );

	it( 'calls onChange when input value changes', () => {
		render( <PhoneNumberControl value="" onChange={ onChange } /> );

		const input = screen.getByRole( 'textbox' );
		userEvent.type( input, '1234567890' );

		expect( onChange ).toHaveBeenCalledTimes( 10 );
		expect( onChange ).toHaveBeenCalledWith( '+11234567890', 'US' );
	} );

	it( 'calls onChange when country code select value changes', () => {
		render( <PhoneNumberControl value="" onChange={ onChange } /> );
		const select = screen.getByRole( 'combobox' );
		userEvent.selectOptions( select, 'ES' );
		expect( onChange ).toHaveBeenCalledTimes( 1 );
		expect( onChange ).toHaveBeenCalledWith( '+34', 'ES' );
	} );

	it( 'focus input on select change', () => {
		render( <PhoneNumberControl value="" onChange={ onChange } /> );

		const input = screen.getByRole( 'textbox' );
		const select = screen.getByRole( 'combobox' );
		userEvent.selectOptions( select, 'CA' );

		expect( input ).toHaveFocus();
	} );

	it( 'toggles focused class as expected', () => {
		render( <PhoneNumberControl value="" onChange={ onChange } /> );
		const input = screen.getByRole( 'textbox' );
		const control = input.parentElement;

		userEvent.click( input );
		fireEvent.focus( input ); // Workaround for onFocus event not firing with jsdom <16.3.0
		expect( input ).toHaveFocus();
		expect( control ).toHaveClass( 'focused' );

		userEvent.tab();
		fireEvent.focusOut( input ); // Workaround for onFocus event not firing with jsdom <16.3.0
		expect( input ).not.toHaveFocus();
		expect( control ).not.toHaveClass( 'focused' );
	} );
} );
