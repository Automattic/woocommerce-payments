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
import { useDepositsOverview, useInstantDeposit } from 'data';

jest.mock( 'data', () => ( {
	useDepositsOverview: jest.fn(),
	useInstantDeposit: jest.fn(),
} ) );
const mockUseDepositsOverview = ( overview, isLoading = false ) =>
	useDepositsOverview.mockReturnValue( {
		overview: overview,
		isLoading: isLoading,
	} );

const getMockedOverview = ( additionalData ) =>
	merge(
		{
			// eslint-disable-next-line camelcase
			last_deposit: {
				id: 'last_deposit',
				amount: 6574,
				date: new Date( '2019-04-18' ).getTime(),
			},
			// eslint-disable-next-line camelcase
			next_deposit: {
				id: 'next_deposit',
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
			account: {
				// eslint-disable-next-line camelcase
				deposits_disabled: false,
				// eslint-disable-next-line camelcase
				deposits_schedule: {
					interval: 'daily',
				},
			},
		},
		additionalData
	);

useInstantDeposit.mockReturnValue( {
	deposit: undefined,
	isLoading: false,
	submit: () => {},
} );

describe( 'Deposits overview', () => {
	beforeEach( () => {
		global.wcpaySettings = { zeroDecimalCurrencies: [] };
	} );
	afterEach( () => {
		jest.clearAllMocks();
	} );

	test( 'renders correctly', () => {
		mockUseDepositsOverview( getMockedOverview() );
		const { container } = render( <DepositsOverview /> );
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders on error correctly', () => {
		mockUseDepositsOverview();
		const { container } = render( <DepositsOverview /> );
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders temporarily suspended notice for accounts with disabled deposits', () => {
		const depositSchedule = getDepositSchedule(
			{ interval: 'daily' },
			true
		);
		expect( depositSchedule ).toEqual(
			'Deposit schedule: Temporarily suspended (learn more)'
		);
	} );

	test( 'renders temporarily suspended notice for manual interval', () => {
		const depositSchedule = getDepositSchedule( { interval: 'manual' } );
		expect( depositSchedule ).toEqual(
			'Deposit schedule: Temporarily suspended (learn more)'
		);
	} );

	test( 'renders deposit schedule for daily interval', () => {
		const depositSchedule = getDepositSchedule( { interval: 'daily' } );
		expect( depositSchedule ).toEqual(
			'Deposit schedule: Automatic, every business day'
		);
	} );

	test( 'renders deposit schedule for weekly interval', () => {
		const depositSchedule = getDepositSchedule( {
			interval: 'weekly',
			// eslint-disable-next-line camelcase
			weekly_anchor: 'monday',
		} );
		expect( depositSchedule ).toEqual(
			'Deposit schedule: Automatic, every week on Monday'
		);
	} );

	test( 'renders deposit schedule for monthly interval', () => {
		const depositSchedule = getDepositSchedule( {
			interval: 'monthly',
			// eslint-disable-next-line camelcase
			monthly_anchor: 26,
		} );
		expect( depositSchedule ).toEqual(
			'Deposit schedule: Automatic, every month on the 26th'
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
			'Deposit schedule: Automatic, every week on Dienstag'
		);
		// the default locale should not have changed
		expect( momentLib.locale() ).toEqual( 'de' );
	} );

	test( 'renders deposit delay notice prior to first deposit', () => {
		// eslint-disable-next-line camelcase
		const overview = getMockedOverview( { last_deposit: null } );
		mockUseDepositsOverview( overview );
		const { getByText } = render( <DepositsOverview /> );
		const depositSchedule = getByText( 'Deposit schedule:' );
		const expected =
			'Deposit schedule: Automatic, every business day – your first deposit is held for seven days (learn more)';
		expect( depositSchedule.parentElement.textContent ).toEqual( expected );
	} );

	test( 'renders instant deposit button', () => {
		const mockInstantDeposit = {
			// eslint-disable-next-line camelcase
			instant_balance: {
				amount: 12345,
				fee: 185.175,
				net: 12159.83,
				// eslint-disable-next-line camelcase
				fee_percentage: 1.5,
				// eslint-disable-next-line camelcase
				transaction_ids: [ 'txn_ABC123', 'txn_DEF456' ],
			},
		};
		const overview = getMockedOverview( mockInstantDeposit );
		mockUseDepositsOverview( overview );
		const { getByRole } = render( <DepositsOverview /> );
		const instantButton = getByRole( 'button', {
			name: /instant deposit/i,
		} );
		const expected = 'Instant deposit';
		expect( instantButton.textContent ).toEqual( expected );
	} );

	// TODO: Enable/rewrite the following 3 test cases when https://github.com/Automattic/woocommerce-payments/issues/962 is fixed.
	test.skip( 'renders in transit label to in_transit next deposits', () => {
		const overview = getMockedOverview( {
			// eslint-disable-next-line camelcase
			next_deposit: { status: 'in_transit' },
		} );
		mockUseDepositsOverview( overview );
		const { getByText } = render( <DepositsOverview /> );
		const nextDepositDate = getByText( 'Est. Apr 22, 2019 - In transit' );
		expect( nextDepositDate.parentElement.textContent ).toContain(
			'Next deposit'
		);
	} );

	test.skip( 'renders singular number of pending deposits', () => {
		const overview = getMockedOverview( {
			// eslint-disable-next-line camelcase
			balance: { pending: { deposits_count: 1 } },
		} );
		mockUseDepositsOverview( overview );
		const { getByText } = render( <DepositsOverview /> );
		const pendingDeposits = getByText( '1 deposit' );
		expect( pendingDeposits.parentElement.textContent ).toContain(
			'Pending balance'
		);
	} );

	test.skip( 'renders plural number of pending deposits', () => {
		const overview = getMockedOverview( {
			// eslint-disable-next-line camelcase
			balance: { pending: { deposits_count: 2 } },
		} );
		mockUseDepositsOverview( overview );
		const { getByText } = render( <DepositsOverview /> );
		const pendingDeposits = getByText( '2 deposits' );
		expect( pendingDeposits.parentElement.textContent ).toContain(
			'Pending balance'
		);
	} );
} );

function getDepositSchedule( schedule, disabled = false ) {
	const overview = getMockedOverview( {
		// eslint-disable-next-line camelcase
		account: { deposits_schedule: schedule, deposits_disabled: disabled },
	} );
	mockUseDepositsOverview( overview );
	const { getByText } = render( <DepositsOverview /> );
	const depositSchedule = getByText( 'Deposit schedule:' );
	return depositSchedule.parentElement.textContent;
}
