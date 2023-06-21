/**
 * External dependencies
 */
import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import AmountInput from '..';

describe( 'Amount input', () => {
	let mockValue: string;
	const mockOnChangeEvent = jest.fn();

	beforeEach( () => {
		jest.clearAllMocks();
		mockOnChangeEvent.mockImplementation( ( value ) => {
			mockValue = value;
		} );
		mockValue = '';
	} );

	const writeKeyByKey = ( input: HTMLInputElement, text: string ) => {
		const characters = text.split( '' );

		characters.forEach( ( character: string ) => {
			fireEvent.change( input, {
				target: { value: ( mockValue ? mockValue : '' ) + character },
			} );
		} );
	};

	test( 'renders correctly', () => {
		const { container } = render(
			<AmountInput
				id={ 'test_id' }
				prefix={ 'test_prefix' }
				value={ mockValue }
				placeholder={ 'test_placeholder' }
				help="this is a helptext"
				onChange={ jest.fn() }
			/>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'sets the id of the input', async () => {
		render( <AmountInput value={ mockValue } id="test_id" /> );
		const testInput = await screen.findByTestId( 'amount-input' );
		expect( testInput ).toBeInTheDocument();
		expect( testInput.id ).toBe( 'test_id' );
	} );
	test( 'sets the placeholder of the input', async () => {
		render( <AmountInput value={ mockValue } placeholder="test_id" /> );
		const testInput = ( await screen.findByTestId(
			'amount-input'
		) ) as HTMLInputElement;
		expect( testInput ).toBeInTheDocument();
		expect( testInput.placeholder ).toBe( 'test_id' );
	} );
	test( 'sets the value of the input', async () => {
		render( <AmountInput value="1234" /> );
		const testInput = await screen.findByTestId( 'amount-input' );
		expect( testInput ).toBeInTheDocument();
		expect( testInput ).toHaveValue( '1234' );
	} );
	test( 'sets a float value to the input', async () => {
		render(
			<AmountInput value={ mockValue } onChange={ mockOnChangeEvent } />
		);
		const testInput = ( await screen.findByTestId(
			'amount-input'
		) ) as HTMLInputElement;
		const testContent = '123.45';
		writeKeyByKey( testInput, testContent );
		expect( mockValue ).toBe( '123.45' );
	} );
	test( 'doesn`t set a non-float value to the input', async () => {
		render(
			<AmountInput value={ mockValue } onChange={ mockOnChangeEvent } />
		);
		const testInput = ( await screen.findByTestId(
			'amount-input'
		) ) as HTMLInputElement;
		const testContent = 'a.123.123.323.v+';
		writeKeyByKey( testInput, testContent );
		expect( mockValue ).toBe( '123.123323' );
	} );
} );
