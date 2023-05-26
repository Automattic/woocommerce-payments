/**
 * External dependencies
 */
import moment from 'moment';

/**
 * Internal dependencies
 */
import type { CachedDispute } from 'wcpay/types/disputes';
import { getDisputesNoticeString } from '../utils';

declare const global: {
	wcpaySettings: {
		zeroDecimalCurrencies: string[];
		connect: {
			country: string;
		};
		currentUserEmail: string;
		currencyData: {
			[ key: string ]: {
				code: string;
				symbol: string;
				symbolPosition: string;
				thousandSeparator: string;
				decimalSeparator: string;
				precision: number;
			};
		};
	};
};

const addHoursToCurrentDate = ( hours: number ): string => {
	const now = moment();
	const duration = moment.duration( hours, 'hours' );
	const futureDate = now.add( duration );
	const dateString = futureDate.format( 'YYYY-MM-DD HH:mm:ss' );
	return dateString;
};

const mockDisputes: CachedDispute[] = [
	{
		wcpay_disputes_cache_id: 4,
		stripe_account_id: 'acct_test',
		dispute_id: 'dp_asdfghjkl',
		charge_id: 'ch_mock',
		amount: 1234,
		currency: 'usd',
		reason: 'fraudulent',
		source: 'visa',
		order_number: 1,
		customer_name: 'Mock customer',
		customer_email: 'mock@customer.net',
		customer_country: 'US',
		status: 'needs_response',
		created: '2023-01-01 23:59:59',
		due_by: addHoursToCurrentDate( 20 ), // Due within 24h of current time.
		order: {
			number: 1,
			customer_url: 'https://shop.local',
			url: 'http://test.local/order/1',
		},
	},
	{
		wcpay_disputes_cache_id: 9,
		stripe_account_id: 'acct_test',
		dispute_id: 'dp_asd654l',
		charge_id: 'ch_mock',
		amount: 1000,
		currency: 'usd',
		reason: 'product_unacceptable',
		source: 'visa',
		order_number: 2,
		customer_name: 'Mock customer',
		customer_email: 'mock@customer.net',
		customer_country: 'US',
		status: 'warning_needs_response',
		created: '2023-01-01 23:59:59',
		due_by: addHoursToCurrentDate( 48 ), // Due > 24h of current time.
		order: {
			number: 2,
			customer_url: 'https://shop.local',
			url: 'http://test.local/order/2',
		},
	},
	{
		wcpay_disputes_cache_id: 18,
		stripe_account_id: 'acct_test',
		dispute_id: 'dp_asd654l',
		charge_id: 'ch_mock',
		amount: 2000,
		currency: 'chf',
		reason: 'bank_cannot_process',
		source: 'visa',
		order_number: 3,
		customer_name: 'Mock customer',
		customer_email: 'mock@customer.net',
		customer_country: 'US',
		status: 'needs_response',
		created: '2023-01-01 23:59:59',
		due_by: addHoursToCurrentDate( 72 ), // Due > 24h of current time.
		order: {
			number: 3,
			customer_url: 'https://shop.local',
			url: 'http://test.local/order/3',
		},
	},
];

describe( 'getDisputesNoticeString', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			zeroDecimalCurrencies: [],
			connect: {
				country: 'US',
			},
			currentUserEmail: 'mock@example.com',
			currencyData: {
				US: {
					code: 'USD',
					symbol: '$',
					symbolPosition: 'left',
					thousandSeparator: ',',
					decimalSeparator: '.',
					precision: 2,
				},
			},
		};
	} );

	it( 'returns null if passed an empty array', () => {
		expect( getDisputesNoticeString( [] ) ).toBe( null );
	} );

	it( 'returns the correct string for a single dispute', () => {
		expect( getDisputesNoticeString( [ mockDisputes[ 1 ] ] ) ).toEqual(
			`Respond to a dispute for $10.00`
		);
	} );

	it( 'returns the correct string for a single dispute due within 24h', () => {
		expect( getDisputesNoticeString( [ mockDisputes[ 0 ] ] ) ).toEqual(
			`Respond to a dispute for $12.34 – last day`
		);
	} );

	it( 'returns the correct string for multiple disputes', () => {
		expect( getDisputesNoticeString( mockDisputes.slice( 0, 2 ) ) ).toEqual(
			`Respond to 2 active disputes for a total of $22.34`
		);
	} );

	it( 'returns the correct string for multiple disputes with multiple currencies', () => {
		expect( getDisputesNoticeString( mockDisputes ) ).toEqual(
			// eslint-disable-next-line no-irregular-whitespace -- needed for test since this character is being returned by the currency formatter
			`Respond to 3 active disputes for a total of $22.34, CHF 20.00`
		);
	} );
} );
