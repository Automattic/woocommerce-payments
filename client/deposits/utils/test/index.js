/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import momentLib from 'moment';

/**
 * Internal dependencies
 */
import {
	getDepositDate,
	getBalanceDepositCount,
	getNextDepositLabelFormatted,
	getDepositScheduleDescriptor,
	getDepositMonthlyAnchorLabel,
} from './..';

function getDepositSchedule(
	schedule,
	includeLastDeposit = true,
	disabled = false,
	blocked = false
) {
	const lastDeposit = {
		id: 'last_deposit',
		amount: 6574,
		date: new Date( '2019-04-18' ).getTime(),
	};

	const descriptor = getDepositScheduleDescriptor( {
		account: {
			deposits_schedule: schedule,
			deposits_disabled: disabled,
			deposits_blocked: blocked,
		},
		last_deposit: includeLastDeposit ? lastDeposit : null,
	} );

	if ( 'string' === typeof descriptor ) {
		return descriptor;
	}

	const { container } = render( descriptor );
	return container.textContent;
}

describe( 'Deposits Overview Utils / getDepositScheduleDescriptor', () => {
	test( 'renders temporarily suspended notice for accounts with disabled deposits', () => {
		const depositSchedule = getDepositSchedule(
			{ interval: 'daily' },
			true,
			true
		);
		expect( depositSchedule ).toEqual(
			'Deposits temporarily suspended (learn more).'
		);
	} );

	test( 'renders temporarily suspended notice for accounts with deposits blocked', () => {
		const depositSchedule = getDepositSchedule(
			{ interval: 'daily' },
			true,
			false,
			true // blocked
		);
		expect( depositSchedule ).toEqual(
			'Deposits temporarily suspended (learn more).'
		);
	} );

	test( 'renders deposit schedule for manual interval', () => {
		const depositSchedule = getDepositSchedule( { interval: 'manual' } );
		expect( depositSchedule ).toEqual( 'Deposits set to manual.' );
	} );

	test( 'renders deposit schedule for daily interval', () => {
		const depositSchedule = getDepositSchedule( { interval: 'daily' } );
		expect( depositSchedule ).toEqual( 'Deposits set to daily.' );
	} );

	test( 'renders deposit schedule for weekly interval', () => {
		const depositSchedule = getDepositSchedule( {
			interval: 'weekly',
			weekly_anchor: 'monday',
		} );
		expect( depositSchedule ).toEqual( 'Deposits set to every Monday.' );
	} );

	test( 'renders deposit schedule for monthly interval', () => {
		const depositSchedule = getDepositSchedule( {
			interval: 'monthly',
			monthly_anchor: 26,
		} );
		expect( depositSchedule ).toEqual(
			'Deposits set to monthly on the 26th.'
		);
	} );

	test( 'renders deposit schedule for monthly interval', () => {
		const depositSchedule = getDepositSchedule( {
			interval: 'monthly',
			monthly_anchor: 31,
		} );
		expect( depositSchedule ).toEqual(
			'Deposits set to monthly on the last day of the month.'
		);
	} );

	test( 'renders weekly anchor for non en locales', () => {
		momentLib.locale( 'de' );
		const depositSchedule = getDepositSchedule( {
			interval: 'weekly',
			weekly_anchor: 'tuesday',
		} );
		// without resetting the locale to en the anchor monday would become Sonntag, instead of Dienstag
		expect( depositSchedule ).toEqual( 'Deposits set to every Dienstag.' );
		// the default locale should not have changed
		expect( momentLib.locale() ).toEqual( 'de' );
	} );

	test( 'renders deposit delay notice prior to first deposit', () => {
		const depositSchedule = getDepositSchedule(
			{ interval: 'daily' },
			false
		);
		expect( depositSchedule ).toEqual(
			'Deposits set to daily. Your first deposit is held for seven days (learn more).'
		);
	} );
} );

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

describe( 'Deposits Overview Utils / getBalanceDepositCount', () => {
	test( 'formats the count with a single deposit', () => {
		const balance = { deposits_count: 1 };
		expect( getBalanceDepositCount( balance ) ).toEqual( '1 deposit' );
	} );

	test( 'formats the count with multiple deposits', () => {
		const balance = { deposits_count: 3 };
		expect( getBalanceDepositCount( balance ) ).toEqual( '3 deposits' );
	} );
} );

describe( 'Deposits Overview Utils / getNextDepositLabelFormatted', () => {
	test( 'returns a display value without a deposit', () => {
		expect( getNextDepositLabelFormatted() ).toEqual( '—' );
	} );

	test( 'returns a well-formated estimation string', () => {
		const deposit = {
			date: new Date( '2019-04-18' ).getTime(),
		};

		expect( getNextDepositLabelFormatted( deposit ) ).toEqual(
			'Est. Apr 18, 2019'
		);
	} );

	test( 'includes the in-transit status', () => {
		const deposit = {
			date: new Date( '2019-04-18' ).getTime(),
			status: 'in_transit',
		};

		expect( getNextDepositLabelFormatted( deposit ) ).toEqual(
			'Est. Apr 18, 2019 - In transit'
		);
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
