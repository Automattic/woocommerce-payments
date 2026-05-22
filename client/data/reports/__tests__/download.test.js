/** @format */

/**
 * Internal dependencies
 */
import { feesDownloadEndpoint, getFeesCSVRequestURL } from '../download';

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
		const url = getFeesCSVRequestURL( {
			userEmail: 'merchant@example.com',
			locale: 'en_US',
			dateAfter: '2026-01-01T00:00:00Z',
			dateBefore: '2026-02-01T00:00:00Z',
			paymentMethodType: 'card',
			type: 'charge',
			search: [ 'pi_123' ],
		} );

		expect( url ).toContain( feesDownloadEndpoint );
		expect( url ).toContain( 'user_email=merchant%40example.com' );
		expect( url ).toContain( 'locale=en_US' );
		expect( url ).toContain( 'date_after=' );
		expect( url ).toContain( 'date_before=' );
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
		expect( url ).not.toContain( 'date_after' );
		expect( url ).not.toContain( 'type' );
		expect( url ).not.toContain( 'payment_method_type' );
	} );
} );
