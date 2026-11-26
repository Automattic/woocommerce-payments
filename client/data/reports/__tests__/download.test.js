/** @format */

/**
 * Internal dependencies
 */
import { feesDownloadEndpoint, getFeesCSVRequestURL } from '../resolvers';

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
			dateAfter: '2026-01-15 12:00:00',
			dateBefore: '2026-01-15 12:00:00',
			paymentMethodType: 'card',
			type: 'charge',
			search: [ 'pi_123' ],
		} );

		expect( url ).toContain( feesDownloadEndpoint );
		expect( url ).toContain( 'user_email=merchant%40example.com' );
		expect( url ).toContain( 'locale=en_US' );
		expect( url ).toContain( 'date_after=2026-01-15%2000%3A00%3A00' );
		expect( url ).toContain( 'date_before=2026-01-15%2023%3A59%3A59' );
		expect( url ).toContain( 'payment_method_type=card' );
		expect( url ).toContain( 'type%5B0%5D=charge' );
		expect( url ).toContain( 'search%5B0%5D=pi_123' );
		expect( url ).not.toContain( 'user_timezone' );
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
