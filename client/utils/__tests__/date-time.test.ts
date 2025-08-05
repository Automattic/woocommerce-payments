/**
 * Internal dependencies
 */
import {
	formatDateTimeFromString,
	formatDateTimeFromTimestamp,
} from 'wcpay/utils/date-time';

// Mock dateI18n
jest.mock( '@wordpress/date', () => ( {
	dateI18n: jest.fn( ( format, date, timezone ) => {
		return jest
			.requireActual( '@wordpress/date' )
			.dateI18n( format, date, timezone || 'UTC' ); // Use provided timezone or fallback to UTC
	} ),
} ) );

describe( 'Date/Time Formatting', () => {
	const originalWcpaySettings = window.wcpaySettings;
	const mockWcpaySettings = {
		dateFormat: 'Y-m-d',
		timeFormat: 'H:i',
	};

	beforeEach( () => {
		jest.clearAllMocks();
		window.wcpaySettings = mockWcpaySettings as typeof wcpaySettings;
	} );

	afterEach( () => {
		window.wcpaySettings = originalWcpaySettings;
	} );

	describe( 'formatDateTimeFromString', () => {
		it( 'should format using default WordPress settings', () => {
			const dateTime = '2024-10-23 15:28:26';
			const formatted = formatDateTimeFromString( dateTime, {
				includeTime: true,
			} );

			expect( formatted ).toBe( '2024-10-23 / 15:28' );
		} );

		it( 'should use custom format if provided', () => {
			const dateTime = '2024-10-23 15:28:26';
			const options = { customFormat: 'd-m-Y H:i:s' };
			const formatted = formatDateTimeFromString( dateTime, options );

			expect( formatted ).toBe( '23-10-2024 15:28:26' );
		} );

		it( 'should exclude time if includeTime is set to false', () => {
			const dateTime = '2024-10-23 15:28:26';
			const formatted = formatDateTimeFromString( dateTime );

			expect( formatted ).toBe( '2024-10-23' );
		} );

		it( 'should use custom separator when provided', () => {
			const dateTime = '2024-10-23 15:28:26';
			const options = { separator: ' - ', includeTime: true };
			const formatted = formatDateTimeFromString( dateTime, options );

			expect( formatted ).toBe( '2024-10-23 - 15:28' );
		} );

		it( 'should handle different timezones correctly', () => {
			// eslint-disable-next-line @typescript-eslint/no-var-requires
			const dateI18n = require( '@wordpress/date' ).dateI18n;
			// Temporarily modify the mock to use a different timezone: America/New_York
			dateI18n.mockImplementationOnce(
				( format: string, date: string | number ) => {
					return jest
						.requireActual( '@wordpress/date' )
						.dateI18n( format, date, 'America/New_York' );
				}
			);

			const dateTime = '2024-10-23 15:28:26';
			const formatted = formatDateTimeFromString( dateTime, {
				includeTime: true,
			} );

			expect( formatted ).toBe( '2024-10-23 / 11:28' );
		} );

		it( 'should respect explicitly provided timezone', () => {
			const dateTime = '2024-10-23 15:28:26';

			// Test with UTC timezone
			const formattedUTC = formatDateTimeFromString( dateTime, {
				includeTime: true,
				timezone: 'UTC',
			} );
			expect( formattedUTC ).toBe( '2024-10-23 / 15:28' );

			// Test with New York timezone
			const formattedNY = formatDateTimeFromString( dateTime, {
				includeTime: true,
				timezone: 'America/New_York',
			} );
			expect( formattedNY ).toBe( '2024-10-23 / 11:28' );
		} );
	} );

	describe( 'formatDateTimeFromTimestamp', () => {
		it( 'should format using default WordPress settings', () => {
			const timestamp = 1729766906; // 2024-10-23 10:48:26 UTC
			const formatted = formatDateTimeFromTimestamp( timestamp, {
				includeTime: true,
			} );

			expect( formatted ).toBe( '2024-10-24 / 10:48' );
		} );

		it( 'should use custom format if provided', () => {
			const timestamp = 1729766906; // 2024-10-23 10:48:26 UTC
			const options = { customFormat: 'd-m-Y H:i:s' };
			const formatted = formatDateTimeFromTimestamp( timestamp, options );

			expect( formatted ).toBe( '24-10-2024 10:48:26' );
		} );

		it( 'should exclude time if includeTime is set to false', () => {
			const timestamp = 1729766906; // 2024-10-23 10:48:26 UTC
			const formatted = formatDateTimeFromTimestamp( timestamp );

			expect( formatted ).toBe( '2024-10-24' );
		} );

		it( 'should use custom separator when provided', () => {
			const timestamp = 1729766906; // 2024-10-23 10:48:26 UTC
			const options = {
				separator: ' - ',
				includeTime: true,
			};
			const formatted = formatDateTimeFromTimestamp( timestamp, options );

			expect( formatted ).toBe( '2024-10-24 - 10:48' );
		} );

		it( 'should handle different timezones correctly', () => {
			// eslint-disable-next-line @typescript-eslint/no-var-requires
			const dateI18n = require( '@wordpress/date' ).dateI18n;
			// Temporarily modify the mock to use a different timezone: America/New_York
			dateI18n.mockImplementationOnce(
				( format: string, date: string | number ) => {
					return jest
						.requireActual( '@wordpress/date' )
						.dateI18n( format, date, 'America/New_York' );
				}
			);

			const timestamp = 1729766906; // 2024-10-24 10:48:26 UTC
			const formatted = formatDateTimeFromTimestamp( timestamp, {
				includeTime: true,
			} );

			// In New York (EDT), this should be 4 hours behind UTC
			expect( formatted ).toBe( '2024-10-24 / 06:48' );
		} );

		it( 'should respect explicitly provided timezone', () => {
			const timestamp = 1729766906; // 2024-10-24 10:48:26 UTC

			// Test with UTC timezone
			const formattedUTC = formatDateTimeFromTimestamp( timestamp, {
				includeTime: true,
				timezone: 'UTC',
			} );
			expect( formattedUTC ).toBe( '2024-10-24 / 10:48' );

			// Test with New York timezone
			const formattedNY = formatDateTimeFromTimestamp( timestamp, {
				includeTime: true,
				timezone: 'America/New_York',
			} );
			expect( formattedNY ).toBe( '2024-10-24 / 06:48' );
		} );
	} );
} );
