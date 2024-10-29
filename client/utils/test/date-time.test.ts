/**
 * External dependencies
 */
import { dateI18n } from '@wordpress/date';
/**
 * Internal dependencies
 */
import { formatDateTime } from '../date-time';

jest.mock( '@wordpress/date', () => ( {
	dateI18n: jest.fn(),
} ) );

describe( 'formatDateTime', () => {
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

	it( 'should format date and time using default WordPress settings', () => {
		const dateTime = '2024-10-23 15:28:26';
		formatDateTime( dateTime );

		expect( dateI18n ).toHaveBeenCalledWith(
			`${ mockWcpaySettings.dateFormat } / ${ mockWcpaySettings.timeFormat }`,
			dateTime,
			true
		);
	} );

	it( 'should use custom format if provided', () => {
		const dateTime = '2024-10-23 15:28:26';
		const options = { customFormat: 'd-m-Y H:i:s' };
		formatDateTime( dateTime, options );

		expect( dateI18n ).toHaveBeenCalledWith(
			'd-m-Y H:i:s',
			dateTime,
			true
		);
	} );

	it( 'should exclude time if includeTime is set to false', () => {
		const dateTime = '2024-10-23 15:28:26';
		const options = { includeTime: false };
		formatDateTime( dateTime, options );

		expect( dateI18n ).toHaveBeenCalledWith(
			mockWcpaySettings.dateFormat,
			dateTime,
			true
		);
	} );

	it( 'should handle GMT/UTC setting correctly', () => {
		const dateTime = '2024-10-23 15:28:26';
		const options = { useGmt: false };
		formatDateTime( dateTime, options );

		expect( dateI18n ).toHaveBeenCalledWith(
			`${ mockWcpaySettings.dateFormat } / ${ mockWcpaySettings.timeFormat }`,
			dateTime,
			false
		);
	} );

	it( 'should use custom separator when provided', () => {
		const dateTime = '2024-10-23 15:28:26';
		const options = { separator: ' - ' };
		formatDateTime( dateTime, options );

		expect( dateI18n ).toHaveBeenCalledWith(
			`${ mockWcpaySettings.dateFormat } - ${ mockWcpaySettings.timeFormat }`,
			dateTime,
			true
		);
	} );
} );
