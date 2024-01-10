/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import OnboardingCard from '..';

describe( 'OnboardingCard Component', () => {
	it( 'renders OnboardingCard component with provided props', () => {
		const { container } = render(
			<OnboardingCard
				icon={ <svg /> }
				heading="Card title"
				actionLabel="Continue"
				content={ <p>Card content</p> }
				onClick={ jest.fn() }
			/>
		);
		expect( container ).toMatchSnapshot();
	} );

	it( 'calls the onChange function when the action button is clicked.', () => {
		const mockOnChange = jest.fn();
		render(
			<OnboardingCard
				icon={ <svg /> }
				heading="Card title"
				actionLabel="Continue"
				content={ <p>Card content</p> }
				onClick={ mockOnChange }
			/>
		);

		user.click( screen.getByRole( 'button' ) );
		expect( mockOnChange ).toHaveBeenCalled();
	} );
} );
