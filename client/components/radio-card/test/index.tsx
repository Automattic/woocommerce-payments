/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import RadioCard from '../';

const options = [
	{
		label: 'Pineapple pizza',
		value: 'pineapple',
		icon: <svg />,
		content: <p>Sweet pizza</p>,
	},
	{
		label: 'Pizza',
		value: 'pizza',
		icon: <svg />,
		content: <p>The real pizza</p>,
	},
];

describe( 'RadioCard Component', () => {
	it( 'renders RadioCard component with provided props', () => {
		const { container } = render(
			<RadioCard
				name="pizzas"
				selected="pineapple"
				onChange={ jest.fn() }
				options={ options }
			/>
		);
		expect( container ).toMatchSnapshot();
	} );

	it( 'changes the selected value when an option is clicked', () => {
		const mockOnChange = jest.fn();
		render(
			<RadioCard
				name="pizzas"
				selected="pizza"
				onChange={ mockOnChange }
				options={ options }
			/>
		);

		user.click( screen.getByLabelText( /Pineapple/i ) );
		expect( mockOnChange ).toHaveBeenCalledWith( 'pineapple' );
	} );
} );
