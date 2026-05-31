/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

// MediaUpload depends on wp.media which doesn't exist in jest. The components
// under test render MediaUpload via the `render` prop, which receives an `open`
// callback — we just stub the wrapper so the component tree renders.
jest.mock( '@wordpress/media-utils', () => ( {
	__esModule: true,
	MediaUpload: ( { render: renderProp } ) =>
		renderProp ? renderProp( { open: jest.fn() } ) : null,
} ) );

// Stub apiFetch so the Profile tab's mount-time + pages-controller fetches
// don't trip MSW's "no unhandled requests" guard.
const mockApiFetch = jest.fn();
jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: ( ...args ) => mockApiFetch( ...args ),
} ) );

/**
 * Internal dependencies
 */
import ProfileTab from '../index';

const SETTINGS_PAYLOAD = {
	settings: {
		hero_image_id: null,
		logo_override_id: null,
		contact_email: 'hello@example.com',
		refund_page_id: null,
	},
	feature_enabled: true,
	derivations: {
		logo_url: 'https://example.test/logo.png',
		logo_source: 'site_logo',
		hero_image_url: null,
		shop_name: 'Midcentury Manila',
		tagline: 'Mid-century furniture',
		shipping_regions: [ 'United States', 'Canada' ],
		free_shipping: {
			has_free_shipping: true,
			human_summary: 'Orders over $50 (US) · Orders over $75 (CA)',
			zones: [],
		},
		refund_page_label: null,
		refund_page_url: null,
		theme_type: 'block',
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

beforeEach( () => {
	mockApiFetch.mockReset();
	mockApiFetch.mockImplementation( ( { path } ) => {
		if ( path === '/wc/v3/payments/wsn/settings' ) {
			return Promise.resolve( SETTINGS_PAYLOAD );
		}
		if ( path === '/wc/v3/payments/wsn/pages' ) {
			return Promise.resolve( PAGES_PAYLOAD );
		}
		return Promise.resolve( {} );
	} );
} );

describe( 'ProfileTab', () => {
	it( 'shows a loading state, then renders both cards from the GET response', async () => {
		render( <ProfileTab /> );

		expect( screen.getByText( /Loading Profile/i ) ).toBeInTheDocument();

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
	} );

	it( 'renders the readonly free-shipping summary from derivations', async () => {
		render( <ProfileTab /> );

		await waitFor( () =>
			expect(
				screen.getByDisplayValue(
					'Orders over $50 (US) · Orders over $75 (CA)'
				)
			).toBeInTheDocument()
		);
	} );

	it( 'renders the readonly shipping regions joined with commas', async () => {
		render( <ProfileTab /> );

		await waitFor( () =>
			expect(
				screen.getByDisplayValue( 'United States, Canada' )
			).toBeInTheDocument()
		);
	} );

	it( 'disables Save until the merchant edits something', async () => {
		render( <ProfileTab /> );

		await waitFor( () =>
			expect(
				screen.getByRole( 'button', { name: /Save changes/i } )
			).toBeDisabled()
		);

		// Edit the contact email — dirty flag must flip.
		const emailInput = screen.getByDisplayValue( 'hello@example.com' );
		userEvent.clear( emailInput );
		userEvent.type( emailInput, 'support@example.com' );

		await waitFor( () =>
			expect(
				screen.getByRole( 'button', { name: /Save changes/i } )
			).not.toBeDisabled()
		);
	} );

	it( 'PUTs only the Profile-relevant fields when Save is clicked', async () => {
		render( <ProfileTab /> );

		await waitFor( () =>
			expect(
				screen.getByDisplayValue( 'hello@example.com' )
			).toBeInTheDocument()
		);

		const emailInput = screen.getByDisplayValue( 'hello@example.com' );
		userEvent.clear( emailInput );
		userEvent.type( emailInput, 'new@example.com' );

		userEvent.click(
			screen.getByRole( 'button', { name: /Save changes/i } )
		);

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
	} );
} );
