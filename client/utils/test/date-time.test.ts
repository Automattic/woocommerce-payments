/**
 * Internal dependencies
 */
import { formatUserDateTime } from 'wcpay/utils/date-time';
import { dateI18n } from '@wordpress/date';

describe( 'formatUserDateTime', () => {
	const originalWcpaySettings = window.wcpaySettings;
	const mockWcpaySettings = {
		dateFormat: 'Y-m-d',
		timeFormat: 'H:i',
	};

	beforeAll( () => {
		window.wcpaySettings = mockWcpaySettings as typeof wcpaySettings;
	} );

	afterAll( () => {
		window.wcpaySettings = originalWcpaySettings;
	} );

	describe( 'with string input', () => {
		it( 'should format using default WordPress settings', () => {
			const dateTime = '2024-10-23 15:28:26';
			const formatted = formatUserDateTime( dateTime, {
				includeTime: true,
			} );

			expect( formatted ).toBe( '2024-10-23 / 15:28' );
		} );

		it( 'should use custom format if provided', () => {
			const dateTime = '2024-10-23 15:28:26';
			const options = { customFormat: 'd-m-Y H:i:s' };
			const formatted = formatUserDateTime( dateTime, options );

			expect( formatted ).toBe( '23-10-2024 15:28:26' );
		} );

		it( 'should exclude time if includeTime is set to false', () => {
			const dateTime = '2024-10-23 15:28:26';
			const formatted = formatUserDateTime( dateTime );

			expect( formatted ).toBe( '2024-10-23' );
		} );

		it( 'should use custom separator when provided', () => {
			const dateTime = '2024-10-23 15:28:26';
			const options = { separator: ' - ', includeTime: true };
			const formatted = formatUserDateTime( dateTime, options );

			expect( formatted ).toBe( '2024-10-23 - 15:28' );
		} );

		it( 'should handle GMT/UTC setting correctly when useGmt is true', () => {
			const dateTime = '2024-10-23 15:28:26Z';
			const options = { useGmt: true, includeTime: true };
			const formatted = formatUserDateTime( dateTime, options );

			// Expect UTC-based output (no timezone adjustment)
			expect( formatted ).toBe( '2024-10-23 / 15:28' );
		} );

		it( 'should support escaping characters with custom format', () => {
			const dateTime = '2024-10-23 15:28:26';
			const options = { customFormat: "'l \\t\\h\\e jS'" };
			const formatted = formatUserDateTime( dateTime, options );
			expect( formatted ).toBe( "'Wednesday the 23rd'" );
		} );

		it( 'should output unrecognized characters as-is', () => {
			const dateTime = '2024-10-23 15:28:26';
			const options = { customFormat: '-' };
			const formatted = formatUserDateTime( dateTime, options );
			expect( formatted ).toBe( '-' );
		} );
	} );

	describe( 'with Date object input', () => {
		it( 'should format using default WordPress settings', () => {
			const dateTime = new Date( Date.UTC( 2024, 9, 23, 15, 28, 26 ) );
			const formatted = formatUserDateTime( dateTime, {
				useGmt: true,
				includeTime: true,
			} );

			expect( formatted ).toBe( '2024-10-23 / 15:28' );
		} );

		it( 'should use custom format if provided', () => {
			const dateTime = new Date( Date.UTC( 2024, 9, 23, 15, 28, 26 ) );
			const options = { customFormat: 'd-m-Y H:i:s', useGmt: true };
			const formatted = formatUserDateTime( dateTime, options );

			expect( formatted ).toBe( '23-10-2024 15:28:26' );
		} );

		it( 'should exclude time if includeTime is set to false', () => {
			const dateTime = new Date( Date.UTC( 2024, 9, 23, 15, 28, 26 ) );
			const options = { useGmt: true };
			const formatted = formatUserDateTime( dateTime, options );

			expect( formatted ).toBe( '2024-10-23' );
		} );

		it( 'should handle GMT/UTC setting correctly', () => {
			const dateTime = new Date( 2024, 9, 23, 15, 28, 26 ); // Local time (non-UTC)
			const formatted = formatUserDateTime( dateTime, {
				includeTime: true,
			} );

			const expectedFormat = dateI18n(
				`${ mockWcpaySettings.dateFormat } / ${ mockWcpaySettings.timeFormat }`,
				dateTime,
				false
			);

			expect( formatted ).toBe( expectedFormat );
		} );

		it( 'should use custom separator when provided', () => {
			const dateTime = new Date( Date.UTC( 2024, 9, 23, 15, 28, 26 ) );
			const options = {
				separator: ' - ',
				useGmt: true,
				includeTime: true,
			};
			const formatted = formatUserDateTime( dateTime, options );

			expect( formatted ).toBe( '2024-10-23 - 15:28' );
		} );
	} );
} );
