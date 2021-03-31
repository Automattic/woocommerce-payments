/** @format */

/**
 * Internal dependencies
 */
import reducer, { TimelineState } from '../reducer';
import { Event } from '../mocks/types';

describe( 'Timeline reducer tests', () => {
	const mockIntentionId1 = 'pi_1';
	const mockTimeline1 = [ new Event( '1', 0 ) ];
	const mockIntentionId2 = 'pi_2';
	const mockTimeline2 = [ new Event( '2a', 0 ), new Event( '2b', 1 ) ];
	const mockError = new Error( 'This is an error.' );
	const mockUnrelatedData = [
		{
			unrelatedData: 1,
		},
	];

	const emptyState: TimelineState = { data: {}, errors: {} };
	const filledStateSuccess: TimelineState = {
		data: { [ mockIntentionId1 ]: mockTimeline1 },
		errors: {},
	};
	const filledStateError: TimelineState = {
		data: { [ mockIntentionId1 ]: mockTimeline1 },
		errors: { [ mockIntentionId1 ]: mockError },
	};

	test( 'Unrelated action is ignored', () => {
		expect(
			reducer( emptyState, {
				id: 123,
				type: 'WRONG-TYPE',
				data: mockUnrelatedData,
			} )
		).toBe( emptyState );
		expect(
			reducer( filledStateSuccess, {
				id: 123,
				type: 'WRONG-TYPE',
				data: mockUnrelatedData,
			} )
		).toBe( filledStateSuccess );
	} );

	test( 'New timeline data reduced correctly', () => {
		expect(
			reducer( emptyState, {
				id: mockIntentionId1,
				type: 'SET_TIMELINE',
				data: mockTimeline1,
			} )
		).toStrictEqual( filledStateSuccess );

		// Error removed when new timeline data is received
		expect(
			reducer( filledStateError, {
				id: mockIntentionId1,
				type: 'SET_TIMELINE',
				data: mockTimeline1,
			} )
		).toStrictEqual( filledStateSuccess );
	} );

	test( 'Timeline data for updated correctly', () => {
		expect(
			reducer( filledStateSuccess, {
				id: mockIntentionId1,
				type: 'SET_TIMELINE',
				data: mockTimeline2,
			} )
		).toStrictEqual( {
			data: { [ mockIntentionId1 ]: mockTimeline2 },
			errors: {},
		} );
	} );

	test( 'New different timeline data reduced correctly', () => {
		expect(
			reducer( filledStateSuccess, {
				id: mockIntentionId2,
				type: 'SET_TIMELINE',
				data: mockTimeline2,
			} )
		).toStrictEqual( {
			data: {
				[ mockIntentionId1 ]: mockTimeline1,
				[ mockIntentionId2 ]: mockTimeline2,
			},
			errors: {},
		} );
	} );

	test( 'Timeline error reduced correctly', () => {
		expect(
			reducer( filledStateSuccess, {
				id: mockIntentionId1,
				type: 'SET_ERROR_FOR_TIMELINE',
				error: mockError,
			} )
		).toStrictEqual( {
			...filledStateSuccess,
			errors: { [ mockIntentionId1 ]: mockError },
		} );
	} );
} );
