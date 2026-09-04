/** @format */

/**
 * External dependencies
 */

import moment from 'moment';

/**
 * Internal dependencies
 */
import { getTasks, taskSort } from '../tasks';
import { getDisputeResolutionTask } from '../tasks/dispute-task';
import { getAdminUrl } from 'wcpay/utils';

const mockHistoryPush = jest.fn();
jest.mock( '@woocommerce/navigation', () => ( {
	getHistory: () => ( {
		push: mockHistoryPush,
	} ),
} ) );

const mockActiveDisputes = [
	{
		wcpay_disputes_cache_id: 4,
		stripe_account_id: 'acct_test',
		dispute_id: 'dp_1',
		charge_id: 'ch_mock',
		amount: 1000,
		currency: 'usd',
		reason: 'fraudulent',
		source: 'visa',
		order_number: 1,
		customer_name: 'Mock customer',
		customer_email: 'mock@customer.net',
		customer_country: 'US',
		status: 'needs_response',
		created: '2019-11-01 23:59:59',
		due_by: '2023-02-01 23:59:59',
		order: {
			number: '1',
			customer_url: 'https://shop.local',
			url: 'http://test.local/order/1',
		},
	},
	{
		wcpay_disputes_cache_id: 4,
		stripe_account_id: 'acct_test',
		dispute_id: 'dp_2',
		charge_id: 'ch_mock',
		amount: 1000,
		currency: 'usd',
		reason: 'fraudulent',
		source: 'visa',
		order_number: 2,
		customer_name: 'Mock customer',
		customer_email: 'mock@customer.net',
		customer_country: 'US',
		status: 'needs_response',
		created: '2019-11-01 23:59:59',
		due_by: '2023-02-03 23:59:59',
		order: {
			number: '1',
			customer_url: 'https://shop.local',
			url: 'http://test.local/order/1',
		},
	},
	{
		wcpay_disputes_cache_id: 4,
		stripe_account_id: 'acct_test',
		dispute_id: 'dp_3',
		charge_id: 'ch_mock',
		amount: 1000,
		currency: 'eur',
		reason: 'fraudulent',
		source: 'visa',
		order_number: 2,
		customer_name: 'Mock customer',
		customer_email: 'mock@customer.net',
		customer_country: 'US',
		status: 'needs_response',
		created: '2019-11-01 23:59:59',
		due_by: '2023-02-07 23:59:59',
		order: {
			number: '1',
			customer_url: 'https://shop.local',
			url: 'http://test.local/order/1',
		},
	},
	{
		wcpay_disputes_cache_id: 1234,
		stripe_account_id: 'acct_test',
		dispute_id: 'dp_1',
		charge_id: 'ch_mock',
		amount: 1000,
		currency: 'usd',
		reason: 'fraudulent',
		source: 'visa',
		order_number: 1,
		customer_name: 'Mock customer',
		customer_email: 'mock@customer.net',
		customer_country: 'US',
		status: 'needs_response',
		created: '2019-11-01 23:59:59',
		due_by: '', // Adding this to cover an edge case where due_by is an empty string. This should be ignored by the task list.
		order: {
			number: '1',
			customer_url: 'https://shop.local',
			url: 'http://test.local/order/1',
		},
	},
];

