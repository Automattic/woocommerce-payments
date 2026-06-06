/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

// MediaUpload depends on wp.media which doesn't exist in jest. The components
// under test render MediaUpload via the `render` prop, which receives an `open`
// callback — we just stub the wrapper so the component tree renders.
jest.mock( '@wordpress/media-utils', () => ( {
	__esModule: true,
	MediaUpload: ( { render: renderProp } ) =>
		renderProp ? renderProp( { open: jest.fn() } ) : null,
} ) );

// apiFetch is still stubbed because:
//   - the PUT-on-save flow goes through apiFetch
//   - the ContactPoliciesCard's pages-controller fetch (/wsn/pages) still
//     happens inside this tab
// The /wsn/settings GET, however, is now the SHELL's responsibility — this
// test passes settings/derivations directly as props instead.
const mockApiFetch = jest.fn();
jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: ( ...args ) => mockApiFetch( ...args ),
} ) );

/**
 * Internal dependencies
 */
import ProfileTab from '../index';

const SETTINGS = {
	hero_image_id: null,
	logo_override_id: null,
	contact_email: 'hello@example.com',
	refund_page_id: null,
};

const DERIVATIONS = {
	logo_url: 'https://example.test/logo.png',
	logo_source: 'site_logo',
	hero_image_url: null,
	shop_name: 'Midcentury Manila',
	tagline: 'Mid-century furniture',
	shipping_zones: [
		{
			zone_id: 1,
			zone_locations: [ { type: 'country', code: 'US' } ],
			is_rest_of_world: false,
			free_shipping: { min_amount: 50, requires: 'min_amount' },
		},
		{
			zone_id: 2,
			zone_locations: [ { type: 'country', code: 'CA' } ],
			is_rest_of_world: false,
			free_shipping: { min_amount: 75, requires: 'min_amount' },
		},
	],
	refund_page_label: null,
	refund_page_url: null,
	theme_type: 'block',
	location: {
		country: 'US',
		region: 'CA',
		city: 'San Francisco',
		country_label: 'United States (US)',
		region_label: 'California',
	},
};

const PAGES_PAYLOAD = {
	policy_pages: [
		{
			id: 42,
			title: 'Refund & Returns Policy',
			edit_url: '#',
			category: 'refund_returns',
		},
	],
	other_pages: [ { id: 14, title: 'About Us', edit_url: '#' } ],
};

const renderProfile = ( overrides = {} ) => {
	const props = {
		settings: SETTINGS,
		derivations: DERIVATIONS,
		isLoading: false,
		loadError: null,
		onRetry: jest.fn(),
		refreshSettings: jest.fn().mockResolvedValue( undefined ),
		...overrides,
	};
	return { ...render( <ProfileTab { ...props } /> ), props };
};

beforeEach( () => {
	mockApiFetch.mockReset();
	mockApiFetch.mockImplementation( ( { path } ) => {
		if ( path === '/wc/v3/payments/wsn/pages' ) {
			return Promise.resolve( PAGES_PAYLOAD );
		}
		// Any PUT (or other request) lands here — return empty so the
		// dirty-aware save flow can complete; the shell's refreshSettings
		// callback (a spy) is what we observe instead of a follow-up GET.
		return Promise.resolve( {} );
	} );
} );

