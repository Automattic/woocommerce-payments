/**
 * External dependencies
 */
import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import LazyPhoneNumberInput from '../lazy';

// Simulate the lazily-loaded chunk failing to resolve (e.g. a 404 on the asset).
jest.mock( '..', () => {
	throw new Error( 'Simulated chunk load failure' );
} );

describe( 'LazyPhoneNumberInput chunk-load fallback', () => {
	it( 'falls back to a plain phone input when the chunk fails to load', async () => {
		const onValueChange = jest.fn();
		const onValidationChange = jest.fn();

		render(
			<LazyPhoneNumberInput
				id="phone"
				value=""
				onValueChange={ onValueChange }
				onValidationChange={ onValidationChange }
				inputProps={ { ariaLabel: 'Mobile phone number' } }
				isBlocksCheckout
			/>
		);

		const input = await screen.findByLabelText( 'Mobile phone number' );

		expect( input ).toBeInTheDocument();

		fireEvent.change( input, { target: { value: '+12345678901' } } );

		expect( onValueChange ).toHaveBeenCalledWith( '+12345678901' );
		expect( onValidationChange ).toHaveBeenLastCalledWith( true );

		// React logs the boundary-caught chunk failure to the console.
		expect( console ).toHaveErrored();
	} );

	it( 'reports an invalid number when too few digits are entered', async () => {
		const onValidationChange = jest.fn();

		render(
			<LazyPhoneNumberInput
				id="phone"
				value=""
				onValueChange={ jest.fn() }
				onValidationChange={ onValidationChange }
				inputProps={ { ariaLabel: 'Mobile phone number' } }
			/>
		);

		const input = await screen.findByLabelText( 'Mobile phone number' );
		fireEvent.change( input, { target: { value: '123' } } );

		expect( onValidationChange ).toHaveBeenLastCalledWith( false );

		// React logs the boundary-caught chunk failure to the console.
		expect( console ).toHaveErrored();
	} );
} );