describe( 'getTasks()', () => {
	// Get current timezone
	const currentTimezone = moment.tz.guess();

	beforeEach( () => {
		// set local timezone to EST (not daylight savings time)
		// Note Etc/GMT+5 === UTC-5
		moment.tz.setDefault( 'Etc/GMT+5' );
		// mock Date.now that moment library uses to get current date for testing purposes
		Date.now = jest.fn( () => new Date( '2023-02-01T08:00:00.000Z' ) );

		global.wcpaySettings = {
			accountStatus: {
				status: 'restricted_soon',
				currentDeadline: 1620857083,
				pastDue: false,
				accountLink: 'http://example.com',
				detailsSubmitted: true,
			},
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
				FR: {
					code: 'EUR',
					symbol: '€',
					symbolPosition: 'left',
					thousandSeparator: ',',
					decimalSeparator: '.',
					precision: 2,
				},
				GB: {
					code: 'GBP',
					symbol: '£',
					symbolPosition: 'left',
					thousandSeparator: ',',
					decimalSeparator: '.',
					precision: 2,
				},
			},
			dateFormat: 'M j, Y',
		};
	} );
	afterEach( () => {
		// roll it back
		Date.now = () => new Date();
		moment.tz.setDefault( currentTimezone );
	} );
	it( 'should include business details when flag is set', () => {
		const actual = getTasks( {
			showUpdateDetailsTask: true,
		} );

		expect( actual ).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( {
					key: 'update-business-details',
					completed: false,
				} ),
			] )
		);
	} );

	it( 'should include complete setup when flag is set and details submitted as false', () => {
		global.wcpaySettings.accountStatus.status = 'restricted';
		global.wcpaySettings.accountStatus.detailsSubmitted = false;
		const actual = getTasks( {
			showUpdateDetailsTask: true,
		} );

		expect( actual ).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( {
					key: 'complete-setup',
					completed: false,
					title: 'Finish setting up WooPayments',
					actionLabel: 'Finish setup',
				} ),
			] )
		);
	} );

	it( 'should omit business details when flag is not set', () => {
		global.wcpaySettings.accountStatus.status = 'restricted';
		global.wcpaySettings.accountStatus.pastDue = true;

		const actual = getTasks( {
			showUpdateDetailsTask: false,
		} );

		expect( actual ).toEqual(
			expect.not.arrayContaining( [
				expect.objectContaining( {
					key: 'update-business-details',
				} ),
			] )
		);
	} );

	it( 'handles when account is complete', () => {
		global.wcpaySettings.accountStatus.status = 'complete';
		global.wcpaySettings.accountStatus.currentDeadline = 0;

		const actual = getTasks( {
			showUpdateDetailsTask: true,
		} );

		expect( actual ).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( {
					key: 'update-business-details',
					completed: true,
				} ),
			] )
		);
	} );

	it( 'handles when account is enabled', () => {
		global.wcpaySettings.accountStatus.status = 'enabled';
		global.wcpaySettings.accountStatus.currentDeadline = 0;

		const actual = getTasks( {
			showUpdateDetailsTask: true,
		} );

		expect( actual ).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( {
					key: 'update-business-details',
					completed: true,
				} ),
			] )
		);
	} );

	it( 'adds WPCOM user reconnect task when the url is specified', () => {
		global.wcpaySettings.accountStatus.status = 'complete';

		const actual = getTasks( {
			wpcomReconnectUrl: 'http://example.com',
		} );

		expect( actual ).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( {
					key: 'reconnect-wpcom-user',
					completed: false,
				} ),
			] )
		);
	} );

	it( 'should omit the WPCOM user reconnect task when the url is not specified', () => {
		global.wcpaySettings.accountStatus.status = 'complete';

		const actual = getTasks( {
			wpcomReconnectUrl: null,
		} );

		expect( actual ).toEqual(
			expect.not.arrayContaining( [
				expect.objectContaining( {
					key: 'reconnect-wpcom-user',
				} ),
			] )
		);
	} );

	it( 'returns the expected keys when account is not complete and needs reconnection', () => {
		const tasks = getTasks( {
			showUpdateDetailsTask: true,
			wpcomReconnectUrl: 'http://example.com',
		} );

		expect( tasks ).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( { key: 'update-business-details' } ),
				expect.objectContaining( { key: 'reconnect-wpcom-user' } ),
			] )
		);
	} );

	it( 'returns the expected keys when the account is not onboarded', () => {
		global.wcSettings.accountStatus = {
			error: true,
		};

		const tasks = getTasks( {
			showUpdateDetailsTask: true,
			wpcomReconnectUrl: 'http://example.com',
		} );

		expect( tasks ).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( { key: 'reconnect-wpcom-user' } ),
			] )
		);
	} );

	it( 'should not include the dispute resolution task without a summary', () => {
		const actual = getTasks( {} );

		expect( actual ).toEqual( [] );
	} );

	it( 'does not include the dispute task while its summary is loading', () => {
		const actual = getTasks( {
			activeDisputesSummary: {
				count: 1,
				amount_by_currency: { usd: 1000 },
				earliest_due_by: '2023-02-01 23:59:59',
			},
			activeDisputesSummaryIsLoading: true,
		} );

		expect( actual ).toEqual( [] );
	} );

	it( 'should not include the dispute resolution task if dispute due_by > 7 days', () => {
		// Set Date.now to - 7 days to reduce urgency of disputes.
		Date.now = jest.fn( () => new Date( '2023-01-24T08:00:00.000Z' ) );
		const actual = getTasks( {
			activeDisputesSummary: {
				count: 3,
				amount_by_currency: { usd: 2000, eur: 1000 },
				earliest_due_by: '2023-02-01 23:59:59',
			},
		} );

		expect( actual ).toEqual( [] );
	} );

	it( 'should include the dispute resolution task with 1 urgent dispute', () => {
		const actual = getTasks( {
			activeDispute: mockActiveDisputes[ 0 ],
			activeDisputesSummary: {
				count: 1,
				amount_by_currency: { usd: 1000 },
				earliest_due_by: '2023-02-01 23:59:59',
			},
		} );

		expect( actual ).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( {
					key: 'dispute-resolution-task-dp_1',
					completed: false,
					level: 1,
					title: 'Respond to a dispute for $10.00 – Last day',
					content: 'Respond today by 6:59 PM', // shown in local timezone.
					actionLabel: 'Respond now',
				} ),
			] )
		);
	} );

	it( 'should include the dispute resolution task', () => {
		// Set Date.now to - 5 days to reduce urgency of dispute.
		Date.now = jest.fn( () => new Date( '2023-01-27T08:00:00.000Z' ) );
		const actual = getTasks( {
			activeDispute: mockActiveDisputes[ 0 ],
			activeDisputesSummary: {
				count: 1,
				amount_by_currency: { usd: 1000 },
				earliest_due_by: '2023-02-01 23:59:59',
			},
		} );

		expect( actual ).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( {
					key: 'dispute-resolution-task-dp_1',
					completed: false,
					level: 1,
					title: 'Respond to a dispute for $10.00',
					content: 'By Feb 1, 2023 – 6 days left to respond',
					actionLabel: 'Respond now',
				} ),
			] )
		);
	} );

	it( 'should include the dispute resolution task with multiple disputes from multiple currencies and 1 urgent dispute', () => {
		const actual = getTasks( {
			activeDisputesSummary: {
				count: 3,
				amount_by_currency: { usd: 2000, eur: 1000 },
				earliest_due_by: '2023-02-01 23:59:59',
			},
		} );

		expect( actual ).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( {
					key: 'dispute-resolution-task-summary',
					completed: false,
					level: 1,
					title: 'Respond to 3 active disputes for totals of €10.00 EUR and $20.00 USD',
					content: 'Respond today by 6:59 PM',
					actionLabel: 'See disputes',
				} ),
			] )
		);
	} );

	it( 'should include the dispute resolution task with multiple disputes from a single currency', () => {
		const actual = getTasks( {
			accountStatus: {
				status: 'restricted_soon',
				currentDeadline: 1620857083,
				pastDue: false,
				accountLink: 'http://example.com',
			},
			activeDisputesSummary: {
				count: 2,
				amount_by_currency: { usd: 2000 },
				earliest_due_by: '2023-02-01 23:59:59',
			},
		} );

		expect( actual ).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( {
					key: 'dispute-resolution-task-summary',
					completed: false,
					level: 1,
					title: 'Respond to 2 active disputes for a total of $20.00',
					content: 'Respond today by 6:59 PM',
					actionLabel: 'See disputes',
				} ),
			] )
		);
	} );

	it( 'should include the dispute resolution task with multiple disputes', () => {
		// Set Date.now to - 5 days to reduce urgency of disputes.
		Date.now = jest.fn( () => new Date( '2023-01-27T08:00:00.000Z' ) );
		const actual = getTasks( {
			activeDisputesSummary: {
				count: 3,
				amount_by_currency: { usd: 2000, eur: 1000 },
				earliest_due_by: '2023-02-01 23:59:59',
			},
		} );

		expect( actual ).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( {
					key: 'dispute-resolution-task-summary',
					completed: false,
					level: 1,
					title: 'Respond to 3 active disputes for totals of €10.00 EUR and $20.00 USD',
					content: 'By Feb 1, 2023 – 6 days left to respond',
					actionLabel: 'See disputes',
				} ),
			] )
		);
	} );
} );