describe( 'ProfileTab', () => {
	it( 'renders both cards from the supplied props (no mount-time fetch)', async () => {
		renderProfile();

		await waitFor( () =>
			expect(
				screen.getByRole( 'heading', { name: /Storefront Profile/i } )
			).toBeInTheDocument()
		);

		// Branding card heading — exact string match, NOT regex (the regex
		// would also match the "Theme branding" sub-label).
		expect( screen.getByText( 'Branding' ) ).toBeInTheDocument();
		expect(
			screen.getByDisplayValue( 'Midcentury Manila' )
		).toBeInTheDocument();

		// Contact & Policies card content
		expect( screen.getByText( 'Contact & Policies' ) ).toBeInTheDocument();
		expect(
			screen.getByDisplayValue( 'hello@example.com' )
		).toBeInTheDocument();

		// ProfileTab MUST NOT issue its own /wsn/settings GET — that fetch
		// now lives in the WsnHubApp shell.
		const settingsCalls = mockApiFetch.mock.calls.filter(
			( call ) => call[ 0 ].path === '/wc/v3/payments/wsn/settings'
		);
		expect( settingsCalls ).toHaveLength( 0 );
	} );

	it( 'shows the loading state when isLoading prop is true', () => {
		renderProfile( { settings: null, isLoading: true } );
		expect( screen.getByText( /Loading Profile/i ) ).toBeInTheDocument();
	} );

	it( 'renders the load-error Notice with a Retry button bound to onRetry', async () => {
		const onRetry = jest.fn();
		// In real usage the shell sets isLoading=false in its catch branch
		// alongside loadError — so the merchant can interact with Retry.
		renderProfile( {
			settings: null,
			isLoading: false,
			loadError: 'Network down',
			onRetry,
		} );

		// Notice copy renders both inside the component AND inside a11y-speak,
		// so use getAllByText and assert at least one match exists.
		expect(
			screen.getAllByText( /Could not load Profile settings/i ).length
		).toBeGreaterThan( 0 );
		await userEvent.click(
			screen.getByRole( 'button', { name: /Try again/i } )
		);
		expect( onRetry ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'renders the readonly free-shipping summary derived from shipping_zones', async () => {
		renderProfile();

		// New shape: zone labels come from zone_locations[].code, not
		// merchant-chosen zone_name. min_amount is rendered as a bare
		// number; receiver-side rendering handles currency formatting.
		await waitFor( () =>
			expect(
				screen.getByDisplayValue(
					'Orders over 50 (US) · Orders over 75 (CA)'
				)
			).toBeInTheDocument()
		);
	} );

	it( 'renders the readonly shipping regions joined with commas (codes, not names)', async () => {
		renderProfile();

		// New shape: shipping_zones[].zone_locations[].code provides the
		// label. Previous behavior used merchant-chosen zone_name strings
		// like "United States" / "Canada"; we now ship ISO codes.
		await waitFor( () =>
			expect( screen.getByDisplayValue( 'US, CA' ) ).toBeInTheDocument()
		);
	} );

	it( 'renders the readonly store-location string in City, Region, Country order', async () => {
		renderProfile();

		// Prefer the human labels (region_label "California", country_label
		// "United States (US)") over the codes (region "CA", country "US")
		// — the merchant UI shows the friendlier form while the WooPay
		// payload still ships the codes.
		await waitFor( () =>
			expect(
				screen.getByDisplayValue(
					'San Francisco, California, United States (US)'
				)
			).toBeInTheDocument()
		);
	} );

	it( 'falls back to region/country codes when labels are absent', async () => {
		renderProfile( {
			derivations: {
				...DERIVATIONS,
				location: {
					country: 'US',
					region: 'CA',
					city: 'San Francisco',
					country_label: null,
					region_label: null,
				},
			},
		} );

		await waitFor( () =>
			expect(
				screen.getByDisplayValue( 'San Francisco, CA, US' )
			).toBeInTheDocument()
		);
	} );

	it( 'renders em-dash when no location resolves', async () => {
		renderProfile( {
			derivations: {
				...DERIVATIONS,
				location: {
					country: null,
					region: null,
					city: null,
					country_label: null,
					region_label: null,
				},
			},
		} );

		// waitFor wraps polling assertions in act() — needed so the
		// RefundPagePicker's mount-time /wsn/pages fetch state updates
		// land inside act and don't trip @wordpress/jest-console.
		await waitFor( () => {
			const storeLocationLabel = screen.getByText( 'Store location' );
			// ReadonlySyncedField uses a <textarea readonly> so long
			// values wrap instead of clipping. Selector targets the
			// textarea, not <input>.
			const input =
				storeLocationLabel.parentElement.querySelector(
					'textarea[readonly]'
				);
			expect( input ).toHaveValue( '—' );
		} );
	} );

	it( 'disables Save until the merchant edits something', async () => {
		renderProfile();

		await waitFor( () =>
			expect(
				screen.getByRole( 'button', { name: /Save changes/i } )
			).toBeDisabled()
		);

		// Edit the contact email — dirty flag must flip.
		const emailInput = screen.getByDisplayValue( 'hello@example.com' );
		await userEvent.clear( emailInput );
		await userEvent.type( emailInput, 'support@example.com' );

		await waitFor( () =>
			expect(
				screen.getByRole( 'button', { name: /Save changes/i } )
			).not.toBeDisabled()
		);
	} );

	it( 'PUTs only the Profile-relevant fields when Save is clicked, then calls refreshSettings', async () => {
		// refreshSettings returns a payload whose sync.last_synced
		// differs from the baseline so the post-save polling exits
		// immediately (advanced=true) without entering the geometric
		// backoff loop. The point of this test is the PUT shape and
		// the single refresh call — NOT the polling semantics, which
		// are exercised at the polling level elsewhere.
		const refreshSettings = jest.fn().mockResolvedValue( {
			sync: { last_synced: 12345 },
		} );
		renderProfile( { refreshSettings } );

		await waitFor( () =>
			expect(
				screen.getByDisplayValue( 'hello@example.com' )
			).toBeInTheDocument()
		);

		const emailInput = screen.getByDisplayValue( 'hello@example.com' );
		await userEvent.clear( emailInput );
		await userEvent.type( emailInput, 'new@example.com' );

		// Wrap click + post-PUT state updates in act so the fire-and-
		// forget polling promise's setStates (setIsSaving(false),
		// setSaveNotice, setPendingMediaUrls) all land inside the act
		// boundary instead of landing later and tripping
		// @wordpress/jest-console.
		// eslint-disable-next-line testing-library/no-unnecessary-act
		await act( async () => {
			await userEvent.click(
				screen.getByRole( 'button', { name: /Save changes/i } )
			);
		} );

		// Wait for the PUT call to fire — once it's in mockApiFetch.mock.calls,
		// the dirty-aware save flow has completed. Then assert on the payload
		// outside the waitFor so we don't repeatedly retry assertion branches.
		await waitFor( () =>
			expect(
				mockApiFetch.mock.calls.find(
					( call ) =>
						call[ 0 ].method === 'PUT' &&
						call[ 0 ].path === '/wc/v3/payments/wsn/settings'
				)
			).toBeDefined()
		);

		const putCall = mockApiFetch.mock.calls.find(
			( call ) =>
				call[ 0 ].method === 'PUT' &&
				call[ 0 ].path === '/wc/v3/payments/wsn/settings'
		);
		// Payload must contain ONLY the 4 Profile keys (not visibility_*, not enabled).
		const payload = putCall[ 0 ].data;
		expect( Object.keys( payload ).sort() ).toEqual( [
			'contact_email',
			'hero_image_id',
			'logo_override_id',
			'refund_page_id',
		] );
		expect( payload.contact_email ).toBe( 'new@example.com' );

		// After a successful PUT the tab delegates the refresh to the
		// shell — no second GET issued from inside the tab. handleSave
		// fires this refresh fire-and-forget (the Save button is
		// already off the moment the PUT resolves), so we waitFor the
		// state updates to settle inside act before the test exits.
		// The "Profile saved." notice is the observable signal that
		// the post-PUT state updates have flushed inside act.
		await waitFor( () =>
			expect( refreshSettings ).toHaveBeenCalledTimes( 1 )
		);
		await waitFor( () =>
			expect(
				screen.getAllByText( /Profile saved/i ).length
			).toBeGreaterThan( 0 )
		);
	} );

	it( 'renders ProfileSyncStatus when a sync prop is supplied', async () => {
		// Guards the one-line forwarding in profile-tab/index.js:
		// `<ProfileSyncStatus sync={ sync } onRefresh={ refreshSettings } />`.
		// If the prop name drifts or the component import disappears, this
		// test catches it — `last_synced` set + no error should render the
		// success state.
		renderProfile( {
			sync: {
				last_synced: Math.floor( Date.now() / 1000 ) - 5 * 60,
				last_synced_version: 'a'.repeat( 64 ),
				last_error: null,
				debounce_seconds: 60,
			},
		} );

		await waitFor( () =>
			expect(
				document.querySelector( '.wcpay-wsn-profile-sync-status' )
			).toHaveAttribute( 'data-state', 'success' )
		);
	} );
} );
