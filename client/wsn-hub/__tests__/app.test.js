/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { render, screen, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

// Mock @wordpress/media-utils — the Profile tab's LogoWithOverride +
// HeroBannerPicker import MediaUpload, which depends on wp.media (not in
// jest). Just rendering via the render prop is enough for the shell tests;
// per-tab tests cover the picker UX in detail.
jest.mock( '@wordpress/media-utils', () => ( {
	__esModule: true,
	MediaUpload: ( { render: renderProp } ) =>
		renderProp ? renderProp( { open: jest.fn() } ) : null,
} ) );

// Mock @wordpress/api-fetch so the OverviewDashboard's + WsnHubApp shell's
// mount-time fetches don't trip MSW's "no unhandled requests" guard. Returns
// a path-aware payload so each tab gets a shape it can render without
// crashing — the per-tab tests cover the data contracts in detail.
//
// The shell now owns the /wsn/settings fetch (lifted from ProfileTab), so
// this mock is the single source of truth for settings during shell tests.
const mockApiFetch = jest.fn();
jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: ( ...args ) => mockApiFetch( ...args ),
} ) );

beforeEach( () => {
	mockApiFetch.mockReset();
	mockApiFetch.mockImplementation( ( { path } ) => {
		if ( path && path.startsWith( '/wc/v3/payments/wsn/orders' ) ) {
			return Promise.resolve( {
				is_empty: true,
				stats: {},
				orders: [],
			} );
		}
		if ( path === '/wc/v3/payments/wsn/settings' ) {
			return Promise.resolve( {
				settings: {},
				feature_enabled: true,
				sync: {
					// Seed a recent successful sync so the Profile-tab
					// sync-state badge has data to render. The shell's
					// job is to extract this block and forward it to
					// ProfileTab; if it doesn't, the badge falls back
					// to the "never synced" state and the assertion
					// below fails.
					last_synced: Math.floor( Date.now() / 1000 ) - 5 * 60,
					last_synced_version: 'a'.repeat( 64 ),
					last_error: null,
					debounce_seconds: 60,
				},
				derivations: {
					logo_url: null,
					logo_source: 'site_logo',
					hero_image_url: null,
					shop_name: '',
					tagline: '',
					shipping_zones: [],
					currency: { code: 'USD', symbol: '$' },
					refund_page_label: null,
					refund_page_url: null,
					theme_type: 'block',
				},
			} );
		}
		if ( path === '/wc/v3/payments/wsn/pages' ) {
			return Promise.resolve( { policy_pages: [], other_pages: [] } );
		}
		return Promise.resolve( {} );
	} );
} );

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
// Switch into the Profile tab and wait for it to finish settling.
// Wraps the click + waitFor(heading) + microtask drain in a single act,
// catching ProfileTab's mount-time fetches (RefundPagePicker's /wsn/pages
// in particular) inside the act boundary. The standalone
// `await userEvent.click()` returns before the picker's apiFetch resolves,
// and a follow-up waitFor only flushes commit phases — neither catches
// the post-resolution setState on its own.
const switchToProfileTab = async () => {
	// The testing-library/no-unnecessary-act rule normally fires here
	// because userEvent already wraps its events in act. The exception:
	// ProfileTab's children fire a mount-time apiFetch
	// (RefundPagePicker → /wsn/pages) whose resolution lands AFTER the
	// userEvent.click await returns. Wrapping the click in our own act
	// extends the boundary across that downstream microtask so the
	// picker's setIsLoading(false) doesn't trip
	// @wordpress/jest-console's "not wrapped in act" check.
	// eslint-disable-next-line testing-library/no-unnecessary-act
	await act( async () => {
		await userEvent.click( screen.getByRole( 'tab', { name: 'Profile' } ) );
	} );
	await waitFor( () =>
		expect(
			screen.getByRole( 'heading', {
				name: /Storefront Profile/i,
			} )
		).toBeInTheDocument()
	);
	// Final microtask drain inside act so the picker's deferred state
	// updates land inside the boundary even when the heading appears
	// before the fetch resolves.
	await act( async () => {
		await Promise.resolve();
	} );
};

const switchToOverviewTab = async () => {
	// Same act wrapper rationale as switchToProfileTab — keeps any
	// in-flight Profile-tab teardown state updates inside the boundary
	// when this is called after a Profile switch.
	// eslint-disable-next-line testing-library/no-unnecessary-act
	await act( async () => {
		await userEvent.click(
			screen.getByRole( 'tab', { name: 'Overview' } )
		);
	} );
	await waitFor( () =>
		expect(
			screen.getByRole( 'heading', {
				name: /Shopping Network traffic and orders/i,
			} )
		).toBeInTheDocument()
	);
};

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
		it( 'renders the two-tab framework with all tab titles', async () => {
			await renderEnabled();

			expect(
				screen.getByRole( 'tab', { name: 'Overview' } )
			).toBeInTheDocument();
			expect(
				screen.getByRole( 'tab', { name: 'Profile' } )
			).toBeInTheDocument();
		} );

		it( 'seeds the initial tab from the URL hash when present', async () => {
			window.location.hash = '#profile';
			await renderEnabled();

			await waitFor( () => {
				expect(
					screen.getByRole( 'heading', {
						name: /Storefront Profile/i,
					} )
				).toBeInTheDocument();
			} );
		} );

		it( 'updates window.location.hash when a tab is clicked', async () => {
			await renderEnabled();

			await switchToProfileTab();

			expect( window.location.hash ).toBe( '#profile' );
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

		it( 'fetches /wsn/settings exactly once across mount + tab switches', async () => {
			await renderEnabled();

			// Wait for the shell's mount-time settings fetch to land.
			await waitFor( () =>
				expect(
					mockApiFetch.mock.calls.some(
						( call ) =>
							call[ 0 ].path === '/wc/v3/payments/wsn/settings'
					)
				).toBe( true )
			);

			// Switch into Profile tab — the legacy behavior would have fired
			// a second GET here. With the lifted fetch, the shell-owned state
			// is reused and no new call is made.
			await switchToProfileTab();
			// Switch back to Overview, then back to Profile again — still no
			// additional /wsn/settings GETs.
			await switchToOverviewTab();
			await switchToProfileTab();

			const settingsCalls = mockApiFetch.mock.calls.filter(
				( call ) => call[ 0 ].path === '/wc/v3/payments/wsn/settings'
			);
			expect( settingsCalls ).toHaveLength( 1 );
		} );

		it( 'extracts the `sync` block from /wsn/settings and forwards it to ProfileTab', async () => {
			await renderEnabled();

			// Wait for the shell-owned settings fetch to land + Profile tab to mount.
			await switchToProfileTab();

			// Sync-state badge data-state=success indicates the shell extracted
			// the `sync` block, forwarded it down to ProfileTab, and ProfileTab
			// forwarded it to ProfileSyncStatus. If any link in that chain
			// drops the prop, the badge falls back to "never synced" (data-state=never).
			const badge = await screen.findByRole( 'status', {
				name: ( accessibleName, element ) =>
					element?.classList?.contains(
						'wcpay-wsn-profile-sync-status'
					),
			} );
			expect( badge ).toHaveAttribute( 'data-state', 'success' );
		} );
	} );
} );
