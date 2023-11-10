/**
 * External dependencies
 */
import momentLib from 'moment';

/**
 * Internal dependencies
 */
import {
	getDepositDate,
	getDepositMonthlyAnchorLabel,
	getNextDepositDate,
} from '../';
import type * as AccountOverview from 'wcpay/types/account-overview';

describe( 'Deposits Overview Utils / getDepositDate', () => {
	test( 'returns a display value without a deposit', () => {
		expect( getDepositDate() ).toEqual( '—' );
	} );

	test( 'Returns a well-formated date', () => {
		const deposit = {
			date: new Date( '2019-04-18' ).getTime(),
		};

		expect( getDepositDate( deposit ) ).toEqual( 'April 18, 2019' );
	} );
} );

describe( 'Deposits Overview Utils / getDepositMonthlyAnchorLabel', () => {
	const expectedLabels = [
		{ label: '1st', value: 1 },
		{ label: '2nd', value: 2 },
		{ label: '3rd', value: 3 },
		{ label: '4th', value: 4 },
		{ label: '5th', value: 5 },
		{ label: '6th', value: 6 },
		{ label: '7th', value: 7 },
		{ label: '8th', value: 8 },
		{ label: '9th', value: 9 },
		{ label: '10th', value: 10 },
		{ label: '11th', value: 11 },
		{ label: '12th', value: 12 },
		{ label: '13th', value: 13 },
		{ label: '14th', value: 14 },
		{ label: '15th', value: 15 },
		{ label: '16th', value: 16 },
		{ label: '17th', value: 17 },
		{ label: '18th', value: 18 },
		{ label: '19th', value: 19 },
		{ label: '20th', value: 20 },
		{ label: '21st', value: 21 },
		{ label: '22nd', value: 22 },
		{ label: '23rd', value: 23 },
		{ label: '24th', value: 24 },
		{ label: '25th', value: 25 },
		{ label: '26th', value: 26 },
		{ label: '27th', value: 27 },
		{ label: '28th', value: 28 },
		{
			label: 'Last day of the month',
			value: 31,
		},
	];

	test( 'returns the expected label', () => {
		momentLib.locale( 'en' );
		expectedLabels.forEach( ( expectedLabel ) => {
			expect(
				getDepositMonthlyAnchorLabel( {
					monthlyAnchor: expectedLabel.value,
				} )
			).toEqual( expectedLabel.label );
		} );
	} );

	test( 'returns a lowercase value with false capitalize argument', () => {
		momentLib.locale( 'en' );
		expect(
			getDepositMonthlyAnchorLabel( {
				monthlyAnchor: 31,
				capitalize: false,
			} )
		).toEqual( 'last day of the month' );
	} );
} );

type NextDepositDateTestCase = [
	string,
	Partial< AccountOverview.Account[ 'deposits_schedule' ] >,
	string
];

describe( 'Deposits Overview Utils / getNextDepositDate', () => {
	const currentTimezone = momentLib.tz.guess();

	beforeEach( () => {
		// Set the local timezone to UTC for the tests.
		momentLib.tz.setDefault( 'UTC' );
	} );

	afterEach( () => {
		// Reset the local timezone to the original value.
		momentLib.tz.setDefault( currentTimezone );
	} );

	const testCases: NextDepositDateTestCase[] = [
		[ '2023-01-01', { interval: 'manual' }, '—' ],
		[ '2023-01-01', { interval: 'daily' }, 'January 2nd, 2023' ],
		[ '2023-01-06', { interval: 'daily' }, 'January 7th, 2023' ], // weekends are acceptable deposit dates
		[
			'2023-01-01',
			{ interval: 'weekly', weekly_anchor: 'tuesday' },
			'January 3rd, 2023',
		],
		[
			'2023-01-03',
			{ interval: 'weekly', weekly_anchor: 'tuesday' },
			'January 10th, 2023',
		], // when the weekly anchor is the same as the current day, it should add a week
		[
			'2023-01-01',
			{ interval: 'monthly', monthly_anchor: 1 },
			'February 1st, 2023',
		], // when the monthly anchor is the same as the current day, it should add a month
		[
			'2023-01-01',
			{ interval: 'monthly', monthly_anchor: 2 },
			'January 2nd, 2023',
		],
		[
			'2023-01-01',
			{ interval: 'monthly', monthly_anchor: 30 },
			'January 30th, 2023',
		], // Using the 30th as an anchor should apply normally on months with >30 days
		[
			'2023-02-01',
			{ interval: 'monthly', monthly_anchor: 30 },
			'February 28th, 2023',
		], // When the anchor >= 29 and the month doesn't have that many days, the last day of month is used instead
	];

	test.each( testCases )(
		'given input date %p and depositSchedule %p, returns %p',
		( inputDate, schedule, expectedOutput ) => {
			Date.now = jest.fn( () => new Date( inputDate ).getTime() );

			const result = getNextDepositDate( {
				delay_days: 0, // default value
				interval: schedule.interval || 'daily', // default value
				weekly_anchor: schedule.weekly_anchor || 'monday', // default value
				monthly_anchor: schedule.monthly_anchor || 1, // default value
			} );
			expect( result ).toBe( expectedOutput );

			// Reset Date.now.
			Date.now = () => new Date().getTime();
		}
	);
} );
