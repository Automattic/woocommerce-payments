/** @format */
/**
 * External dependencies
 */
import React from 'react';
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

		test( 'renders children in hidden container when renderChildrenWhileLoading is true', () => {
			const placeholder = 'Loading...';
			const { queryByText, container } = render(
				<Loadable
					isLoading={ true }
					placeholder={ placeholder }
					renderChildrenWhileLoading={ true }
				>
					<ChildComponent />
				</Loadable>
			);
			// Placeholder should be visible
			expect( queryByText( placeholder ) ).toBeInTheDocument();
			// Children should also be rendered (in hidden container)
			expect( queryByText( /loaded content/i ) ).toBeInTheDocument();
			// Hidden container should exist
			expect(
				container.querySelector(
					'.is-loadable-placeholder__hidden-content'
				)
			).toBeInTheDocument();
		} );

		test( 'does not render children when renderChildrenWhileLoading is false', () => {
			const placeholder = 'Loading...';
			const { queryByText, container } = render(
				<Loadable
					isLoading={ true }
					placeholder={ placeholder }
					renderChildrenWhileLoading={ false }
				>
					<ChildComponent />
				</Loadable>
			);
			// Placeholder should be visible
			expect( queryByText( placeholder ) ).toBeInTheDocument();
			// Children should not be rendered
			expect( queryByText( /loaded content/i ) ).not.toBeInTheDocument();
			// Hidden container should not exist
			expect(
				container.querySelector(
					'.is-loadable-placeholder__hidden-content'
				)
			).not.toBeInTheDocument();
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

		test( 'renders string children', () => {
			const text = 'Simple loadable text';
			const { queryByText } = render(
				<Loadable isLoading={ false }>{ text }</Loadable>
			);
			expect( queryByText( text ) ).toBeInTheDocument();
		} );

		test( 'renders nothing when no children passed', () => {
			const { container } = render( <Loadable isLoading={ false } /> );
			expect( container.innerHTML ).toBe( '' );
		} );
	} );
} );
