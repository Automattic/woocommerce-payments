/** @format */

/**
 * Internal dependencies
 */
import {
	getTimeline,
	getTimelineError,
} from '../selectors';

describe( 'Timeline selectors', () => {
	const mockIntentionId1 = 'pi_1';
	const mockTimeline1 = [
		{
			id: '1',
		},
	];
	const mockError = {
		message: 'This is an error.',
	};

	const mockSuccessState = {
		timeline: {
			[ mockIntentionId1 ]: {
				data: mockTimeline1,
			},
		},
	};
	const mockErrorState = {
		timeline: {
			[ mockIntentionId1 ]: {
				error: mockError,
			},
		},
	};

	test( 'Returns empty timeline list when timeline data is empty', () => {
		expect( getTimeline( {}, mockIntentionId1 ) ).toStrictEqual( {} );
		expect( getTimeline( mockErrorState, mockIntentionId1 ) ).toStrictEqual( {} );
	} );

	test( 'Returns timeline list from state for a given ID', () => {
		expect( getTimeline( mockSuccessState, mockIntentionId1 ) ).toBe( mockTimeline1 );
	} );

	test( 'Returns empty timeline list error when error is empty', () => {
		expect( getTimelineError( {}, mockIntentionId1 ) ).toStrictEqual( {} );
		expect( getTimelineError( mockSuccessState, mockIntentionId1 ) ).toStrictEqual( {} );
	} );

	test( 'Returns timeline list error from state', () => {
		expect( getTimelineError( mockErrorState, mockIntentionId1 ) ).toStrictEqual( mockError );
	} );
} );
