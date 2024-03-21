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
			const {
				data,
				setData,
				errors,
				setErrors,
				touched,
				setTouched,
			} = useOnboardingContext();
			const handleClick = () => {
				setData( {
					url: 'URL',
				} );
				setErrors( {
					url: 'Required',
				} );
				setTouched( {
					url: true,
				} );
			};
			return (
				<>
					<div>data: { JSON.stringify( data ) }</div>
					<div>errors: { JSON.stringify( errors ) }</div>
					<div>touched: { JSON.stringify( touched ) }</div>
					<button onClick={ handleClick }>Update Data</button>;
				</>
			);
		};

		const initialData = { country: 'US' };

		render(
			<OnboardingContextProvider initialData={ initialData }>
				<TestComponent />
			</OnboardingContextProvider>
		);

		expect(
			screen.getByText( 'data: {"country":"US"}' )
		).toBeInTheDocument();
		expect( screen.getByText( 'errors: {}' ) ).toBeInTheDocument();
		expect( screen.getByText( 'touched: {}' ) ).toBeInTheDocument();

		user.click( screen.getByText( 'Update Data' ) );

		expect(
			screen.getByText( 'data: {"country":"US"}' )
		).toBeInTheDocument();
		expect(
			screen.getByText( 'errors: {"country":"Required"}' )
		).toBeInTheDocument();
		expect(
			screen.getByText( 'touched: {"country":true}' )
		).toBeInTheDocument();
	} );

	it( 'removes nil values on setErrors', () => {
		const TestComponent: React.FC = () => {
			const { errors, setErrors } = useOnboardingContext();
			const handleClick = () => {
				setErrors( {
					firstName: 'Required',
					lastName: undefined,
				} );
			};
			return (
				<>
					<div>errors: { JSON.stringify( errors ) }</div>
					<button onClick={ handleClick }>Update Data</button>;
				</>
			);
		};

		render(
			<OnboardingContextProvider>
				<TestComponent />
			</OnboardingContextProvider>
		);

		expect( screen.getByText( 'errors: {}' ) ).toBeInTheDocument();

		user.click( screen.getByText( 'Update Data' ) );

		expect(
			screen.getByText( 'errors: {"firstName":"Required"}' )
		).toBeInTheDocument();
	} );
} );
