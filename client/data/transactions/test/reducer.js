
/**
 * Internal dependencies
 */
import reducer from '../reducer';
import types from '../action-types';

describe( 'Transactoins reducer tests', () => {
	test( 'Wrong action is ignored', () => {
		let mockState = {};
		expect( reducer( mockState, { type: 'wrong-type' } ) ).toBe( mockState );

		mockState = { data: [], summary: { net: 100 } };
		expect( reducer( mockState, { type: 'wrong-type' } ) ).toBe( mockState );
	} );

	test( 'New transactions reduced correctly', () => {
		// Set up mock data
		const mockState = { data: [] };
		const transactions = [
			{
				id: 1234,
				amount: 1000,
				fees: 50,
				net: 950,
			},
			{
				id: 1235,
				amount: 2000,
				fees: 100,
				net: 1900,
			},
		];

		const expected = {
			data: transactions,
		};

		expect( reducer( mockState, { type: types.SET_TRANSACTIONS, data: transactions } ) )
			.toStrictEqual( expected );
	} );

	test( 'Transactions updated correctly on updated info', () => {
		// Set up mock data
		const mockState = { data: [
			{
				id: 1234,
				amount: 1000,
				fees: 50,
				net: 950,
			},
		] };
		const transactions = [
			{
				id: 1235,
				amount: 2000,
				fees: 100,
				net: 1900,
			},
		];

		const expected = {
			data: [ ...mockState.data, ...transactions ],
		};

		expect( reducer( mockState, { type: types.SET_TRANSACTIONS, data: transactions } ) )
			.toStrictEqual( expected );
	} );

	test( 'New transactions summary reduced correctly', () => {
		const mockState = {};
		const summary = {
			total: 1000,
			fees: 50,
			net: 950,
		};

		const expected = {
			summary: {
				data: summary,
			},
		};

		expect( reducer( mockState, { type: types.SET_TRANSACTIONS_SUMMARY, data: summary } ) )
			.toStrictEqual( expected );
	} );

	test( 'Transactions summary updated correctly on updated info', () => {
		const mockState = {
			summary: {
				data: {
					total: 500,
					fees: 10,
					net: 490,
				},
			},
		};
		const summary = {
			total: 1000,
			fees: 50,
			net: 950,
		};

		const expected = {
			summary: {
				data: summary,
			},
		};

		expect( reducer( mockState, { type: types.SET_TRANSACTIONS_SUMMARY, data: summary } ) )
			.toStrictEqual( expected );
	} );
} );