describe( 'getDisputeResolutionTask()', () => {
	const nextWeekDeadline = '2023-02-03 23:59:59';

	beforeEach( () => {
		moment.tz.setDefault( 'Etc/GMT+5' );
		Date.now = jest.fn( () => new Date( '2023-02-01T08:00:00.000Z' ) );
		mockHistoryPush.mockClear();

		global.wcpaySettings.currencyData.FR = {
			code: 'EUR',
			symbol: '€',
			symbolPosition: 'left',
			thousandSeparator: ',',
			decimalSeparator: '.',
			precision: 2,
		};
		global.wcpaySettings.currencyData.GB = {
			code: 'GBP',
			symbol: '£',
			symbolPosition: 'left',
			thousandSeparator: ',',
			decimalSeparator: '.',
			precision: 2,
		};
	} );

	it( 'uses the complete summary count and amount', () => {
		const task = getDisputeResolutionTask( {
			count: 51,
			amount_by_currency: { usd: 51000 },
			earliest_due_by: nextWeekDeadline,
		} );

		expect( task ).toEqual(
			expect.objectContaining( {
				title: 'Respond to 51 active disputes for a total of $510.00',
				content: 'By Feb 3, 2023 – 3 days left to respond',
			} )
		);
	} );

	it( 'shows two summary totals with stable ISO currency codes', () => {
		const task = getDisputeResolutionTask( {
			count: 3,
			amount_by_currency: { usd: 2000, eur: 1000 },
			earliest_due_by: nextWeekDeadline,
		} );

		expect( task?.title ).toBe(
			'Respond to 3 active disputes for totals of €10.00 EUR and $20.00 USD'
		);
	} );

	it( 'shows a currency count instead of totals for three currencies', () => {
		const task = getDisputeResolutionTask( {
			count: 3,
			amount_by_currency: { usd: 1000, eur: 1000, gbp: 1000 },
			earliest_due_by: nextWeekDeadline,
		} );

		expect( task?.title ).toBe(
			'Respond to 3 active disputes in 3 currencies'
		);
		expect( task?.title ).not.toContain( '$' );
		expect( task?.title ).not.toContain( '€' );
		expect( task?.title ).not.toContain( '£' );
	} );

	it( 'uses the summary deadline for the urgent state and subtitle', () => {
		const task = getDisputeResolutionTask( {
			count: 2,
			amount_by_currency: { usd: 2000 },
			earliest_due_by: '2023-02-01 20:00:00',
		} );

		expect( task ).toEqual(
			expect.objectContaining( {
				content: 'Respond today by 3:00 PM',
				dataAttrs: { 'data-urgent': true },
			} )
		);
	} );

	it( 'uses the dated subtitle for a deadline tomorrow with less than 24 hours remaining', () => {
		Date.now = jest.fn( () => new Date( '2023-02-01T23:00:00.000Z' ) );
		const task = getDisputeResolutionTask( {
			count: 1,
			amount_by_currency: { usd: 1000 },
			earliest_due_by: '2023-02-02 13:00:00',
		} );

		expect( task ).toEqual(
			expect.objectContaining( {
				title: 'Respond to a dispute for $10.00',
				content: 'By Feb 2, 2023 – 14 hours left to respond',
				dataAttrs: { 'data-urgent': true },
			} )
		);
	} );

	it.each( [
		[ 'a zero count', { count: 0, earliest_due_by: nextWeekDeadline } ],
		[ 'a null deadline', { count: 1, earliest_due_by: null } ],
		[
			'a past deadline',
			{ count: 1, earliest_due_by: '2023-01-31 23:59:59' },
		],
		[
			'a deadline after seven days',
			{ count: 1, earliest_due_by: '2023-02-09 23:59:59' },
		],
	] )( 'does not return a task for %s', ( unused, summary ) => {
		expect( getDisputeResolutionTask( summary ) ).toBeNull();
	} );

	it( 'treats an empty amount array as no amount', () => {
		const task = getDisputeResolutionTask( {
			count: 2,
			amount_by_currency: [],
			earliest_due_by: nextWeekDeadline,
		} );

		expect( task?.title ).toBe( 'Respond to 2 active disputes' );
	} );

	it( 'does not show an amount when the summary amount map is empty', () => {
		const task = getDisputeResolutionTask( {
			count: 3,
			amount_by_currency: {},
			earliest_due_by: nextWeekDeadline,
		} );

		expect( task?.title ).toBe( 'Respond to 3 active disputes' );
		expect( task?.title ).not.toContain( '$20.00' );
	} );

	it( 'opens the filtered dispute list for one dispute', () => {
		const task = getDisputeResolutionTask( {
			count: 1,
			amount_by_currency: { usd: 1000 },
			earliest_due_by: nextWeekDeadline,
		} );

		task?.action();

		expect( mockHistoryPush ).toHaveBeenCalledWith(
			getAdminUrl( {
				page: 'wc-admin',
				path: '/payments/disputes',
				filter: 'awaiting_response',
			} )
		);
	} );

	it( 'opens the single dispute transaction when its row is loaded', () => {
		const task = getDisputeResolutionTask(
			{
				count: 1,
				amount_by_currency: { usd: 1000 },
				earliest_due_by: nextWeekDeadline,
			},
			mockActiveDisputes[ 0 ]
		);

		task?.action();

		expect( mockHistoryPush ).toHaveBeenCalledWith(
			getAdminUrl( {
				page: 'wc-admin',
				path: '/payments/transactions/details',
				id: 'ch_mock',
			} )
		);
	} );
} );

