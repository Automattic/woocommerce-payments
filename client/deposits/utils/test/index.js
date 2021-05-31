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
} from './..';

function getDepositSchedule(
	schedule,
	includeLastDeposit = true,
	disabled = false
) {
	/* eslint-disable camelcase */
	const last_deposit = {
		id: 'last_deposit',
		amount: 6574,
		date: new Date( '2019-04-18' ).getTime(),
	};

	const descriptor = getDepositScheduleDescriptor( {
		account: {
			deposits_schedule: schedule,
			deposits_disabled: disabled,
		},
		last_deposit: includeLastDeposit ? last_deposit : null,
	} );
	/* eslint-enable camelcase */

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
			'Temporarily suspended (learn more)'
		);
	} );

	test( 'renders temporarily suspended notice for manual interval', () => {
		const depositSchedule = getDepositSchedule( { interval: 'manual' } );
		expect( depositSchedule ).toEqual(
			'Temporarily suspended (learn more)'
		);
	} );

	test( 'renders deposit schedule for daily interval', () => {
		const depositSchedule = getDepositSchedule( { interval: 'daily' } );
		expect( depositSchedule ).toEqual( 'Automatic, every business day' );
	} );

	test( 'renders deposit schedule for weekly interval', () => {
		const depositSchedule = getDepositSchedule( {
			interval: 'weekly',
			// eslint-disable-next-line camelcase
			weekly_anchor: 'monday',
		} );
		expect( depositSchedule ).toEqual( 'Automatic, every week on Monday' );
	} );

	test( 'renders deposit schedule for monthly interval', () => {
		const depositSchedule = getDepositSchedule( {
			interval: 'monthly',
			// eslint-disable-next-line camelcase
			monthly_anchor: 26,
		} );
		expect( depositSchedule ).toEqual(
			'Automatic, every month on the 26th'
		);
	} );

	test( 'renders weekly anchor for non en locales', () => {
		momentLib.locale( 'de' );
		const depositSchedule = getDepositSchedule( {
			interval: 'weekly',
			// eslint-disable-next-line camelcase
			weekly_anchor: 'tuesday',
		} );
		// without resetting the locale to en the anchor monday would become Sonntag, instead of Dienstag
		expect( depositSchedule ).toEqual(
			'Automatic, every week on Dienstag'
		);
		// the default locale should not have changed
		expect( momentLib.locale() ).toEqual( 'de' );
	} );

	test( 'renders deposit delay notice prior to first deposit', () => {
		const depositSchedule = getDepositSchedule( {}, false );
		expect( depositSchedule ).toEqual(
			'Automatic, every business day – your first deposit is held for seven days (learn more)'
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
		// eslint-disable-next-line camelcase
		const balance = { deposits_count: 1 };
		expect( getBalanceDepositCount( balance ) ).toEqual( '1 deposit' );
	} );

	test( 'formats the count with multiple deposits', () => {
		// eslint-disable-next-line camelcase
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
