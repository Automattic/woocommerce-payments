/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
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

// Mock @wordpress/api-fetch so the OverviewDashboard's + ProfileTab's
// mount-time fetches don't trip MSW's "no unhandled requests" guard. Returns
// a path-aware payload so each tab gets a shape it can render without
// crashing — the per-tab tests cover the data contracts in detail.
jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: jest.fn( ( { path } ) => {
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
				derivations: {
					logo_url: null,
					logo_source: 'site_logo',
					hero_image_url: null,
					shop_name: '',
					tagline: '',
					shipping_regions: [],
					free_shipping: {
						has_free_shipping: false,
						human_summary: '',
						zones: [],
					},
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
	} ),
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
			// Profile tab renders its own heading once mounted — confirms the
			// click reached the ProfileTab component (not just the tab button).
			await waitFor( () =>
				expect(
					screen.getByRole( 'heading', {
						name: /Storefront Profile/i,
					} )
				).toBeInTheDocument()
			);
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
