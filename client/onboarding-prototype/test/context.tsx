/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import { OnboardingContextProvider, useOnboardingContext } from '../context';

describe( 'OnboardingContext', () => {
	it( 'sets initial values and updates correctly', () => {
		const TestComponent: React.FC = () => {
			const { data, setData } = useOnboardingContext();
			const handleClick = () =>
				setData( {
					firstName: 'First',
				} );
			return (
				<>
					<div>{ JSON.stringify( data ) }</div>
					<button onClick={ handleClick }>Update Data</button>;
				</>
			);
		};

		render(
			<OnboardingContextProvider>
				<TestComponent />
			</OnboardingContextProvider>
		);

		expect( screen.getByText( '{}' ) ).toBeInTheDocument();

		user.click( screen.getByText( 'Update Data' ) );

		expect(
			screen.getByText( '{"firstName":"First"}' )
		).toBeInTheDocument();
	} );
} );
