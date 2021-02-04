/** @format */

/**
 * Internal dependencies
 */
import { getTimeline, getTimelineError } from '../selectors';
import { TimelineState } from '../reducer';

describe( 'Timeline selectors', () => {
	const mockIntentionId1 = 'pi_1';
	const mockTimeline1 = [
		{
			id: '1',
		},
	];
	const mockError = new Error( 'This is an error.' );

	const mockSuccessState: { timeline: TimelineState } = {
		timeline: {
			data: {
				[ mockIntentionId1 ]: mockTimeline1,
			},
			errors: {},
		},
	};
	const mockErrorState: { timeline: TimelineState } = {
		timeline: {
			data: {},
			errors: {
				[ mockIntentionId1 ]: mockError,
			},
		},
	};

	test( 'Returns empty timeline list when timeline data is empty', () => {
		expect(
			getTimeline(
				{ timeline: { data: {}, errors: {} } },
				mockIntentionId1
			)
		).toBeUndefined();
		expect(
			getTimeline( mockErrorState, mockIntentionId1 )
		).toBeUndefined();
	} );

	test( 'Returns timeline list from state for a given ID', () => {
		expect( getTimeline( mockSuccessState, mockIntentionId1 ) ).toBe(
			mockTimeline1
		);
	} );

	test( 'Returns empty timeline list error when error is empty', () => {
		expect(
			getTimelineError(
				{ timeline: { data: {}, errors: {} } },
				mockIntentionId1
			)
		).toBeUndefined();
		expect(
			getTimelineError( mockSuccessState, mockIntentionId1 )
		).toBeUndefined();
	} );

	test( 'Returns timeline list error from state', () => {
		expect(
			getTimelineError( mockErrorState, mockIntentionId1 )
		).toStrictEqual( mockError );
	} );
} );
