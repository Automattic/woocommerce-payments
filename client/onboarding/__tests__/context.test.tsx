/**
 * External dependencies
 */
import React, { useState } from 'react';
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import { OnboardingContextProvider } from '../context-provider';
import { useOnboardingContext } from '../use-onboarding-context';
import { OnboardingContextValue } from '../types';

describe( 'OnboardingContext', () => {
	it( 'sets initial values and updates correctly', async () => {
		const TestComponent: React.FC = () => {
			const { data, setData, errors, setErrors, touched, setTouched } =
				useOnboardingContext();
			const handleClick = () => {
				setData( {
					business_type: 'Individual',
				} );
				setErrors( {
					business_type: 'Required',
				} );
				setTouched( {
					business_type: true,
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

		const initialData = { business_type: 'Individual' };

		render(
			<OnboardingContextProvider initialData={ initialData }>
				<TestComponent />
			</OnboardingContextProvider>
		);

		expect(
			screen.getByText( 'data: {"business_type":"Individual"}' )
		).toBeInTheDocument();
		expect( screen.getByText( 'errors: {}' ) ).toBeInTheDocument();
		expect( screen.getByText( 'touched: {}' ) ).toBeInTheDocument();

		await user.click( screen.getByText( 'Update Data' ) );

		expect(
			screen.getByText( 'data: {"business_type":"Individual"}' )
		).toBeInTheDocument();
		expect(
			screen.getByText( 'errors: {"business_type":"Required"}' )
		).toBeInTheDocument();
		expect(
			screen.getByText( 'touched: {"business_type":true}' )
		).toBeInTheDocument();
	} );

	it( 'removes nil values on setErrors', async () => {
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

		await user.click( screen.getByText( 'Update Data' ) );

		expect(
			screen.getByText( 'errors: {"firstName":"Required"}' )
		).toBeInTheDocument();
	} );

	it( 'returns the same context value when a parent re-renders', async () => {
		const contexts: OnboardingContextValue[] = [];

		const TestComponent: React.FC = () => {
			contexts.push( useOnboardingContext() );
			return null;
		};

		const Parent: React.FC = () => {
			const [ count, setCount ] = useState( 0 );
			return (
				<>
					<button onClick={ () => setCount( ( prev ) => prev + 1 ) }>
						count: { count }
					</button>
					<OnboardingContextProvider>
						<TestComponent />
					</OnboardingContextProvider>
				</>
			);
		};

		render( <Parent /> );

		await user.click( screen.getByText( 'count: 0' ) );

		expect( screen.getByText( 'count: 1' ) ).toBeInTheDocument();
		expect( contexts.length ).toBeGreaterThan( 1 );

		contexts.forEach( ( context ) =>
			expect( context ).toBe( contexts[ 0 ] )
		);
	} );

	it( 'returns the same setters when onboarding state changes', async () => {
		const contexts: OnboardingContextValue[] = [];

		const TestComponent: React.FC = () => {
			const context = useOnboardingContext();
			contexts.push( context );
			return (
				<button
					onClick={ () =>
						context.setData( { business_type: 'Individual' } )
					}
				>
					data: { JSON.stringify( context.data ) }
				</button>
			);
		};

		render(
			<OnboardingContextProvider>
				<TestComponent />
			</OnboardingContextProvider>
		);

		await user.click( screen.getByText( 'data: {}' ) );

		expect(
			screen.getByText( 'data: {"business_type":"Individual"}' )
		).toBeInTheDocument();

		contexts.forEach( ( context ) => {
			expect( context.setData ).toBe( contexts[ 0 ].setData );
			expect( context.setErrors ).toBe( contexts[ 0 ].setErrors );
			expect( context.setTouched ).toBe( contexts[ 0 ].setTouched );
		} );
	} );
} );
