/** @format */

/**
 * Internal dependencies
 */
import { getResourceId } from 'utils/data';
import { getDispute, getDisputes, getDisputesSummary } from '../selectors';

// Sections in initial state are empty.
const emptyState = {
	disputes: {},
};

describe( 'Dispute selector', () => {
	const mockDispute = {
		id: 'dp_mock1',
		reason: 'product_unacceptable',
	};

	const filledState = {
		disputes: {
			byId: {
				dp_mock1: mockDispute,
			},
		},
	};

	test( 'Returns undefined when dispute is not present', () => {
		expect( getDispute( emptyState, 'dp_mock1' ) ).toStrictEqual(
			undefined
		);
	} );

	test( 'Returns dispute when it is present', () => {
		expect( getDispute( filledState, 'dp_mock1' ) ).toStrictEqual(
			mockDispute
		);
	} );
} );

describe( 'Disputes selectors', () => {
	// Mock objects.
	const mockQuery = { paged: '2', perPage: '50' };
	const mockDisputes = [
		{
			id: 'dp_mock1',
			reason: 'product_unacceptable',
		},
		{
			id: 'dp_mock2',
			reason: 'fraudulent',
		},
	];

	// State is populated.
	const filledSuccessState = {
		disputes: {
			byId: {},
			cached: {
				dp_mock1: mockDisputes[ 0 ],
				dp_mock2: mockDisputes[ 1 ],
			},
			queries: {
				[ getResourceId( mockQuery ) ]: {
					data: [ 'dp_mock1', 'dp_mock2' ],
				},
			},
		},
	};

	test( 'Returns empty disputes list when disputes list is empty', () => {
		expect( getDisputes( emptyState, mockQuery ) ).toStrictEqual( [] );
	} );

	test( 'Returns disputes list from state', () => {
		const expected = mockDisputes;
		expect( getDisputes( filledSuccessState, mockQuery ) ).toStrictEqual(
			expected
		);
	} );
} );

describe( 'Disputes summary selector', () => {
	// Mock objects.
	const mockQuery = { paged: '2', perPage: '50' };
	const mockDisputesSummary = {
		count: 42,
	};

	// State is populated.
	const filledSuccessState = {
		disputes: {
			summary: {
				[ getResourceId( mockQuery ) ]: {
					data: {
						count: 42,
					},
				},
			},
		},
	};

	test( 'Returns empty disputes summary when state is empty', () => {
		expect( getDisputesSummary( emptyState, mockQuery ) ).toStrictEqual(
			{}
		);
	} );

	test( 'Returns disputes summary from state', () => {
		expect(
			getDisputesSummary( filledSuccessState, mockQuery )
		).toStrictEqual( mockDisputesSummary );
	} );
} );
