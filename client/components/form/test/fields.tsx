/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { TextField, SelectField, PhoneNumberField } from '../fields';

describe( 'Form fields components', () => {
	it( 'renders TextField component with provided props', () => {
		render(
			<TextField
				label="Test Label"
				value="Test Value"
				onChange={ jest.fn() }
			/>
		);
		expect( screen.getByLabelText( 'Test Label' ) ).toHaveValue(
			'Test Value'
		);
	} );

	it( 'renders SelectField component with provided props', () => {
		const options = [
			{ key: 'Option 1', value: 'option-1' },
			{ key: 'Option 2', value: 'option-2' },
		];
		render(
			<SelectField
				label="Test Label"
				options={ options }
				onChange={ jest.fn() }
			/>
		);
		expect( screen.getByText( 'Test Label' ) ).toBeInTheDocument();
	} );

	it( 'renders PhoneNumberField component with provided props', () => {
		render(
			<PhoneNumberField
				label="Test Label"
				value="123"
				onChange={ jest.fn() }
			/>
		);
		expect( screen.getByText( 'Test Label' ) ).toBeInTheDocument();
	} );

	it( 'renders TextField component with error', () => {
		render(
			<TextField
				label="Test Label"
				value="Test Value"
				error="Test Error"
				onChange={ jest.fn() }
			/>
		);
		expect( screen.getByText( 'Test Error' ) ).toBeInTheDocument();
	} );
} );
