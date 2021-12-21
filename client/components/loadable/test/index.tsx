/** @format */
/**
 * External dependencies
 */
import * as React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import Loadable from '..';

describe( 'Loadable', () => {
	const ChildComponent = () => <div>Loaded content</div>;

	describe( 'when active', () => {
		test( 'renders custom placeholder', () => {
			const placeholder = 'Custom text';
			const { queryByText } = render(
				<Loadable isLoading={ true } placeholder={ placeholder }>
					<ChildComponent />
				</Loadable>
			);
			expect( queryByText( placeholder ) ).toBeInTheDocument();
		} );

		test( 'uses children as placeholder if not passed', () => {
			const { container } = render(
				<Loadable isLoading={ true }>
					<ChildComponent />
				</Loadable>
			);
			expect( container ).toMatchSnapshot();
		} );
	} );

	describe( 'when inactive', () => {
		test( 'render children', () => {
			const { container } = render(
				<Loadable isLoading={ false }>
					<ChildComponent />
				</Loadable>
			);
			expect( container ).toMatchSnapshot();
		} );

		test( 'renders simple value', () => {
			const value = 'Simple loadable value';
			const { queryByText } = render(
				<Loadable isLoading={ false } value={ value } />
			);
			expect( queryByText( value ) ).toBeInTheDocument();
		} );

		test( 'prioritizes rendering children over simple value', () => {
			const value = 'Simple loadable value';
			const { queryByText } = render(
				<Loadable isLoading={ false } value={ value }>
					<ChildComponent />
				</Loadable>
			);
			expect( queryByText( /loaded content/i ) ).toBeInTheDocument();
			expect( queryByText( value ) ).not.toBeInTheDocument();
		} );

		test( 'renders nothing when neither children nor value passed', () => {
			const { container, queryByText } = render(
				<Loadable isLoading={ false } />
			);
			expect( queryByText( /loaded content/i ) ).not.toBeInTheDocument();
			expect( container.innerHTML ).toBe( '' );
		} );
	} );
} );
