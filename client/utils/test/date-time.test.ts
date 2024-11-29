/**
 * External dependencies
 */
import { dateI18n } from '@wordpress/date';

/**
 * Internal dependencies
 */
import {
	formatDateTimeFromString,
	formatDateTimeFromTimestamp,
} from 'wcpay/utils/date-time';

// Mock dateI18n
jest.mock( '@wordpress/date', () => ( {
	dateI18n: jest.fn( ( format, date ) => {
		return jest
			.requireActual( '@wordpress/date' )
			.dateI18n( format, date, 'UTC' ); // Force UTC
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

		it( 'should handle GMT/UTC setting correctly when useGmt is true', () => {
			const dateTime = '2024-10-23 15:28:26Z';
			const options = { useGmt: true, includeTime: true };
			const formatted = formatDateTimeFromString( dateTime, options );

			expect( formatted ).toBe( '2024-10-23 / 15:28' );
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
			const options = { customFormat: 'd-m-Y H:i:s', useGmt: true };
			const formatted = formatDateTimeFromTimestamp( timestamp, options );

			expect( formatted ).toBe( '24-10-2024 10:48:26' );
		} );

		it( 'should exclude time if includeTime is set to false', () => {
			const timestamp = 1729766906; // 2024-10-23 10:48:26 UTC
			const options = { useGmt: true };
			const formatted = formatDateTimeFromTimestamp( timestamp, options );

			expect( formatted ).toBe( '2024-10-24' );
		} );

		it( 'should use custom separator when provided', () => {
			const timestamp = 1729766906; // 2024-10-23 10:48:26 UTC
			const options = {
				separator: ' - ',
				useGmt: true,
				includeTime: true,
			};
			const formatted = formatDateTimeFromTimestamp( timestamp, options );

			expect( formatted ).toBe( '2024-10-24 - 10:48' );
		} );
	} );
} );
