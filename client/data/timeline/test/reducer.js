/** @format */

/**
 * Internal dependencies
 */
import reducer from '../reducer';
import TYPES from '../action-types';

describe( 'Timeline reducer tests', () => {
	const mockIntentionId1 = 'pi_1';
	const mockTimeline1 = [
		{
			id: '1',
		},
	];
	const mockIntentionId2 = 'pi_2';
	const mockTimeline2 = [
		{
			id: '2a',
		},
		{
			id: '2b',
		},
	];
	const mockError = {
		message: 'This is an error.',
	};
	const mockUnrelatedData = [
		{
			unrelatedData: 1,
		},
	];

	const emptyState = {};
	const filledStateSuccess = {
		[ mockIntentionId1 ]: {
			data: mockTimeline1,
		},
	};
	const filledStateError = {
		[ mockIntentionId1 ]: {
			error: mockError,
			data: mockTimeline1,
		},
	};

	test( 'Unrelated action is ignored', () => {
		expect( reducer( emptyState, {
			id: 123,
			type: 'WRONG-TYPE',
			data: mockUnrelatedData,
		} ) ).toBe( emptyState );
		expect( reducer( filledStateSuccess, {
			id: 123,
			type: 'WRONG-TYPE',
			data: mockUnrelatedData,
		} ) ).toBe( filledStateSuccess );
	} );

	test( 'New timeline data reduced correctly', () => {
		expect( reducer( emptyState, {
			id: mockIntentionId1,
			type: TYPES.SET_TIMELINE,
			data: mockTimeline1,
		} ) ).toStrictEqual( filledStateSuccess );

		// Error removed when new timeline data is received
		expect( reducer( filledStateError, {
			id: mockIntentionId1,
			type: TYPES.SET_TIMELINE,
			data: mockTimeline1,
		} ) ).toStrictEqual( filledStateSuccess );
	} );

	test( 'Timeline data for updated correctly', () => {
		expect( reducer( filledStateSuccess, {
			id: mockIntentionId1,
			type: TYPES.SET_TIMELINE,
			data: mockTimeline2,
		} ) ).toStrictEqual( {
			[ mockIntentionId1 ]: {
				data: mockTimeline2,
			},
		} );
	} );

	test( 'New different timeline data reduced correctly', () => {
		expect( reducer( filledStateSuccess, {
			id: mockIntentionId2,
			type: TYPES.SET_TIMELINE,
			data: mockTimeline2,
		} ) ).toStrictEqual( {
			[ mockIntentionId1 ]: {
				data: mockTimeline1,
			},
			[ mockIntentionId2 ]: {
				data: mockTimeline2,
			},
		} );
	} );

	test( 'Timeline error reduced correctly', () => {
		expect( reducer( filledStateSuccess, {
			id: mockIntentionId1,
			type: TYPES.SET_ERROR_FOR_TIMELINE,
			error: mockError,
		} ) ).toStrictEqual( {
			[ mockIntentionId1 ]: {
				...filledStateSuccess[ mockIntentionId1 ],
				error: mockError,
			},
		} );
	} );
} );
