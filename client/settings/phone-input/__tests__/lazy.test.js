/**
 * External dependencies
 */
import React, { useState } from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

/**
 * Internal dependencies
 */
import LazyPhoneNumberInput from '../lazy';

// Simulate the lazily-loaded chunk failing to resolve (e.g. a 404 on the asset).
jest.mock( '..', () => {
	throw new Error( 'Simulated chunk load failure' );
} );

// The loading placeholder shares the input's accessible name, so wait for the
// enabled fallback field (the placeholder is disabled) rather than the first match.
const findFallbackInput = async () => {
	await waitFor( () =>
		expect( screen.getByLabelText( 'Mobile phone number' ) ).toBeEnabled()
	);

	return screen.getByLabelText( 'Mobile phone number' );
};

describe( 'LazyPhoneNumberInput chunk-load fallback', () => {
	it( 'validates a prefilled value so checkout is not left blocked', async () => {
		const onValidationChange = jest.fn();

		render(
			<LazyPhoneNumberInput
				id="phone"
				value="+12345678901"
				onValueChange={ jest.fn() }
				onValidationChange={ onValidationChange }
				inputProps={ { ariaLabel: 'Mobile phone number' } }
				isBlocksCheckout
			/>
		);

		const input = await findFallbackInput();

		expect( input ).toBeInTheDocument();
		expect( onValidationChange ).toHaveBeenLastCalledWith( true );

		// React logs the boundary-caught chunk failure to the console.
		expect( console ).toHaveErrored();
	} );

	it( 'lets the shopper type, stays controlled, and forwards passthrough props', async () => {
		const onValidationChange = jest.fn();
		const onClick = jest.fn();

		const Harness = () => {
			const [ value, setValue ] = useState( '' );
			return (
				<LazyPhoneNumberInput
					id="phone"
					value={ value }
					onValueChange={ setValue }
					onValidationChange={ onValidationChange }
					onClick={ onClick }
					inputProps={ { ariaLabel: 'Mobile phone number' } }
					isBlocksCheckout
				/>
			);
		};

		render( <Harness /> );

		const input = await findFallbackInput();

		fireEvent.click( input );

		expect( onClick ).toHaveBeenCalled();

		fireEvent.change( input, { target: { value: '+12345678901' } } );

		expect( input ).toHaveValue( '+12345678901' );
		expect( onValidationChange ).toHaveBeenLastCalledWith( true );

		expect( console ).toHaveErrored();
	} );

	it( 'reports an invalid number when too few digits are present', async () => {
		const onValidationChange = jest.fn();

		render(
			<LazyPhoneNumberInput
				id="phone"
				value="123"
				onValueChange={ jest.fn() }
				onValidationChange={ onValidationChange }
				inputProps={ { ariaLabel: 'Mobile phone number' } }
			/>
		);

		await findFallbackInput();

		expect( onValidationChange ).toHaveBeenLastCalledWith( false );

		// React logs the boundary-caught chunk failure to the console.
		expect( console ).toHaveErrored();
	} );
} );
