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
		created: '2019-11-01 23:59:59',
		due_by: '2019-11-08 02:46:00',
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
		created: '2019-11-01 23:59:59',
		due_by: '2019-11-08 02:46:00',
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
		currency: 'usd',
		reason: 'bank_cannot_process',
		source: 'visa',
		order_number: 3,
		customer_name: 'Mock customer',
		customer_email: 'mock@customer.net',
		customer_country: 'US',
		status: 'needs_response',
		created: '2019-11-01 23:59:59',
		due_by: '2019-11-08 02:46:00',
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

	it( 'returns the correct string for a single dispute', () => {
		expect(
			getDisputesNoticeString( {
				activeDisputes: [ mockDisputes[ 0 ] ],
			} )
		).toEqual( `Respond to a dispute for $12.34` );
	} );

	it( 'returns the correct string for multiple disputes', () => {
		expect(
			getDisputesNoticeString( {
				activeDisputes: mockDisputes,
			} )
		).toEqual( `Respond to 3 active disputes for $42.34` );
	} );
} );
