/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { DisputeDefenderPanel } from '../index';

jest.mock( '@wordpress/api-fetch' );
jest.mock( 'tracks', () => ( {
	recordEvent: jest.fn(),
} ) );

const baseFraudulentDispute: any = {
	id: 'dp_test_123',
	reason: 'fraudulent',
	amount: 10000,
	currency: 'usd',
};

describe( 'DisputeDefenderPanel', () => {
	beforeEach( () => {
		( window as any ).wcpaySettings = {
			featureFlags: { isDisputeDefenderEnabled: true },
		};
		( apiFetch as unknown as jest.Mock ).mockReset();
	} );

	it( 'renders the Generate button when the flag is on and reason is fraudulent', () => {
		render( <DisputeDefenderPanel dispute={ baseFraudulentDispute } /> );
		expect(
			screen.getByRole( 'button', { name: /generate with ai/i } )
		).toBeInTheDocument();
	} );

	it( 'renders nothing when the dispute reason is not fraudulent', () => {
		const dispute = { ...baseFraudulentDispute, reason: 'duplicate' };
		const { container } = render(
			<DisputeDefenderPanel dispute={ dispute } />
		);
		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'renders nothing when the feature flag is off', () => {
		( window as any ).wcpaySettings = {
			featureFlags: { isDisputeDefenderEnabled: false },
		};
		const { container } = render(
			<DisputeDefenderPanel dispute={ baseFraudulentDispute } />
		);
		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'calls the REST endpoint and renders the draft on click', async () => {
		( apiFetch as unknown as jest.Mock ).mockResolvedValue( {
			narrative: 'The charge is legitimate.',
			gaps: [],
			meta: {
				model: 'claude-sonnet-4-5',
				prompt_version: 'v1',
				generated_at: '2026-04-16T00:00:00Z',
			},
		} );

		render( <DisputeDefenderPanel dispute={ baseFraudulentDispute } /> );

		const button = screen.getByRole( 'button', {
			name: /generate with ai/i,
		} );
		// Wrap the click + async resolution in act() so the second state
		// update (fired after apiFetch resolves on a subsequent microtask)
		// is captured inside the test boundary. Without this, React 18
		// emits an act() warning that @wordpress/jest-console treats as a
		// test failure.
		await act( async () => {
			await userEvent.click( button );
		} );

		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/wc/v3/payments/disputes/dp_test_123/defense/draft',
			method: 'POST',
		} );

		await waitFor( () => {
			expect(
				screen.getByText( /The charge is legitimate/i )
			).toBeInTheDocument();
		} );
	} );

	it( 'shows an error when the REST call fails', async () => {
		( apiFetch as unknown as jest.Mock ).mockRejectedValue(
			new Error( 'Upstream failure' )
		);

		render( <DisputeDefenderPanel dispute={ baseFraudulentDispute } /> );
		await act( async () => {
			await userEvent.click(
				screen.getByRole( 'button', { name: /generate with ai/i } )
			);
		} );

		await waitFor( () => {
			// Notice renders the message in multiple wrappers for a11y — use
			// getAllByText so the presence check doesn't fail on duplicates.
			expect(
				screen.getAllByText( /Upstream failure/i ).length
			).toBeGreaterThan( 0 );
		} );
	} );
} );
