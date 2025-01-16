/**
 * External dependencies
 */
import React, { useEffect } from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ErrorBoundary from '..';

const ComponentThatThrows = () => {
	useEffect( () => {
		throw new Error( 'Some error' );
	} );

	return null;
};

describe( 'ErrorBoundary', () => {
	const handleError = ( event ) => {
		event.preventDefault();
	};

	beforeAll( () => {
		// preventing the error from bubble up to `@wordpress/jest-console`
		window.addEventListener( 'error', handleError );
	} );

	afterAll( () => {
		window.removeEventListener( 'error', handleError );
	} );

	it( 'renders its children', () => {
		const onErrorMock = jest.fn();
		const fallbackMock = jest.fn().mockReturnValue( 'Fallback message' );

		render(
			<ErrorBoundary
				onError={ onErrorMock }
				fallbackRender={ fallbackMock }
			>
				<p>Children mock</p>
			</ErrorBoundary>
		);

		expect( screen.queryByText( 'Fallback message' ) ).toBeNull();
		expect( screen.getByText( 'Children mock' ) ).toBeInTheDocument();
		expect( onErrorMock ).not.toHaveBeenCalled();
		expect( fallbackMock ).not.toHaveBeenCalled();
	} );

	it( 'renders nothing on error, if no fallback is provided', () => {
		const onErrorMock = jest.fn();

		const {
			container: { firstChild },
		} = render(
			<ErrorBoundary onError={ onErrorMock }>
				<ComponentThatThrows />
				<p>Children mock</p>
			</ErrorBoundary>
		);

		expect( screen.queryByText( 'Children mock' ) ).toBeNull();
		expect( firstChild ).toBeNull();
		expect( onErrorMock ).toHaveBeenCalledWith(
			new Error( 'Some error' ),
			expect.objectContaining( { componentStack: expect.anything() } )
		);
	} );

	it( 'renders the fallback on error', () => {
		const fallbackMock = jest.fn().mockReturnValue( 'Fallback message' );

		render(
			<ErrorBoundary fallbackRender={ fallbackMock }>
				<ComponentThatThrows />
				<p>Children mock</p>
			</ErrorBoundary>
		);

		expect( screen.queryByText( 'Children mock' ) ).toBeNull();
		expect( screen.getByText( 'Fallback message' ) ).toBeInTheDocument();
		expect( fallbackMock ).toHaveBeenCalledWith(
			expect.objectContaining( { error: new Error( 'Some error' ) } ),
			expect.anything()
		);
	} );
} );
