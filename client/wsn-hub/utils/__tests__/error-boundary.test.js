/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import { TabErrorBoundary } from '../error-boundary';

// A child that throws on render to trigger the boundary.
const Boom = () => {
	throw new Error( 'Kaboom' );
};

describe( 'TabErrorBoundary', () => {
	let consoleErrorSpy;

	beforeEach( () => {
		// React logs caught render errors to console.error; silence it so the
		// test output stays readable.
		consoleErrorSpy = jest
			.spyOn( console, 'error' )
			.mockImplementation( () => {} );
	} );

	afterEach( () => {
		consoleErrorSpy.mockRestore();
	} );

	it( 'renders children when no error is thrown', () => {
		render(
			<TabErrorBoundary>
				<div>Healthy tab content</div>
			</TabErrorBoundary>
		);

		expect( screen.getByText( 'Healthy tab content' ) ).toBeInTheDocument();
	} );

	it( 'renders the error Notice fallback when a child component throws', () => {
		render(
			<TabErrorBoundary>
				<Boom />
			</TabErrorBoundary>
		);

		// The copy appears in BOTH the Notice's visible content and the
		// @wordpress/components a11y-speak live region. We only need to
		// confirm at least one match — getAllByText handles the duplication.
		expect(
			screen.getAllByText(
				/This panel failed to load\. Refresh the page to try again\./
			).length
		).toBeGreaterThan( 0 );
		expect(
			screen.getByRole( 'button', { name: /Refresh/i } )
		).toBeInTheDocument();
	} );

	it( 'reloads the window when the Refresh button is clicked', async () => {
		// jsdom marks `window.location` as non-configurable, so we can't
		// redefine `reload` directly. Replacing the whole `window.location`
		// object IS allowed and is the canonical jsdom workaround.
		const originalLocation = window.location;
		const reloadMock = jest.fn();
		delete window.location;
		window.location = { ...originalLocation, reload: reloadMock };

		try {
			render(
				<TabErrorBoundary>
					<Boom />
				</TabErrorBoundary>
			);

			await userEvent.click(
				screen.getByRole( 'button', { name: /Refresh/i } )
			);

			expect( reloadMock ).toHaveBeenCalledTimes( 1 );
		} finally {
			window.location = originalLocation;
		}
	} );
} );
