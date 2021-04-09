/** @format */
/* eslint-disable camelcase */

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
} from '../selectors';

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
