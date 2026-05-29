/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';

// Stub apiFetch so the OverviewDashboard's mount-time fetch doesn't trip the
// MSW "no unhandled requests" guard. Returns an empty-state payload — these
// tests only exercise the pre/post-enable dispatch, not the dashboard data.
jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: jest.fn( () =>
		Promise.resolve( { is_empty: true, stats: {}, orders: [] } )
	),
} ) );

/**
 * Internal dependencies
 */
import OverviewTab from '../index';

describe( 'OverviewTab dispatcher', () => {
	it( 'renders the PreEnableHero when isEnabled is false', () => {
		render(
			<OverviewTab isEnabled={ false } onEnabledChange={ () => {} } />
		);

		expect(
			screen.getByRole( 'button', {
				name: /Enable Woo Shopping Network/i,
			} )
		).toBeInTheDocument();
	} );

	it( 'renders the OverviewDashboard when isEnabled is true', async () => {
		render(
			<OverviewTab isEnabled={ true } onEnabledChange={ () => {} } />
		);

		// The dashboard's mount-time apiFetch resolves asynchronously; waitFor
		// lets the resulting setState calls settle before we assert.
		await waitFor( () => {
			expect(
				screen.getByRole( 'heading', {
					name: /Shopping Network traffic and orders/i,
				} )
			).toBeInTheDocument();
		} );
	} );

	it( 'is stateless on isEnabled — re-rendering with a new prop flips the view', async () => {
		const { rerender } = render(
			<OverviewTab isEnabled={ false } onEnabledChange={ () => {} } />
		);

		expect(
			screen.getByRole( 'button', {
				name: /Enable Woo Shopping Network/i,
			} )
		).toBeInTheDocument();

		rerender(
			<OverviewTab isEnabled={ true } onEnabledChange={ () => {} } />
		);

		// Parent prop change must immediately switch to the dashboard —
		// regression guard against reintroducing local useState(enabled)
		// that would otherwise pin to the initial prop value.
		await waitFor( () => {
			expect(
				screen.queryByRole( 'button', {
					name: /Enable Woo Shopping Network/i,
				} )
			).not.toBeInTheDocument();
		} );
		expect(
			screen.getByRole( 'heading', {
				name: /Shopping Network traffic and orders/i,
			} )
		).toBeInTheDocument();
	} );
} );
