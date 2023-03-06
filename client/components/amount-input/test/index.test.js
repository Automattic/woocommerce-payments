/**
 * External dependencies
 */
import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import AmountInput from '..';

describe( 'Amount input', () => {
	let mockValue;
	const mockOnChangeEvent = jest.fn();

	beforeEach( () => {
		jest.clearAllMocks();
		mockOnChangeEvent.mockImplementation( ( value ) => {
			mockValue = value;
		} );
		mockValue = null;
	} );
	test( 'renders correctly', () => {
		const { container } = render(
			<AmountInput
				id={ 'test_id' }
				prefix={ 'test_prefix' }
				value={ jest.mockValue }
				placeholder={ 'test_placeholder' }
				help="this is a helptext"
				onChange={ jest.fn() }
			/>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'sets the id of the input', () => {
		render( <AmountInput id="test_id" /> );
		const testInput = screen.queryByTestId( 'amount-input' );
		expect( testInput ).toBeInTheDocument();
		expect( testInput.id ).toBe( 'test_id' );
	} );
	test( 'sets the placeholder of the input', () => {
		render( <AmountInput placeholder="test_id" /> );
		const testInput = screen.queryByTestId( 'amount-input' );
		expect( testInput ).toBeInTheDocument();
		expect( testInput.placeholder ).toBe( 'test_id' );
	} );
	test( 'sets the value of the input', () => {
		render( <AmountInput value="1234" /> );
		const testInput = screen.queryByTestId( 'amount-input' );
		expect( testInput ).toBeInTheDocument();
		expect( testInput ).toHaveValue( '1234' );
	} );
	test( 'sets a float value to the input', () => {
		render(
			<AmountInput value={ mockValue } onChange={ mockOnChangeEvent } />
		);
		const testInput = screen.queryByTestId( 'amount-input' );
		const testContent = '123.45';
		// Simulate key by key typing
		for ( const i in testContent ) {
			user.type( testInput, ( mockValue ?? '' ) + testContent[ i ] );
		}
		expect( mockValue ).toBe( '123.45' );
	} );
	test( 'doesn`t set a non-float value to the input', async () => {
		render(
			<AmountInput value={ mockValue } onChange={ mockOnChangeEvent } />
		);
		const testInput = screen.queryByTestId( 'amount-input' );
		const testContent = 'a.123.123.323.v+';
		// Simulate key by key typing
		for ( const i in testContent ) {
			await user.type(
				testInput,
				( mockValue ?? '' ) + testContent[ i ]
			);
		}
		expect( mockValue ).toBe( '123.123323' );
	} );
	test( 'can set empty value on input', async () => {
		render(
			<AmountInput value={ mockValue } onChange={ mockOnChangeEvent } />
		);
		const testInput = screen.getByTestId( 'amount-input' );
		fireEvent.change( testInput, { target: { value: '1' } } );
		expect( mockValue ).toBe( '1' );
		fireEvent.change( testInput, { target: { value: '' } } );
		expect( mockValue ).toBe( '' );
	} );
} );
