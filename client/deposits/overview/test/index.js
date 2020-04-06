/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
import { merge } from 'lodash';
import momentLib from 'moment';

/**
 * Internal dependencies
 */
import DepositsOverview from '../';
import { useDepositsOverview } from 'data';

jest.mock( 'data', () => ( { useDepositsOverview: jest.fn() } ) );
const mockUseDepositsOverview = ( overview, isLoading = false ) =>
	useDepositsOverview.mockReturnValue( {
		overview: overview,
		isLoading: isLoading,
	} );

const getMockedOverview = ( additionalData ) => merge(
	{
		// eslint-disable-next-line camelcase
		last_deposit: {
			amount: 6574,
			date: new Date( '2019-04-18' ).getTime(),
		},
		// eslint-disable-next-line camelcase
		next_deposit: {
			amount: 1522,
			date: new Date( '2019-04-22' ).getTime(),
		},
		balance: {
			pending: {
				amount: 4866,
				// eslint-disable-next-line camelcase
				deposits_count: 2,
			},
			available: {
				amount: 0,
			},
		},
		// eslint-disable-next-line camelcase
		deposit_schedule: {
			interval: 'daily',
		},
	},
	additionalData
);

describe( 'Deposits Overview', () => {
	afterEach( () => {
		jest.clearAllMocks();
	} );

	test( 'renders correctly', () => {
		mockUseDepositsOverview( getMockedOverview() );
		const { container } = render( <DepositsOverview /> );
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders deposit schedule for manual interval', () => {
		const depositSchedule = getDepositSchedule( { interval: 'manual' } );
		expect( depositSchedule ).toEqual( 'Deposit Schedule: Manual' );
	} );

	test( 'renders deposit schedule for daily interval', () => {
		const depositSchedule = getDepositSchedule( { interval: 'daily' } );
		expect( depositSchedule ).toEqual( 'Deposit Schedule: Automatic, every business day' );
	} );

	test( 'renders deposit schedule for weekly interval', () => {
		// eslint-disable-next-line camelcase
		const depositSchedule = getDepositSchedule( { interval: 'weekly', weekly_anchor: 'monday' } );
		expect( depositSchedule ).toEqual( 'Deposit Schedule: Automatic, every week on Monday' );
	} );

	test( 'renders deposit schedule for monthly interval', () => {
		// eslint-disable-next-line camelcase
		const depositSchedule = getDepositSchedule( { interval: 'monthly', monthly_anchor: 26 } );
		expect( depositSchedule ).toEqual( 'Deposit Schedule: Automatic, every month on the 26th' );
	} );

	test( 'renders weekly anchor for non en locales', () => {
		momentLib.locale( 'de' );
		// eslint-disable-next-line camelcase
		const depositSchedule = getDepositSchedule( { interval: 'weekly', weekly_anchor: 'tuesday' } );
		// without resetting the locale to en the anchor monday would become Sonntag, instead of Dienstag
		expect( depositSchedule ).toEqual( 'Deposit Schedule: Automatic, every week on Dienstag' );
		// the default locale should not have changed
		expect( momentLib.locale() ).toEqual( 'de' );
	} );

	test( 'renders in transit label to in_transit next deposits', () => {
		// eslint-disable-next-line camelcase
		const overview = getMockedOverview( { next_deposit: { status: 'in_transit' } } );
		mockUseDepositsOverview( overview );
		const { getByText } = render( <DepositsOverview /> );
		const nextDepositDate = getByText( 'Est. Apr 22, 2019 - In transit' );
		expect( nextDepositDate.parentElement.textContent ).toContain( 'Next deposit' );
	} );

	test( 'renders singular number of pending deposits', () => {
		// eslint-disable-next-line camelcase
		const overview = getMockedOverview( { balance: { pending: { deposits_count: 1 } } } );
		mockUseDepositsOverview( overview );
		const { getByText } = render( <DepositsOverview /> );
		const pendingDeposits = getByText( '1 deposit' );
		expect( pendingDeposits.parentElement.textContent ).toContain( 'Pending balance' );
	} );

	test( 'renders plural number of pending deposits', () => {
		// eslint-disable-next-line camelcase
		const overview = getMockedOverview( { balance: { pending: { deposits_count: 2 } } } );
		mockUseDepositsOverview( overview );
		const { getByText } = render( <DepositsOverview /> );
		const pendingDeposits = getByText( '2 deposits' );
		expect( pendingDeposits.parentElement.textContent ).toContain( 'Pending balance' );
	} );
} );

function getDepositSchedule( schedule ) {
	// eslint-disable-next-line camelcase
	const overview = getMockedOverview( { deposit_schedule: schedule } );
	mockUseDepositsOverview( overview );
	const { getByText } = render( <DepositsOverview /> );
	const depositSchedule = getByText( 'Deposit Schedule:' );
	return depositSchedule.parentElement.textContent;
}
