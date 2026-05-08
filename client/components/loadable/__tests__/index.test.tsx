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

		test( 'is announced to assistive tech by default (no aria-hidden)', () => {
			const { container } = render(
				<Loadable isLoading placeholder="Loading…" />
			);
			const placeholder = container.querySelector(
				'.is-loadable-placeholder'
			);

			expect( placeholder ).not.toHaveAttribute( 'aria-hidden' );
		} );

		test( 'forwards aria-hidden to the placeholder span', () => {
			const { container } = render(
				<Loadable
					isLoading
					aria-hidden
					placeholder="shape-only filler"
				/>
			);
			const placeholder = container.querySelector(
				'.is-loadable-placeholder'
			);

			expect( placeholder ).toHaveAttribute( 'aria-hidden', 'true' );
		} );

		test( 'forwards arbitrary aria-* attributes to the placeholder span', () => {
			const { container } = render(
				<Loadable
					isLoading
					aria-label="loading payout amount"
					placeholder="$0.00"
				/>
			);
			const placeholder = container.querySelector(
				'.is-loadable-placeholder'
			);

			expect( placeholder ).toHaveAttribute(
				'aria-label',
				'loading payout amount'
			);
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
