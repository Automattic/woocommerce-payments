/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { render, screen, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import ProfileSyncStatus from '../profile-sync-status';

// Mock apiFetch — we don't want to fire real HTTP from a unit test.
jest.mock( '@wordpress/api-fetch', () => jest.fn() );
import apiFetch from '@wordpress/api-fetch';

describe( 'ProfileSyncStatus', () => {
	beforeEach( () => {
		apiFetch.mockReset();
		jest.useFakeTimers();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	describe( 'visual state', () => {
		it( 'renders the "never synced" state when last_synced is null and there is no error', () => {
			render(
				<ProfileSyncStatus
					sync={ {
						last_synced: null,
						last_error: null,
						debounce_seconds: 60,
					} }
					onRefresh={ jest.fn() }
				/>
			);

			expect( screen.getByRole( 'status' ) ).toHaveAttribute(
				'data-state',
				'never'
			);
			expect( screen.getByText( /Not yet synced/i ) ).toBeInTheDocument();
			expect(
				screen.getByRole( 'button', { name: /Retry sync/i } )
			).toBeEnabled();
		} );

		it( 'renders the "success" state with a relative-time string when last_synced is set', () => {
			const fiveMinAgo = Math.floor( Date.now() / 1000 ) - 5 * 60;

			render(
				<ProfileSyncStatus
					sync={ {
						last_synced: fiveMinAgo,
						last_error: null,
						debounce_seconds: 60,
					} }
					onRefresh={ jest.fn() }
				/>
			);

			expect( screen.getByRole( 'status' ) ).toHaveAttribute(
				'data-state',
				'success'
			);
			expect( screen.getByText( /Last synced/i ) ).toBeInTheDocument();
			expect( screen.getByText( /5 min ago/i ) ).toBeInTheDocument();
			// Success state hides the Retry button — sync is already current.
			expect(
				screen.queryByRole( 'button', { name: /Retry sync/i } )
			).not.toBeInTheDocument();
		} );

		// formatRelativeTime has four buckets keyed off `<60`, `<3600`,
		// `<86400`, and `>=86400`. An off-by-one in any boundary would
		// silently flip the displayed unit (e.g. 60s could render as
		// "just now" or "1 min ago"). Pin each bucket with a value that's
		// far enough from a boundary to be unambiguous.
		it.each( [
			[ 10, /just now/i ],
			[ 90, /1 min ago/i ],
			[ 7200, /2 hr ago/i ],
			[ 172800, /2 days ago/i ],
		] )(
			'formatRelativeTime renders the right bucket for %i seconds ago',
			( secondsAgo, expected ) => {
				const ts = Math.floor( Date.now() / 1000 ) - secondsAgo;
				render(
					<ProfileSyncStatus
						sync={ {
							last_synced: ts,
							last_error: null,
							debounce_seconds: 60,
						} }
						onRefresh={ jest.fn() }
					/>
				);
				expect( screen.getByText( expected ) ).toBeInTheDocument();
			}
		);

		it( 'renders the "failed" state with the error message and a Retry button', () => {
			render(
				<ProfileSyncStatus
					sync={ {
						last_synced: 1717500000,
						last_error: {
							message: 'WSN Profile POST returned HTTP 403.',
							timestamp: 1717500050,
						},
						debounce_seconds: 60,
					} }
					onRefresh={ jest.fn() }
				/>
			);

			expect( screen.getByRole( 'status' ) ).toHaveAttribute(
				'data-state',
				'failed'
			);
			expect(
				screen.getByText(
					/Sync failed: WSN Profile POST returned HTTP 403/i
				)
			).toBeInTheDocument();
			expect(
				screen.getByRole( 'button', { name: /Retry sync/i } )
			).toBeEnabled();
		} );
	} );

	describe( 'Retry click', () => {
		it( 'fires POST /wc/v3/payments/wsn/profile-resync', async () => {
			apiFetch.mockResolvedValueOnce( { status: 'scheduled' } );

			render(
				<ProfileSyncStatus
					sync={ {
						last_synced: null,
						last_error: null,
						debounce_seconds: 60,
					} }
					onRefresh={ jest.fn() }
				/>
			);

			userEvent.click(
				screen.getByRole( 'button', { name: /Retry sync/i } )
			);

			expect( apiFetch ).toHaveBeenCalledWith( {
				path: '/wc/v3/payments/wsn/profile-resync',
				method: 'POST',
			} );
		} );

		it( 'disables the Retry button while a request is pending and shows the "Syncing…" state', async () => {
			// apiFetch resolves but we never advance timers, so the optimistic
			// state stays in "syncing" with isRetrying === true.
			apiFetch.mockResolvedValueOnce( { status: 'scheduled' } );

			render(
				<ProfileSyncStatus
					sync={ {
						last_synced: null,
						last_error: null,
						debounce_seconds: 60,
					} }
					onRefresh={ jest.fn() }
				/>
			);

			userEvent.click(
				screen.getByRole( 'button', { name: /Retry sync/i } )
			);

			expect( screen.getByRole( 'status' ) ).toHaveAttribute(
				'data-state',
				'syncing'
			);
			expect( screen.getByText( /Syncing…/i ) ).toBeInTheDocument();
			expect(
				screen.getByRole( 'button', { name: /Retry sync/i } )
			).toBeDisabled();
		} );

		it( 'calls onRefresh after debounce_seconds + 2s', async () => {
			apiFetch.mockResolvedValueOnce( { status: 'scheduled' } );
			const onRefresh = jest.fn();

			render(
				<ProfileSyncStatus
					sync={ {
						last_synced: null,
						last_error: null,
						debounce_seconds: 60,
					} }
					onRefresh={ onRefresh }
				/>
			);

			userEvent.click(
				screen.getByRole( 'button', { name: /Retry sync/i } )
			);

			// Flush the apiFetch promise so the handler's setTimeout is
			// scheduled. Without this, advanceTimersByTime fires before
			// setTimeout was even registered and onRefresh never runs.
			await act( async () => {
				await Promise.resolve();
			} );

			// Not yet — debounce window timer hasn't fired.
			expect( onRefresh ).not.toHaveBeenCalled();

			act( () => {
				jest.advanceTimersByTime( 60 * 1000 + 2000 );
			} );

			expect( onRefresh ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'surfaces apiFetch failure as an inline error', async () => {
			apiFetch.mockRejectedValueOnce( {
				message: 'Network unreachable.',
				code: 'fetch_error',
			} );

			render(
				<ProfileSyncStatus
					sync={ {
						last_synced: null,
						last_error: null,
						debounce_seconds: 60,
					} }
					onRefresh={ jest.fn() }
				/>
			);

			userEvent.click(
				screen.getByRole( 'button', { name: /Retry sync/i } )
			);

			// After the rejection the isRetrying flag clears and the inline
			// error appears. Retry button re-enables so the merchant can try
			// again.
			expect( await screen.findByRole( 'alert' ) ).toBeInTheDocument();
			expect(
				screen.getByRole( 'button', { name: /Retry sync/i } )
			).toBeEnabled();
		} );
	} );
} );
