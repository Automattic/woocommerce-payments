/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

// Mock @wordpress/api-fetch so the OverviewDashboard's mount-time fetch doesn't
// trip MSW's "no unhandled requests" guard in tests/js/jest-msw-setup.js. Every
// post-enable render mounts OverviewDashboard, which immediately calls
// apiFetch('/wc/v3/payments/wsn/orders?period=30d'). Returning the empty-state
// payload keeps the dashboard happy without coupling these shell tests to the
// orders endpoint's contract — that's covered by orders-table.test.js.
jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: jest.fn( () =>
		Promise.resolve( { is_empty: true, stats: {}, orders: [] } )
	),
} ) );

/**
 * Internal dependencies
 */
import WsnHubApp from '../app';

// Pre-enable state by default. Tests that exercise the enabled branch reassign
// before render. Cleared in afterEach so other modules can't leak in.
const originalWcpaySettings = window.wcpaySettings;

afterEach( () => {
	window.wcpaySettings = originalWcpaySettings;
} );

// `<TabPanel>` from @wordpress/components defers focus management to Ariakit,
// which fires async state updates after mount. waitFor() lets those settle
// before we assert, avoiding the `act()` warnings that @wordpress/jest-console
// would otherwise treat as hard test failures.
const renderEnabled = async () => {
	window.wcpaySettings = { wsn: { enabled: true } };
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
		window.history.replaceState( null, '', window.location.pathname );
	} );

	describe( 'pre-enable state (wsn.enabled is falsy)', () => {
		beforeEach( () => {
			window.wcpaySettings = { wsn: { enabled: false } };
		} );

		it( 'renders the pre-enable hero with the CTA button', () => {
			render( <WsnHubApp /> );

			expect(
				screen.getByRole( 'heading', {
					name: /List once\. Sell to millions of Woo shoppers\./i,
				} )
			).toBeInTheDocument();
			expect(
				screen.getByRole( 'button', {
					name: /Enable Woo Shopping Network/i,
				} )
			).toBeInTheDocument();
		} );

		it( 'does NOT render the tab nav when WSN is disabled', () => {
			render( <WsnHubApp /> );

			expect(
				screen.queryByRole( 'tab', { name: 'Visibility' } )
			).not.toBeInTheDocument();
			expect(
				screen.queryByRole( 'tab', { name: 'Profile' } )
			).not.toBeInTheDocument();
		} );

		it( 'falls back to disabled when wcpaySettings.wsn is missing entirely', () => {
			window.wcpaySettings = {};
			render( <WsnHubApp /> );

			expect(
				screen.getByRole( 'button', {
					name: /Enable Woo Shopping Network/i,
				} )
			).toBeInTheDocument();
		} );
	} );

	describe( 'post-enable state (wsn.enabled is true)', () => {
		it( 'renders the three-tab framework with all tab titles', async () => {
			await renderEnabled();

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

		it( 'seeds the initial tab from the URL hash when present', async () => {
			window.location.hash = '#visibility';
			await renderEnabled();

			await waitFor( () => {
				expect(
					screen.getByText( /Visibility content lands in RSM-2480/i )
				).toBeInTheDocument();
			} );
		} );

		it( 'updates window.location.hash when a tab is clicked', async () => {
			await renderEnabled();

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
			await renderEnabled();

			// Overview tab is selected — confirmed by the section heading from
			// the OverviewDashboard (the post-enable view).
			expect(
				screen.getByRole( 'heading', {
					name: /Shopping Network traffic and orders/i,
				} )
			).toBeInTheDocument();
		} );
	} );
} );
