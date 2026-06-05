/** @format */

/**
 * Internal dependencies
 */
import { feesDownloadEndpoint, getFeesCSVRequestURL } from '../resolvers';

// Match the resolver's user_timezone helper so the test is deterministic.
jest.mock( 'utils', () => ( {
	...jest.requireActual( 'utils' ),
	getUserTimeZone: () => 'UTC',
} ) );

describe( 'Fees report CSV download helpers', () => {
	it( 'exposes the REST endpoint constant', () => {
		expect( feesDownloadEndpoint ).toBe(
			'/wc/v3/payments/reports/fees/download'
		);
	} );

	it( 'serializes a query into the download URL', () => {
		// Plain local-time strings match how date picker filters arrive in
		// `getQuery()`. The forced America/New_York TZ in jest-global-setup
		// makes the start/end-of-day → UTC math deterministic across machines.
		const url = getFeesCSVRequestURL( {
			userEmail: 'merchant@example.com',
			locale: 'en_US',
			dateAfter: '2026-01-15 12:00:00',
			dateBefore: '2026-01-15 12:00:00',
			paymentMethodType: 'card',
			type: 'charge',
			search: [ 'pi_123' ],
		} );

		expect( url ).toContain( feesDownloadEndpoint );
		expect( url ).toContain( 'user_email=merchant%40example.com' );
		expect( url ).toContain( 'locale=en_US' );
		// Lock the serialized values, not just key presence: date_after is the
		// start-of-local-day (00:00 EST → 05:00 UTC in January) and date_before
		// is the end-of-local-day (23:59:59 EST → 04:59:59 UTC the next day).
		// A regression in the boundary direction, timezone normalization, or
		// dateI18n format string would slip past a presence-only assertion.
		expect( url ).toContain( 'date_after=2026-01-15%2005%3A00%3A00' );
		expect( url ).toContain( 'date_before=2026-01-16%2004%3A59%3A59' );
		expect( url ).toContain( 'payment_method_type=card' );
		expect( url ).toContain( 'type%5B0%5D=charge' );
		expect( url ).toContain( 'search%5B0%5D=pi_123' );
		expect( url ).toContain( 'user_timezone=UTC' );
	} );

	it( 'omits empty filters from the URL', () => {
		const url = getFeesCSVRequestURL( {
			userEmail: 'merchant@example.com',
		} );

		expect( url ).toContain( feesDownloadEndpoint );
		// Positive assertion: the always-passed `user_email` MUST still appear
		// when every other filter is empty. Without this, a regression that
		// accidentally gated `user_email` on another filter being present
		// would pass — and a Fees export without a recipient address fails
		// silently on the backend.
		expect( url ).toContain( 'user_email=merchant%40example.com' );
		expect( url ).not.toContain( 'date_after' );
		expect( url ).not.toContain( 'type' );
		expect( url ).not.toContain( 'payment_method_type' );
	} );
} );
