/** @format */

/**
 * Internal dependencies
 */
import reducer from '../reducer';
import types from '../action-types';
import { getResourceId } from 'utils/data';

describe( 'Disputes reducer tests', () => {
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

	test( 'New individual disputes reduced correctly', () => {
		const stateAfterOne = reducer(
			undefined, // Default state.
			{
				type: types.SET_DISPUTE,
				data: mockDisputes[ 0 ],
			}
		);

		expect( stateAfterOne ).toStrictEqual( {
			byId: {
				dp_mock1: mockDisputes[ 0 ],
			},
			queries: {},
			evidenceTransientForDispute: {},
			evidenceUploadErrorsForDispute: {},
			isSavingEvidenceForDispute: {},
			isUploadingEvidenceForDispute: {},
		} );

		const stateAfterTwo = reducer( stateAfterOne, {
			type: types.SET_DISPUTE,
			data: mockDisputes[ 1 ],
		} );

		expect( stateAfterTwo ).toStrictEqual( {
			byId: {
				dp_mock1: mockDisputes[ 0 ],
				dp_mock2: mockDisputes[ 1 ],
			},
			queries: {},
			evidenceTransientForDispute: {},
			evidenceUploadErrorsForDispute: {},
			isSavingEvidenceForDispute: {},
			isUploadingEvidenceForDispute: {},
		} );
	} );

	test( 'New disputes reduced correctly', () => {
		const reduced = reducer(
			undefined, // Default state.
			{
				type: types.SET_DISPUTES,
				data: mockDisputes,
				query: mockQuery,
			}
		);

		const after = {
			byId: {
				dp_mock1: mockDisputes[ 0 ],
				dp_mock2: mockDisputes[ 1 ],
			},
			queries: {
				[ getResourceId( mockQuery ) ]: {
					data: [ 'dp_mock1', 'dp_mock2' ],
				},
			},
			evidenceTransientForDispute: {},
			evidenceUploadErrorsForDispute: {},
			isSavingEvidenceForDispute: {},
			isUploadingEvidenceForDispute: {},
		};

		expect( reduced ).toStrictEqual( after );
	} );

	test( 'Disputes updated correctly on updated info', () => {
		const before = {
			byId: {
				dp_mock1: mockDisputes[ 0 ],
			},
			queries: {
				earlierQuery: {
					data: [ 'dp_mock1' ],
				},
			},
		};

		const reduced = reducer( before, {
			type: types.SET_DISPUTES,
			data: mockDisputes.slice( 1 ),
			query: mockQuery,
		} );

		const after = {
			byId: {
				dp_mock1: mockDisputes[ 0 ],
				dp_mock2: mockDisputes[ 1 ],
			},
			queries: {
				earlierQuery: {
					data: [ 'dp_mock1' ],
				},
				[ getResourceId( mockQuery ) ]: {
					data: [ 'dp_mock2' ],
				},
			},
		};

		expect( reduced ).toStrictEqual( after );
	} );
} );
