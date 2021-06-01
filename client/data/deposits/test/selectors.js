/** @format */
/* eslint-disable camelcase */

/**
 * External dependencies
 */
import { get } from 'lodash';

/**
 * Internal dependencies
 */
import { getResourceId } from 'utils/data';
import {
	getDeposit,
	getDeposits,
	getDepositQueryError,
	getDepositsOverview,
	getDepositsOverviewError,
	getInstantDeposit,
	getAllDepositsOverviews,
	getAllDepositsOverviewsError,
} from '../selectors';
import overviewsFixture from './overviews.fixture.json';

// Sections in initial state are empty.
const emptyState = {
	deposits: {},
};

describe( 'Deposit selector', () => {
	const mockDeposit = {
		id: 'po_mock1',
		amount: 2000,
	};

	const filledState = {
		deposits: {
			byId: {
				po_mock1: mockDeposit,
			},
		},
	};

	test( 'Returns undefined when deposit is not present', () => {
		expect( getDeposit( emptyState, 'po_mock1' ) ).toStrictEqual(
			undefined
		);
	} );

	test( 'Returns deposit when it is present', () => {
		expect( getDeposit( filledState, 'po_mock1' ) ).toStrictEqual(
			mockDeposit
		);
	} );
} );

describe( 'Deposits selectors', () => {
	// Mock objects.
	const mockQuery = { paged: '2', perPage: '50' };
	const mockDeposits = [
		{
			id: 'po_mock1',
			amount: 2000,
		},
		{
			id: 'po_mock2',
			amount: 3000,
		},
	];
	const mockError = {
		error: 'Something went wrong!',
		code: 400,
	};

	// State is populated.
	const filledSuccessState = {
		deposits: {
			byId: {
				po_mock1: mockDeposits[ 0 ],
				po_mock2: mockDeposits[ 1 ],
			},
			queries: {
				[ getResourceId( mockQuery ) ]: {
					data: [ 'po_mock1', 'po_mock2' ],
				},
			},
		},
	};
	const filledErrorState = {
		deposits: {
			queries: {
				[ getResourceId( mockQuery ) ]: {
					error: mockError,
				},
			},
		},
	};

	test( 'Returns empty deposits list when deposits list is empty', () => {
		expect( getDeposits( emptyState, mockQuery ) ).toStrictEqual( [] );
	} );

	test( 'Returns deposits list from state', () => {
		const expected = mockDeposits;
		expect( getDeposits( filledSuccessState, mockQuery ) ).toStrictEqual(
			expected
		);
	} );

	test( 'Returns empty deposits query error when error is empty', () => {
		expect( getDepositQueryError( emptyState, mockQuery ) ).toStrictEqual(
			{}
		);
	} );

	test( 'Returns deposits query error from state', () => {
		const expected = mockError;
		expect( getDepositQueryError( filledErrorState, mockQuery ) ).toBe(
			expected
		);
	} );
} );

describe( 'Deposits overview selectors', () => {
	const filledSuccessState = {
		deposits: {
			overview: {
				data: {
					last_deposit: null,
					next_deposit: null,
					balance: { object: 'balance' },
					deposits_schedule: { interval: 'daily' },
				},
			},
		},
	};
	const filledErrorState = {
		deposits: {
			overview: {
				error: {
					code: 'error',
				},
			},
		},
	};

	test( 'Returns undefined when overview is not present', () => {
		expect( getDepositsOverview( emptyState ) ).toStrictEqual( undefined );
	} );

	test( 'Returns undefined when overview error is not present', () => {
		expect( getDepositsOverviewError( emptyState ) ).toStrictEqual(
			undefined
		);
	} );

	test( 'Returns deposits overview from state', () => {
		expect( getDepositsOverview( filledSuccessState ) ).toStrictEqual(
			filledSuccessState.deposits.overview.data
		);
	} );

	test( 'Returns deposits overview error from state', () => {
		expect( getDepositsOverviewError( filledErrorState ) ).toStrictEqual(
			filledErrorState.deposits.overview.error
		);
	} );
} );

describe( 'Deposits overviews selectors', () => {
	const filledErrorState = {
		deposits: {
			overviews: {
				error: {
					code: 'error',
				},
			},
		},
	};

	const checkResult = ( result, path, currency ) => {
		expect( result ).toStrictEqual(
			get( overviewsFixture, path ).find(
				( element ) => element.currency === currency.currency
			)
		);
	};

	test( 'Returns deposits overviews errors from state', () => {
		expect(
			getAllDepositsOverviewsError( filledErrorState )
		).toStrictEqual( filledErrorState.deposits.overviews.error );
	} );

	test( 'Returns an empty object when overviews are not present', () => {
		expect( getAllDepositsOverviews( filledErrorState ) ).toStrictEqual( {
			account: null,
			currencies: [],
		} );
	} );

	test( 'Properly groups by currency', () => {
		const computed = getAllDepositsOverviews( {
			deposits: {
				overviews: {
					data: overviewsFixture,
				},
			},
		} );

		expect( computed.account ).toStrictEqual( overviewsFixture.account );
		expect( computed.currencies.length ).toEqual( 2 );

		const first = computed.currencies[ 0 ];
		const second = computed.currencies[ 1 ];

		// Verify that the default currency is always first.
		// eslint-disable-next-line prettier/prettier
		expect( first.currency ).toEqual(
			overviewsFixture.account.default_currency
		);

		// Check the grouping
		checkResult( first.lastPaid, 'deposit.last_paid', first );
		checkResult( first.nextScheduled, 'deposit.next_scheduled', first );
		checkResult( first.pending, 'balance.pending', first );
		checkResult( first.available, 'balance.available', first );
		checkResult( first.instant, 'balance.instant', first );

		checkResult( second.lastPaid, 'deposit.last_paid', second );
		checkResult( second.nextScheduled, 'deposit.next_scheduled', second );
		checkResult( second.pending, 'balance.pending', second );
		checkResult( second.available, 'balance.available', second );
		checkResult( second.instant, 'balance.instant', second );
	} );
} );

describe( 'Instant Deposit selector', () => {
	const mockDeposit = {
		id: 'po_mock1',
		object: 'payout',
		amount: 2000,
	};

	const filledState = {
		deposits: {
			instant: {
				data: mockDeposit,
			},
		},
	};

	test( 'Returns undefined when instant deposit is not present', () => {
		expect( getInstantDeposit( emptyState ) ).toStrictEqual( undefined );
	} );

	test( 'Returns instant deposit when it is present', () => {
		expect( getInstantDeposit( filledState ) ).toStrictEqual( mockDeposit );
	} );
} );