describe( 'taskSort()', () => {
	beforeEach( () => {
		// mock Date.now that moment library uses to get current date for testing purposes
		Date.now = jest.fn( () => new Date( '2023-02-01T12:33:37.000Z' ) );

		global.wcpaySettings = {
			accountStatus: {
				status: 'restricted_soon',
				currentDeadline: 1620857083,
				pastDue: false,
				accountLink: 'http://example.com',
			},
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
	afterEach( () => {
		// roll it back
		Date.now = () => new Date();
	} );
	it( 'should sort the tasks', () => {
		const unsortedTasks = getTasks( {
			activeDisputesSummary: {
				count: mockActiveDisputes.filter(
					( dispute ) => dispute.due_by
				).length,
				amount_by_currency: { usd: 2000, eur: 1000 },
				earliest_due_by: '2023-02-01 23:59:59',
			},
		} );
		unsortedTasks.unshift( {
			key: 'test-element',
			completed: true,
			level: 3,
		} );
		expect( unsortedTasks[ 0 ] ).toEqual(
			expect.objectContaining( {
				key: 'test-element',
				completed: true,
				level: 3,
			} )
		);
		const sortedTasks = unsortedTasks.sort( taskSort );
		expect( sortedTasks[ 0 ] ).toEqual(
			expect.objectContaining( {
				key: 'dispute-resolution-task-summary',
				completed: false,
				level: 1,
			} )
		);
	} );
} );
