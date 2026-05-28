/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import WsnHubApp from '../app';

// `<TabPanel>` from @wordpress/components defers focus management to Ariakit,
// which fires async state updates after mount. waitFor() lets those settle
// before we assert, avoiding the `act()` warnings that @wordpress/jest-console
// would otherwise treat as hard test failures. Pattern mirrors the existing
// reports/tabs.test.tsx in this codebase.
const renderApp = async () => {
	const result = render( <WsnHubApp /> );
	await waitFor( () => {
		expect(
			screen.getByRole( 'tab', { name: 'Overview' } )
		).toBeInTheDocument();
	} );
	return result;
};

describe( 'WsnHubApp', () => {
	beforeEach( () => {
		// Reset URL hash between tests so prior runs don't leak the active tab.
		window.history.replaceState( null, '', window.location.pathname );
	} );

	it( 'renders the three-tab framework with all tab titles', async () => {
		await renderApp();

		expect(
			screen.getByRole( 'tab', { name: 'Overview' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'tab', { name: 'Visibility' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'tab', { name: 'Profile' } )
		).toBeInTheDocument();
	} );

	it( 'defaults to the Overview tab when no hash is present', async () => {
		await renderApp();

		expect(
			screen.getByText( /Overview content lands in RSM-2493/i )
		).toBeInTheDocument();
		expect(
			screen.queryByText( /Visibility content lands in RSM-2480/i )
		).not.toBeInTheDocument();
	} );

	it( 'seeds the initial tab from the URL hash when present', async () => {
		window.location.hash = '#visibility';
		await renderApp();

		await waitFor( () => {
			expect(
				screen.getByText( /Visibility content lands in RSM-2480/i )
			).toBeInTheDocument();
		} );
		expect(
			screen.queryByText( /Overview content lands in RSM-2493/i )
		).not.toBeInTheDocument();
	} );

	it( 'updates window.location.hash when a tab is clicked', async () => {
		await renderApp();

		// userEvent v13 API: direct call, no setup() factory.
		userEvent.click( screen.getByRole( 'tab', { name: 'Profile' } ) );

		await waitFor( () => {
			expect( window.location.hash ).toBe( '#profile' );
		} );
		expect(
			screen.getByText( /Profile content lands in RSM-2481/i )
		).toBeInTheDocument();
	} );

	it( 'ignores an unknown hash and falls back to Overview', async () => {
		window.location.hash = '#not-a-real-tab';
		await renderApp();

		expect(
			screen.getByText( /Overview content lands in RSM-2493/i )
		).toBeInTheDocument();
	} );
} );
